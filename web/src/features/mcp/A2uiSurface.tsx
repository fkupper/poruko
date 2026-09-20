import * as React from 'react';
import {
    A2uiSurface,
    basicCatalog,
    type ReactComponentImplementation,
} from '@a2ui/react/v0_9';
import {
    MessageProcessor,
    type A2uiMessage,
    type SurfaceModel,
} from '@a2ui/web_core/v0_9';

import type { A2uiMessage as ApiA2uiMessage } from '@/api/mcp';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

interface A2uiSurfaceRendererProps {
    messages: ApiA2uiMessage[];
}

export function A2uiSurfaceRenderer({ messages }: A2uiSurfaceRendererProps) {
    const { processor, error } = React.useMemo(() => {
        const nextProcessor = new MessageProcessor([basicCatalog]);

        try {
            nextProcessor.processMessages(messages as unknown as A2uiMessage[]);
            return { processor: nextProcessor, error: null };
        } catch (caught) {
            return {
                processor: nextProcessor,
                error: caught instanceof Error ? caught.message : 'Invalid A2UI payload.',
            };
        }
    }, [messages]);
    const [surfaces, setSurfaces] = React.useState<Array<SurfaceModel<ReactComponentImplementation>>>(
        () => Array.from(processor.model.surfacesMap.values()),
    );

    React.useEffect(() => {
        const sync = () => setSurfaces(Array.from(processor.model.surfacesMap.values()));
        sync();
        const created = processor.onSurfaceCreated(sync);
        const deleted = processor.onSurfaceDeleted(sync);

        return () => {
            created.unsubscribe();
            deleted.unsubscribe();
        };
    }, [processor]);

    if (error) {
        return (
            <Alert variant="destructive">
                <AlertTitle>A2UI could not render</AlertTitle>
                <AlertDescription>{error}</AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            {surfaces.map((surface) => (
                <A2uiSurface key={surface.id} surface={surface} />
            ))}
        </div>
    );
}
