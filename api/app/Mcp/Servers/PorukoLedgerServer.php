<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ApprovePendingTransactionsTool;
use App\Mcp\Tools\ConfirmSettlementTool;
use App\Mcp\Tools\CreateAccountTool;
use App\Mcp\Tools\CreateRecurringBlueprintTool;
use App\Mcp\Tools\DeleteAccountTool;
use App\Mcp\Tools\DeleteRecurringBlueprintTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\GetFinancialProfileTool;
use App\Mcp\Tools\GetMcpSettingsTool;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\ListLedgersTool;
use App\Mcp\Tools\ListMcpActionLogsTool;
use App\Mcp\Tools\ListPendingTransactionsTool;
use App\Mcp\Tools\ListRecurringBlueprintsTool;
use App\Mcp\Tools\ListSettlementsTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Mcp\Tools\PostTransactionTool;
use App\Mcp\Tools\PreviewSettlementTool;
use App\Mcp\Tools\ProposeTransactionTool;
use App\Mcp\Tools\RejectPendingTransactionsTool;
use App\Mcp\Tools\RenderPendingApprovalsUiTool;
use App\Mcp\Tools\RenderTransactionInspectionUiTool;
use App\Mcp\Tools\ShowTransactionTool;
use App\Mcp\Tools\UpdateAccountTool;
use App\Mcp\Tools\UpdateFinancialProfileTool;
use App\Mcp\Tools\UpdateMcpSettingsTool;
use App\Mcp\Tools\UpdateRecurringBlueprintTool;
use App\Mcp\Tools\UpdateTransactionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Poruko Ledger')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Poruko ledger MCP for the authenticated user. Tools cover transactions, accounts, settlements, recurring blueprints, and My Finance.

    Never manage users or create/update/delete spaces. Never spend from another member's personal account.
    Honor the user's MCP read/write/destructive settings. Direct posting vs the shared approval queue follows post_mode.
    MCP action logs are observability only and must not be used as the approval queue.
    MARKDOWN)]
class PorukoLedgerServer extends Server
{
    /** @var array<int, class-string<Server\Tool>> */
    protected array $tools = [
        ListLedgersTool::class,
        GetMcpSettingsTool::class,
        UpdateMcpSettingsTool::class,
        ListMcpActionLogsTool::class,
        ListAccountsTool::class,
        CreateAccountTool::class,
        UpdateAccountTool::class,
        DeleteAccountTool::class,
        ListTransactionsTool::class,
        ShowTransactionTool::class,
        ProposeTransactionTool::class,
        PostTransactionTool::class,
        UpdateTransactionTool::class,
        DeleteTransactionTool::class,
        ListPendingTransactionsTool::class,
        ApprovePendingTransactionsTool::class,
        RejectPendingTransactionsTool::class,
        RenderPendingApprovalsUiTool::class,
        RenderTransactionInspectionUiTool::class,
        ListRecurringBlueprintsTool::class,
        CreateRecurringBlueprintTool::class,
        UpdateRecurringBlueprintTool::class,
        DeleteRecurringBlueprintTool::class,
        PreviewSettlementTool::class,
        ListSettlementsTool::class,
        ConfirmSettlementTool::class,
        GetFinancialProfileTool::class,
        UpdateFinancialProfileTool::class,
    ];
}
