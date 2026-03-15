import type { ReactNode } from 'react';

interface SectionBlockProps {
  title: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
  children?: ReactNode;
  className?: string;
}

export function SectionBlock({ title, subtitle, actions, children, className }: SectionBlockProps) {
  const rootClassName = className === undefined ? 'panel' : `panel ${className}`;

  return (
    <section className={rootClassName}>
      <div className="flex items-start justify-between gap-4">
        <div className="section-header">
          <h2 className="section-title">{title}</h2>
          {subtitle !== undefined && <p className="section-subtitle">{subtitle}</p>}
        </div>
        {actions !== undefined && <div>{actions}</div>}
      </div>

      {children !== undefined && <div className="mt-4">{children}</div>}
    </section>
  );
}

