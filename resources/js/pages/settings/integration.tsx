import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    useIntegrationSettings,
    type IntegrationSettings,
} from '@/hooks/useIntegrationSettings';
import { useProviderHealth } from '@/hooks/useProviderHealth';
import { useProviderModels } from '@/hooks/useProviderModels';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { CheckCircle, RefreshCw, XCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Integration settings',
        href: '/settings/integrations',
    },
];

interface IntegrationPageProps {
    integration: IntegrationSettings | null;
    availableProviders: string[];
}

export default function Integration({
    integration,
    availableProviders,
}: IntegrationPageProps) {
    const { settings, isLoading, error, updateSettings } =
        useIntegrationSettings(integration);
    const { healthStatus, isChecking, checkHealth } = useProviderHealth();
    const {
        models,
        isLoading: isLoadingModels,
        fetchModels,
    } = useProviderModels();

    const [localProvider, setLocalProvider] = useState(
        settings?.active_integration || 'none',
    );
    const [localModel, setLocalModel] = useState(settings?.active_model || '');
    const [localChatMode, setLocalChatMode] = useState(
        settings?.chat_mode || 'sync',
    );
    const [isSaving, setIsSaving] = useState(false);
    const [saveSuccess, setSaveSuccess] = useState(false);

    useEffect(() => {
        if (settings) {
            setLocalProvider(settings.active_integration);
            setLocalModel(settings.active_model || '');
            setLocalChatMode(settings.chat_mode);
        }
    }, [settings]);

    useEffect(() => {
        if (localProvider && localProvider !== 'none') {
            fetchModels(localProvider);
            checkHealth(localProvider);
        }
    }, [localProvider, fetchModels, checkHealth]);

    // Auto-check health on initial load
    useEffect(() => {
        if (
            settings?.active_integration &&
            settings.active_integration !== 'none'
        ) {
            checkHealth(settings.active_integration);
        }
    }, []);

    const handleProviderChange = (value: string) => {
        setLocalProvider(value);
        setLocalModel('');
    };

    const handleSave = async () => {
        setIsSaving(true);
        setSaveSuccess(false);

        try {
            await updateSettings({
                active_integration: localProvider,
                active_model: localModel || null,
                chat_mode: localChatMode,
            });
            setSaveSuccess(true);
            setTimeout(() => setSaveSuccess(false), 3000);
        } catch (err) {
            console.error('Failed to save settings:', err);
        } finally {
            setIsSaving(false);
        }
    };

    const handleHealthCheck = async (provider: string) => {
        await checkHealth(provider);
    };

    const getProviderStatus = (provider: string) => {
        const health = healthStatus.get(provider);
        return (
            health?.status ||
            (settings?.active_integration === provider
                ? settings?.integration_status
                : 'unknown')
        );
    };

    const getStatusIcon = (provider: string) => {
        const status = getProviderStatus(provider);
        if (status === 'online') {
            return <CheckCircle className="h-5 w-5 text-green-600" />;
        } else if (status === 'offline') {
            return <XCircle className="h-5 w-5 text-red-600" />;
        }
        return (
            <div className="h-5 w-5 rounded-full bg-gray-300 dark:bg-gray-700" />
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integration settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="LLM Integration settings"
                        description="Configure your preferred LLM provider and chat settings"
                    />

                    {error && (
                        <div className="rounded-md bg-red-50 p-4 dark:bg-red-900/20">
                            <p className="text-sm text-red-800 dark:text-red-400">
                                {error}
                            </p>
                        </div>
                    )}

                    {saveSuccess && (
                        <div className="rounded-md bg-green-50 p-4 dark:bg-green-900/20">
                            <p className="text-sm text-green-800 dark:text-green-400">
                                Settings saved successfully!
                            </p>
                        </div>
                    )}

                    <div className="space-y-6">
                        {/* Provider Selection */}
                        <div className="grid gap-4">
                            <Label htmlFor="provider">Active integration</Label>
                            <Select
                                value={localProvider}
                                onValueChange={handleProviderChange}
                            >
                                <SelectTrigger id="provider" className="w-full">
                                    <SelectValue placeholder="Select a provider" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">None</SelectItem>
                                    {availableProviders.map((provider) => (
                                        <SelectItem
                                            key={provider}
                                            value={provider}
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="capitalize">
                                                    {provider}
                                                </span>
                                                {getStatusIcon(provider)}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Choose your preferred LLM provider
                            </p>
                        </div>

                        {/* Provider Health Status */}
                        {localProvider && localProvider !== 'none' && (
                            <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        {getStatusIcon(localProvider)}
                                        <div>
                                            <p className="font-medium capitalize">
                                                {localProvider}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Status:{' '}
                                                {getProviderStatus(
                                                    localProvider,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handleHealthCheck(localProvider)
                                        }
                                        disabled={isChecking}
                                    >
                                        <RefreshCw
                                            className={`mr-2 h-4 w-4 ${isChecking ? 'animate-spin' : ''}`}
                                        />
                                        Check health
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* Model Selection */}
                        {localProvider &&
                            localProvider !== 'none' &&
                            models.length > 0 && (
                                <div className="grid gap-4">
                                    <Label htmlFor="model">Model</Label>
                                    <Select
                                        value={localModel}
                                        onValueChange={setLocalModel}
                                    >
                                        <SelectTrigger
                                            id="model"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a model" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {models.map((model) => (
                                                <SelectItem
                                                    key={model.id}
                                                    value={model.id}
                                                >
                                                    {model.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {isLoadingModels && (
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Loading models...
                                        </p>
                                    )}
                                </div>
                            )}

                        {/* Chat Mode */}
                        {localProvider && localProvider !== 'none' && (
                            <div className="grid gap-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <Label htmlFor="chat-mode">
                                            Async chat mode
                                        </Label>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Process chat requests in the
                                            background
                                        </p>
                                    </div>
                                    <Switch
                                        id="chat-mode"
                                        checked={localChatMode === 'async'}
                                        onCheckedChange={(checked) =>
                                            setLocalChatMode(
                                                checked ? 'async' : 'sync',
                                            )
                                        }
                                    />
                                </div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {localChatMode === 'async'
                                        ? 'Responses will be delivered in the background. You can navigate away while processing.'
                                        : 'Responses will be delivered immediately. You must wait for the response.'}
                                </p>
                            </div>
                        )}

                        {/* Save Button */}
                        <div className="flex justify-end gap-3">
                            <Button
                                onClick={handleSave}
                                disabled={isSaving || isLoading}
                            >
                                {isSaving ? 'Saving...' : 'Save settings'}
                            </Button>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
