import type { ReactNode } from 'react';

type SummaryCardTone = 'inflow' | 'destructive' | 'info';

interface SummaryCardProps {
  label: string;
  amount: string;
  descriptor: string;
  tone: SummaryCardTone;
  icon?: ReactNode;
}

const toneClasses: Record<SummaryCardTone, string> = {
  inflow: 'border-inflow/25 bg-inflowSubtle',
  destructive: 'border-destructive/25 bg-destructiveSubtle',
  info: 'border-info/25 bg-infoSubtle',
};

const toneIconClasses: Record<SummaryCardTone, string> = {
  inflow: 'text-inflow',
  destructive: 'text-destructive',
  info: 'text-info',
};

export function SummaryCard({ label, amount, descriptor, tone, icon }: SummaryCardProps) {
  return (
    <article className={`rounded-card border p-4 ${toneClasses[tone]}`}>
      <p className="inline-flex items-center gap-2 text-sm font-medium text-foreground">
        {icon !== undefined && <span aria-hidden="true" className={toneIconClasses[tone]}>{icon}</span>}
        <span className="text-foreground">{label}</span>
      </p>
      <p className={`amount-numeric mt-5 text-3xl font-semibold ${toneIconClasses[tone]}`}>{amount}</p>
      <p className="mt-1 text-xs text-muted-foreground">{descriptor}</p>
    </article>
  );
}
