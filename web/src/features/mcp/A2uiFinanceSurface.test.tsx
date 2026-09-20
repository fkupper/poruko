import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { A2uiFinanceSurface } from '@/features/mcp/A2uiFinanceSurface';

describe('A2uiFinanceSurface', () => {
    it('renders an authenticated v0.9 surface with the official React client', async () => {
        render(
            <A2uiFinanceSurface
                messages={[
                    {
                        version: 'v0.9',
                        createSurface: {
                            surfaceId: 'finance-test',
                            catalogId: 'https://a2ui.org/specification/v0_9/catalogs/basic/catalog.json',
                        },
                    },
                    {
                        version: 'v0.9',
                        updateComponents: {
                            surfaceId: 'finance-test',
                            components: [
                                { id: 'root', component: 'Column', children: ['title'] },
                                { id: 'title', component: 'Text', text: { path: '/title' }, variant: 'h2' },
                            ],
                        },
                    },
                    {
                        version: 'v0.9',
                        updateDataModel: {
                            surfaceId: 'finance-test',
                            path: '/',
                            value: { title: 'Home finance summary' },
                        },
                    },
                ]}
            />,
        );

        expect(await screen.findByText('Home finance summary')).toBeInTheDocument();
    });
});
