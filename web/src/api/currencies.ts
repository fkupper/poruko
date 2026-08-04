import client from '@/api/client';

export interface CurrencyOption {
    code: string;
    symbol: string;
    name: string;
}

export interface CurrenciesResponse {
    default: string;
    available: CurrencyOption[];
}

export async function fetchCurrencies(): Promise<CurrenciesResponse> {
    const { data } = await client.get<CurrenciesResponse>('/currencies');
    return data;
}
