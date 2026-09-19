import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useAuthStore } from '@/stores/authStore';
import { useLedgerStore } from '@/stores/ledgerStore';

import AiImportPage from './AiImportPage';

const fetchAiImportSettingsMock = vi.fn();
const saveAiImportSettingsMock = vi.fn();
const fetchBankAccountMappingsMock = vi.fn();
const fetchStatementImportsMock = vi.fn();
const updateBankAccountMappingMock = vi.fn();
const uploadBankStatementMock = vi.fn();
const fetchAccountsMock = vi.fn();
const createAccountMock = vi.fn();

vi.mock('@/api/ingestion', () => ({
    fetchAiImportSettings: (...args: unknown[]) => fetchAiImportSettingsMock(...args),
    saveAiImportSettings: (...args: unknown[]) => saveAiImportSettingsMock(...args),
    fetchBankAccountMappings: (...args: unknown[]) => fetchBankAccountMappingsMock(...args),
    fetchStatementImports: (...args: unknown[]) => fetchStatementImportsMock(...args),
    updateBankAccountMapping: (...args: unknown[]) => updateBankAccountMappingMock(...args),
    uploadBankStatement: (...args: unknown[]) => uploadBankStatementMock(...args),
}));

vi.mock('@/api/accounts', () => ({
    fetchAccounts: (...args: unknown[]) => fetchAccountsMock(...args),
    createAccount: (...args: unknown[]) => createAccountMock(...args),
}));

function renderPage() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: { retry: false },
            mutations: { retry: false },
        },
    });

    return render(
        <MemoryRouter>
            <QueryClientProvider client={queryClient}>
                <AiImportPage />
            </QueryClientProvider>
        </MemoryRouter>,
    );
}

describe('AiImportPage', () => {
    beforeEach(() => {
        useLedgerStore.setState({ activeLedgerId: 7 });
        useAuthStore.setState({
            user: {
                id: 1,
                name: 'Alice',
                email: 'alice@example.test',
                theme: 'poruko',
                color_mode: 'light',
            },
        });

        fetchAiImportSettingsMock.mockReset();
        saveAiImportSettingsMock.mockReset();
        fetchBankAccountMappingsMock.mockReset();
        fetchStatementImportsMock.mockReset();
        updateBankAccountMappingMock.mockReset();
        uploadBankStatementMock.mockReset();
        fetchAccountsMock.mockReset();
        createAccountMock.mockReset();

        fetchAiImportSettingsMock.mockResolvedValue({
            configured: true,
            provider: 'openai',
            model: 'gpt-4.1-mini',
            masked_api_key: '••••••••1234',
            auto_create_accounts: false,
        });
        fetchBankAccountMappingsMock.mockResolvedValue([
            {
                id: 4,
                ledger_id: 7,
                external_account_name: 'Everyday Checking',
                masked_identifier: '•••• 1234',
                ownership_type: 'personal',
                account_id: null,
                account_name: null,
                suggested_account_id: 10,
                suggested_account_name: 'Alice Checking',
                created_at: null,
                updated_at: null,
            },
        ]);
        fetchStatementImportsMock.mockResolvedValue([
            {
                id: 'import-1',
                status: 'completed',
                filename: 'september.csv',
                parsed_count: 4,
                pending_count: 3,
                duplicate_count: 1,
                failed_count: 0,
                error_message: null,
                processed_at: '2026-09-19T10:00:00Z',
                created_at: '2026-09-19T09:59:00Z',
            },
        ]);
        fetchAccountsMock.mockResolvedValue([
            {
                id: 10,
                ledger_id: 7,
                owner_id: 1,
                name: 'Alice Checking',
                type: 'user_funding',
                balance: 0,
            },
        ]);
        saveAiImportSettingsMock.mockResolvedValue({
            configured: true,
            provider: 'openai',
            model: 'gpt-4.1-mini',
            masked_api_key: '••••••••5678',
            auto_create_accounts: false,
        });
        uploadBankStatementMock.mockResolvedValue({
            id: 'import-2',
            status: 'queued',
            filename: 'october.csv',
        });
        updateBankAccountMappingMock.mockResolvedValue({});
        createAccountMock.mockResolvedValue({ id: 11 });
    });

    afterEach(() => {
        cleanup();
    });

    it('shows server-backed provider status, mappings, and import results', async () => {
        renderPage();

        expect(await screen.findByText('AI statement import')).toBeInTheDocument();
        expect(await screen.findByLabelText('Replace API key'))
            .toHaveAttribute('placeholder', '••••••••1234');
        expect(screen.getByText('Everyday Checking')).toBeInTheDocument();
        expect(screen.getByText('Use suggestion: Alice Checking')).toBeInTheDocument();
        expect(screen.getByText('september.csv')).toBeInTheDocument();
        expect(screen.getByText('3 pending · 1 duplicates · 0 skipped')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /review pending transactions/i }))
            .toHaveAttribute('href', '/transactions');
    });

    it('saves a replacement key through the API instead of browser storage', async () => {
        const user = userEvent.setup();
        const storageSpy = vi.spyOn(Storage.prototype, 'setItem');
        renderPage();

        const keyInput = await screen.findByLabelText('Replace API key');
        await user.type(keyInput, 'sk-replacement-5678');
        await user.click(screen.getByRole('button', { name: 'Save provider settings' }));

        await waitFor(() => {
            expect(saveAiImportSettingsMock).toHaveBeenCalledWith(7, {
                provider: 'openai',
                model: 'gpt-4.1-mini',
                api_key: 'sk-replacement-5678',
                auto_create_accounts: false,
            });
        });
        expect(storageSpy).not.toHaveBeenCalled();
        storageSpy.mockRestore();
    });

    it('queues the selected statement without committing transactions', async () => {
        const user = userEvent.setup();
        renderPage();

        const fileInput = await screen.findByLabelText('Statement file');
        const file = new File(['date,description,amount'], 'october.csv', { type: 'text/csv' });
        fireEvent.change(fileInput, { target: { files: [file] } });
        await user.click(screen.getByRole('button', { name: 'Queue import' }));

        await waitFor(() => {
            expect(uploadBankStatementMock).toHaveBeenCalledWith(7, file);
        });
    });
});
