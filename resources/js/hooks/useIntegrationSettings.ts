import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

export interface IntegrationSettings {
    id?: number;
    user_id?: number;
    active_integration: string;
    integration_status: string;
    active_model?: string | null;
    model_provider?: string | null;
    chat_mode: string;
    last_health_check_at?: string | null;
    provider_config?: Record<string, any> | null;
    created_at?: string;
    updated_at?: string;
}

interface UseIntegrationSettingsReturn {
    settings: IntegrationSettings | null;
    isLoading: boolean;
    error: string | null;
    updateSettings: (data: Partial<IntegrationSettings>) => Promise<void>;
    refreshSettings: () => Promise<void>;
}

export function useIntegrationSettings(
    initialSettings: IntegrationSettings | null = null,
): UseIntegrationSettingsReturn {
    const [settings, setSettings] = useState<IntegrationSettings | null>(
        initialSettings,
    );
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const refreshSettings = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch('/settings/integrations/show', {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error('Failed to fetch integration settings');
            }

            const data = await response.json();
            setSettings(data.integration);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
            console.error('Error fetching integration settings:', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const updateSettings = useCallback(
        async (data: Partial<IntegrationSettings>) => {
            setIsLoading(true);
            setError(null);

            return new Promise<void>((resolve, reject) => {
                router.patch('/settings/integrations', data, {
                    preserveScroll: true,
                    onSuccess: (page: any) => {
                        const updatedSettings = page.props?.integration || null;
                        if (updatedSettings) {
                            setSettings(updatedSettings);
                        }
                        setIsLoading(false);
                        resolve();
                    },
                    onError: (errors: any) => {
                        const errorMessage =
                            typeof errors === 'string'
                                ? errors
                                : Object.values(errors).flat().join(', ');
                        setError(errorMessage);
                        setIsLoading(false);
                        reject(new Error(errorMessage));
                    },
                    onFinish: () => {
                        setIsLoading(false);
                    },
                });
            });
        },
        [],
    );

    return {
        settings,
        isLoading,
        error,
        updateSettings,
        refreshSettings,
    };
}
