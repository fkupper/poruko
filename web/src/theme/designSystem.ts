import designTokens from '../../../design.json';

type JsonObject = Record<string, unknown>;

interface TokenLeaf {
  $value: unknown;
  $type?: string;
}

interface TypographyToken {
  fontFamily: string;
  fontWeight: number;
  fontSize: string;
  lineHeight: string;
}

interface PorukoTheme {
  color: {
    background: string;
    surface: string;
    textPrimary: string;
    textSecondary: string;
    border: string;
    focusRing: string;
    inflow: string;
    outflow: string;
    destructive: string;
    info: string;
  };
  spacing: {
    xs: string;
    sm: string;
    md: string;
    lg: string;
    xl: string;
    xxl: string;
  };
  shape: {
    control: string;
    card: string;
    dialog: string;
    pill: string;
  };
  typography: {
    title: TypographyToken;
    body: TypographyToken;
    label: TypographyToken;
    numeric: TypographyToken;
    numericCompact: TypographyToken;
  };
  state: {
    disabledOpacity: number;
    hoverOpacity: number;
    focusOpacity: number;
    pressedOpacity: number;
    focusRingWidth: string;
  };
}

const tokensRoot = designTokens as JsonObject;

function isTokenLeaf(value: unknown): value is TokenLeaf {
  return typeof value === 'object' && value !== null && '$value' in value;
}

function isReference(value: unknown): value is string {
  return typeof value === 'string' && /^\{[^}]+\}$/.test(value);
}

function getByPath(source: JsonObject, path: string): unknown {
  const segments = path.split('.');
  let cursor: unknown = source;

  for (const segment of segments) {
    if (typeof cursor !== 'object' || cursor === null || !(segment in cursor)) {
      throw new Error(`Token path not found: ${path}`);
    }

    cursor = (cursor as JsonObject)[segment];
  }

  return cursor;
}

function resolveValue(value: unknown, stack: string[] = []): unknown {
  if (isReference(value)) {
    const reference = value.slice(1, -1);
    if (stack.includes(reference)) {
      throw new Error(`Circular token reference: ${[...stack, reference].join(' -> ')}`);
    }

    const refNode = getByPath(tokensRoot, reference);
    if (!isTokenLeaf(refNode)) {
      throw new Error(`Reference does not point to token leaf: ${reference}`);
    }

    return resolveValue(refNode.$value, [...stack, reference]);
  }

  if (typeof value === 'object' && value !== null) {
    return Object.fromEntries(
      Object.entries(value as JsonObject).map(([key, nestedValue]) => [key, resolveValue(nestedValue, stack)]),
    );
  }

  return value;
}

function resolveToken(path: string): unknown {
  const node = getByPath(tokensRoot, path);
  if (!isTokenLeaf(node)) {
    throw new Error(`Path is not a token leaf: ${path}`);
  }

  return resolveValue(node.$value, [path]);
}

function resolveTypography(path: string): TypographyToken {
  const value = resolveToken(path);
  if (typeof value !== 'object' || value === null) {
    throw new Error(`Typography token is not an object: ${path}`);
  }

  const token = value as Partial<TypographyToken>;
  if (
    typeof token.fontFamily !== 'string' ||
    typeof token.fontWeight !== 'number' ||
    typeof token.fontSize !== 'string' ||
    typeof token.lineHeight !== 'string'
  ) {
    throw new Error(`Typography token shape is invalid: ${path}`);
  }

  return {
    fontFamily: token.fontFamily,
    fontWeight: token.fontWeight,
    fontSize: token.fontSize,
    lineHeight: token.lineHeight,
  };
}

function resolveString(path: string): string {
  const value = resolveToken(path);
  if (typeof value !== 'string') {
    throw new Error(`Token must resolve to string: ${path}`);
  }

  return value;
}

function resolveNumber(path: string): number {
  const value = resolveToken(path);
  if (typeof value !== 'number') {
    throw new Error(`Token must resolve to number: ${path}`);
  }

  return value;
}

export const porukoTheme: PorukoTheme = {
  color: {
    background: resolveString('semantic.color.background'),
    surface: resolveString('semantic.color.surface'),
    textPrimary: resolveString('semantic.color.textPrimary'),
    textSecondary: resolveString('semantic.color.textSecondary'),
    border: resolveString('semantic.color.border'),
    focusRing: resolveString('semantic.color.focusRing'),
    inflow: resolveString('semantic.color.inflow'),
    outflow: resolveString('semantic.color.outflow'),
    destructive: resolveString('semantic.color.destructive'),
    info: resolveString('semantic.color.info'),
  },
  spacing: {
    xs: resolveString('semantic.spacing.xs'),
    sm: resolveString('semantic.spacing.sm'),
    md: resolveString('semantic.spacing.md'),
    lg: resolveString('semantic.spacing.lg'),
    xl: resolveString('semantic.spacing.xl'),
    xxl: resolveString('semantic.spacing.xxl'),
  },
  shape: {
    control: resolveString('semantic.shape.control'),
    card: resolveString('semantic.shape.card'),
    dialog: resolveString('semantic.shape.dialog'),
    pill: resolveString('semantic.shape.pill'),
  },
  typography: {
    title: resolveTypography('semantic.typography.title'),
    body: resolveTypography('semantic.typography.body'),
    label: resolveTypography('semantic.typography.label'),
    numeric: resolveTypography('semantic.typography.numeric'),
    numericCompact: resolveTypography('semantic.typography.numericCompact'),
  },
  state: {
    disabledOpacity: resolveNumber('states.disabledOpacity'),
    hoverOpacity: resolveNumber('states.stateLayerOpacity.hover'),
    focusOpacity: resolveNumber('states.stateLayerOpacity.focus'),
    pressedOpacity: resolveNumber('states.stateLayerOpacity.pressed'),
    focusRingWidth: resolveString('states.focusRingWidth'),
  },
};

