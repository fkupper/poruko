#!/usr/bin/env node

import { readdir, readFile } from 'node:fs/promises';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = join(__filename, '..');
const projectRoot = join(__dirname, '..');
const srcDir = join(projectRoot, 'src');

const IGNORED_PANEL_PATH_PATTERNS = ['/features/auth/'];

async function collectTsxFiles(dir, acc = []) {
  const entries = await readdir(dir, { withFileTypes: true });

  for (const entry of entries) {
    const fullPath = join(dir, entry.name);

    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === '.storybook' || entry.name === '.turbo') {
        continue;
      }

      await collectTsxFiles(fullPath, acc);
      continue;
    }

    if (entry.isFile() && fullPath.endsWith('.tsx')) {
      acc.push(fullPath);
    }
  }

  return acc;
}

async function checkSectionBlocks() {
  const files = await collectTsxFiles(srcDir);
  const offenders = [];

  for (const file of files) {
    const rel = relative(projectRoot, file);

    if (IGNORED_PANEL_PATH_PATTERNS.some((pattern) => rel.includes(pattern))) {
      // Allow auth screens to diverge for now
      continue;
    }

    const content = await readFile(file, 'utf8');

    if (!content.includes('className="panel') && !content.includes("className='panel")) {
      // No panel usage in this file, nothing to check.
      continue;
    }

    const hasSectionTitle =
      content.includes('className="section-title"') || content.includes("className='section-title'");

    if (!hasSectionTitle) {
      offenders.push(rel);
    }
  }

  if (offenders.length > 0) {
    console.error(
      `Design check failed: panel containers must use the section-block header pattern (section-title).\n` +
        `The following files use .panel but do not include section-title:\n` +
        offenders.map((f) => `  - ${f}`).join('\n'),
    );
    process.exitCode = 1;
  }
}

function runCommand(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      stdio: 'inherit',
      cwd: projectRoot,
      shell: false,
      ...options,
    });

    child.on('exit', (code) => {
      if (code === 0) {
        resolve();
      } else {
        reject(new Error(`${command} ${args.join(' ')} exited with code ${code}`));
      }
    });
  });
}

async function main() {
  await checkSectionBlocks();

  if (process.exitCode && process.exitCode !== 0) {
    // Fail fast if design contract is broken.
    process.exit(process.exitCode);
  }

  // Run existing quality gates as part of the design check.
  await runCommand('npm', ['run', 'lint'], { cwd: projectRoot });
  await runCommand('npm', ['run', 'test'], { cwd: projectRoot });
  await runCommand('npm', ['run', 'build-storybook'], { cwd: projectRoot });
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});

