import { useCallback, useState } from 'react';

export interface HealthCheckResult {
    provider: string;
    status: 'online' | 'offline';
    healthy: boolean;
    checked_at: string;
}

interface UseProviderHealthReturn {
    healthStatus: Map<string, HealthCheckResult>;
    isChecking: boolean;
    checkHealth: (provider: string) => Promise<HealthCheckResult>;
    checkUserHealth: () => Promise<HealthCheckResult | null>;
}

export function useProviderHealth(): UseProviderHealthReturn {
    const [healthStatus, setHealthStatus] = useState<
        Map<string, HealthCheckResult>
    >(new Map());
    const [isChecking, setIsChecking] = useState(false);

    const checkHealth = useCallback(
        async (provider: string): Promise<HealthCheckResult> => {
            setIsChecking(true);

            try {
                const response = await fetch(
                    `/settings/integrations/health/${provider}`,
                    {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                    },
                );

                if (!response.ok) {
                    throw new Error('Failed to check health');
                }

                const data: HealthCheckResult = await response.json();

                setHealthStatus((prev) => {
                    const next = new Map(prev);
                    next.set(provider, data);
                    return next;
                });

                return data;
            } catch (err) {
                console.error('Error checking health:', err);
                const errorResult: HealthCheckResult = {
                    provider,
                    status: 'offline',
                    healthy: false,
                    checked_at: new Date().toISOString(),
                };

                setHealthStatus((prev) => {
                    const next = new Map(prev);
                    next.set(provider, errorResult);
                    return next;
                });

                return errorResult;
            } finally {
                setIsChecking(false);
            }
        },
        [],
    );

    const checkUserHealth =
        useCallback(async (): Promise<HealthCheckResult | null> => {
            setIsChecking(true);

            try {
                const response = await fetch('/settings/integrations/health', {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                });

                if (!response.ok) {
                    if (response.status === 400) {
                        return null;
                    }
                    throw new Error('Failed to check health');
                }

                const data: HealthCheckResult = await response.json();

                setHealthStatus((prev) => {
                    const next = new Map(prev);
                    next.set(data.provider, data);
                    return next;
                });

                return data;
            } catch (err) {
                console.error('Error checking user health:', err);
                return null;
            } finally {
                setIsChecking(false);
            }
        }, []);

    return {
        healthStatus,
        isChecking,
        checkHealth,
        checkUserHealth,
    };
}
