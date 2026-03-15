<?php

namespace Tools\PhpCsFixer;

use PhpCsFixer\Runner\Parallel\ParallelConfig as BaseParallelConfig;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

final class ParallelConfig
{
    private const int DEFAULT_MAX_PROCESSES = 4;

    public static function get(): BaseParallelConfig
    {
        $runningInGithubActions = getenv('GITHUB_ACTIONS') !== false || getenv('CI') === 'true';

        if ($runningInGithubActions) {
            return new BaseParallelConfig(
                self::DEFAULT_MAX_PROCESSES,
                BaseParallelConfig::DEFAULT_FILES_PER_PROCESS, /** @phpstan-ignore-line */
                BaseParallelConfig::DEFAULT_PROCESS_TIMEOUT, /** @phpstan-ignore-line */
            );
        }

        return ParallelConfigFactory::detect();
    }
}
