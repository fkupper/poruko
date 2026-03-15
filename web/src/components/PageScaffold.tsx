import type { ReactNode } from 'react';
import { PageHeader } from './PageHeader';

interface PageScaffoldProps {
  title: ReactNode;
  subtitle?: ReactNode;
  headerActions?: ReactNode;
  children: ReactNode;
  className?: string;
}

export function PageScaffold({
  title,
  subtitle,
  headerActions,
  children,
  className,
}: PageScaffoldProps) {
  const rootClassName =
    className === undefined
      ? 'mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8'
      : `mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8 ${className}`;

  return (
    <main className={rootClassName}>
      <PageHeader title={title} subtitle={subtitle} actions={headerActions} />
      {children}
    </main>
  );
}

