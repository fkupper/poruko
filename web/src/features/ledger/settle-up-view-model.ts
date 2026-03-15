import type { SettlementPreviewSummary, SettlementStatusItem } from '../../api/types';

export type SettleUpViewModel =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | {
      status: 'confirmation_required';
      preview: SettlementPreviewSummary;
      pendingCycle: SettlementStatusItem;
    }
  | {
      status: 'ready';
      preview: SettlementPreviewSummary;
      latestSettlement: SettlementStatusItem | null;
    };

export function buildSettleUpViewModel(args: {
  isPreviewPending: boolean;
  isStatusPending: boolean;
  previewError: Error | null;
  statusError: Error | null;
  preview: SettlementPreviewSummary | undefined;
  settlements: SettlementStatusItem[] | undefined;
}): SettleUpViewModel {
  if (args.isPreviewPending || args.isStatusPending) {
    return { status: 'loading' };
  }

  if (args.previewError || args.statusError) {
    const message = args.previewError?.message ?? args.statusError?.message ?? 'Failed to load settlement data.';
    return { status: 'error', message };
  }

  if (!args.preview) {
    return { status: 'error', message: 'Missing settlement preview.' };
  }

  const settlements = args.settlements ?? [];
  const pending = settlements.find((item) => item.executed_at === null);

  if (pending) {
    return {
      status: 'confirmation_required',
      preview: args.preview,
      pendingCycle: pending,
    };
  }

  return {
    status: 'ready',
    preview: args.preview,
    latestSettlement: settlements[0] ?? null,
  };
}

