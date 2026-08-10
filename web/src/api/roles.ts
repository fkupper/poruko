import client from './client';

export interface Role {
    id: number;
    name: string;
    permissions: string[];
}

export const fetchLedgerRoles = async (ledgerId: number): Promise<Role[]> => {
    const { data } = await client.get<Role[]>(`/ledgers/${ledgerId}/roles`);
    return data;
};
