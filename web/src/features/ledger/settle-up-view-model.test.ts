import { describe, expect, it } from 'vitest';
import { buildSettleUpViewModel } from './settle-up-view-model';

describe('buildSettleUpViewModel', () => {
  it('returns confirmation_required when there is a pending settlement row', () => {
    const viewModel = buildSettleUpViewModel({
      isPreviewPending: false,
      isStatusPending: false,
      previewError: null,
      statusError: null,
      preview: {
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        settlement_mode: 'joint_clearinghouse',
        summary: {
          total_shared_spend: 1000,
          pool_current_balance: 1000,
        },
        user_breakdowns: [],
        required_transfers: [],
      },
      settlements: [
        {
          id: 1,
          ledger_id: 1,
          period_start: '2026-03-01',
          period_end: '2026-03-31',
          executed_at: null,
        },
      ],
    });

    expect(viewModel.status).toBe('confirmation_required');
  });

  it('passes through user_breakdowns from the preview', () => {
    const breakdowns = [
      {
        user_id: 1,
        name: 'Bob',
        active_ratio: 0.6,
        target_liability: 83000,
        paid_out_of_pocket: 30000,
        net_balance: -53000,
      },
      {
        user_id: 2,
        name: 'Clara',
        active_ratio: 0.4,
        target_liability: 57000,
        paid_out_of_pocket: 0,
        net_balance: -57000,
      },
    ];

    const viewModel = buildSettleUpViewModel({
      isPreviewPending: false,
      isStatusPending: false,
      previewError: null,
      statusError: null,
      preview: {
        ledger_id: 1,
        period_start: '2026-03-01',
        period_end: '2026-03-31',
        settlement_mode: 'joint_clearinghouse',
        summary: {
          total_shared_spend: 140000,
          pool_current_balance: 200000,
        },
        user_breakdowns: breakdowns,
        required_transfers: [],
      },
      settlements: [],
    });

    expect(viewModel.status).toBe('ready');
    if (viewModel.status === 'ready') {
      expect(viewModel.preview.user_breakdowns).toEqual(breakdowns);
    }
  });
});

