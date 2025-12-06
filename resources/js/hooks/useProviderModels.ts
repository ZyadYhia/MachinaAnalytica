import { useCallback, useEffect, useState } from 'react';

export interface ProviderModel {
    id: string;
    name: string;
    object: string;
}

interface UseProviderModelsReturn {
    models: ProviderModel[];
    isLoading: boolean;
    error: string | null;
    fetchModels: (provider: string) => Promise<void>;
}

export function useProviderModels(provider?: string): UseProviderModelsReturn {
    const [models, setModels] = useState<ProviderModel[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchModels = useCallback(async (providerName: string) => {
        if (!providerName || providerName === 'none') {
            setModels([]);
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(
                `/settings/integrations/models/${providerName}`,
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                },
            );

            if (!response.ok) {
                throw new Error('Failed to fetch models');
            }

            const data = await response.json();
            setModels(data.models || []);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
            console.error('Error fetching models:', err);
            setModels([]);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        if (provider && provider !== 'none') {
            fetchModels(provider);
        }
    }, [provider, fetchModels]);

    return {
        models,
        isLoading,
        error,
        fetchModels,
    };
}