export function applyPorukoThemeVariables(target: HTMLElement = document.documentElement): void {
  // Runtime source variables for semantic Tailwind utilities.
  target.style.setProperty('--ds-color-background', porukoTheme.color.background);
  target.style.setProperty('--ds-color-surface', porukoTheme.color.surface);
  target.style.setProperty('--ds-color-foreground', porukoTheme.color.textPrimary);
  target.style.setProperty('--ds-color-muted-foreground', porukoTheme.color.textSecondary);
  target.style.setProperty('--ds-color-border', porukoTheme.color.border);
  target.style.setProperty('--ds-color-focus-ring', porukoTheme.color.focusRing);
  target.style.setProperty('--ds-color-inflow', porukoTheme.color.inflow);
  target.style.setProperty('--ds-color-outflow', porukoTheme.color.outflow);
  target.style.setProperty('--ds-color-destructive', porukoTheme.color.destructive);
  target.style.setProperty('--ds-color-info', porukoTheme.color.info);

  target.style.setProperty('--ds-space-xs', porukoTheme.spacing.xs);
  target.style.setProperty('--ds-space-sm', porukoTheme.spacing.sm);
  target.style.setProperty('--ds-space-md', porukoTheme.spacing.md);
  target.style.setProperty('--ds-space-lg', porukoTheme.spacing.lg);
  target.style.setProperty('--ds-space-xl', porukoTheme.spacing.xl);
  target.style.setProperty('--ds-space-xxl', porukoTheme.spacing.xxl);

  target.style.setProperty('--ds-radius-control', porukoTheme.shape.control);
  target.style.setProperty('--ds-radius-card', porukoTheme.shape.card);
  target.style.setProperty('--ds-radius-dialog', porukoTheme.shape.dialog);
  target.style.setProperty('--ds-radius-pill', porukoTheme.shape.pill);

  target.style.setProperty('--ds-font-sans', porukoTheme.typography.body.fontFamily);
  target.style.setProperty('--ds-font-numeric', porukoTheme.typography.numeric.fontFamily);
  target.style.setProperty('--ds-font-size-body', porukoTheme.typography.body.fontSize);
  target.style.setProperty('--ds-line-height-body', porukoTheme.typography.body.lineHeight);

  target.style.setProperty('--ds-state-disabled-opacity', String(porukoTheme.state.disabledOpacity));
  target.style.setProperty('--ds-state-hover-opacity', String(porukoTheme.state.hoverOpacity));
  target.style.setProperty('--ds-state-focus-opacity', String(porukoTheme.state.focusOpacity));
  target.style.setProperty('--ds-state-pressed-opacity', String(porukoTheme.state.pressedOpacity));
  target.style.setProperty('--ds-focus-ring-width', porukoTheme.state.focusRingWidth);

  // Backward-compatible aliases for existing var(...) usage.
  target.style.setProperty('--color-background', porukoTheme.color.background);
  target.style.setProperty('--color-surface', porukoTheme.color.surface);
  target.style.setProperty('--color-text-primary', porukoTheme.color.textPrimary);
  target.style.setProperty('--color-text-secondary', porukoTheme.color.textSecondary);
  target.style.setProperty('--color-border', porukoTheme.color.border);
  target.style.setProperty('--color-focus-ring', porukoTheme.color.focusRing);
  target.style.setProperty('--color-inflow', porukoTheme.color.inflow);
  target.style.setProperty('--color-outflow', porukoTheme.color.outflow);
  target.style.setProperty('--color-destructive', porukoTheme.color.destructive);
  target.style.setProperty('--color-info', porukoTheme.color.info);

  target.style.setProperty('--space-xs', porukoTheme.spacing.xs);
  target.style.setProperty('--space-sm', porukoTheme.spacing.sm);
  target.style.setProperty('--space-md', porukoTheme.spacing.md);
  target.style.setProperty('--space-lg', porukoTheme.spacing.lg);
  target.style.setProperty('--space-xl', porukoTheme.spacing.xl);
  target.style.setProperty('--space-xxl', porukoTheme.spacing.xxl);

  target.style.setProperty('--radius-control', porukoTheme.shape.control);
  target.style.setProperty('--radius-card', porukoTheme.shape.card);
  target.style.setProperty('--radius-dialog', porukoTheme.shape.dialog);
  target.style.setProperty('--radius-pill', porukoTheme.shape.pill);

  target.style.setProperty('--font-sans', porukoTheme.typography.body.fontFamily);
  target.style.setProperty('--font-numeric', porukoTheme.typography.numeric.fontFamily);
  target.style.setProperty('--font-size-body', porukoTheme.typography.body.fontSize);
  target.style.setProperty('--line-height-body', porukoTheme.typography.body.lineHeight);

  target.style.setProperty('--state-disabled-opacity', String(porukoTheme.state.disabledOpacity));
  target.style.setProperty('--state-hover-opacity', String(porukoTheme.state.hoverOpacity));
  target.style.setProperty('--state-focus-opacity', String(porukoTheme.state.focusOpacity));
  target.style.setProperty('--state-pressed-opacity', String(porukoTheme.state.pressedOpacity));
  target.style.setProperty('--focus-ring-width', porukoTheme.state.focusRingWidth);
}
