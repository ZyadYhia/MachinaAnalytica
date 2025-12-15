import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'LLM Integration',
        href: '/integration',
    },
];

interface Integration {
    id: string;
    name: string;
    description: string;
    icon: string;
    route: string;
    available: boolean;
    requiresSetup: boolean;
}

export default function IntegrationSelector() {
    const [integrations, setIntegrations] = useState<Integration[]>([
        {
            id: 'jan',
            name: 'Jan AI',
            description:
                'Local AI assistant with full control over model parameters. Run LLMs locally on your machine.',
            icon: '🤖',
            route: '/jan',
            available: false,
            requiresSetup: true,
        },
        {
            id: 'anythingllm',
            name: 'AnythingLLM',
            description:
                'Full-featured RAG platform with workspace management, document integration, and thread support.',
            icon: '💬',
            route: '/anythingllm',
            available: false,
            requiresSetup: true,
        },
    ]);

    useEffect(() => {
        checkIntegrations();
    }, []);

    const checkIntegrations = async () => {
        // Check Jan
        try {
            const janResponse = await fetch('/jan/check-connection');
            const janData = await janResponse.json();

            // Check AnythingLLM
            const anythingResponse = await fetch('/anythingllm/check-auth');
            const anythingData = await anythingResponse.json();

            setIntegrations((prev) =>
                prev.map((integration) => {
                    if (integration.id === 'jan') {
                        return { ...integration, available: janData.success };
                    }
                    if (integration.id === 'anythingllm') {
                        return {
                            ...integration,
                            available: anythingData.authenticated === true,
                        };
                    }
                    return integration;
                }),
            );
        } catch (error) {
            console.error('Failed to check integrations:', error);
        }
    };

    const handleSelect = (route: string) => {
        router.visit(route);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="LLM Integration" />

            <div className="mx-auto max-w-4xl py-8">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        Choose Your LLM Integration
                    </h1>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">
                        Select the AI integration that best fits your needs. You
                        can switch between integrations at any time.
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {integrations.map((integration) => (
                        <div
                            key={integration.id}
                            className={`relative overflow-hidden rounded-lg border-2 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:bg-gray-900 ${
                                integration.available
                                    ? 'border-green-300 dark:border-green-700'
                                    : 'border-gray-200 dark:border-gray-700'
                            }`}
                        >
                            {integration.available && (
                                <div className="absolute top-4 right-4">
                                    <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Available
                                    </span>
                                </div>
                            )}

                            <div className="mb-4 text-4xl">
                                {integration.icon}
                            </div>

                            <h3 className="mb-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {integration.name}
                            </h3>

                            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                {integration.description}
                            </p>

                            {!integration.available &&
                                integration.requiresSetup && (
                                    <div className="mb-4 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
                                        <p className="font-medium">
                                            Setup Required
                                        </p>
                                        <p className="mt-1 text-xs">
                                            {integration.id === 'jan'
                                                ? 'Make sure Jan is running on http://localhost:1337'
                                                : 'Configure your AnythingLLM credentials in .env'}
                                        </p>
                                    </div>
                                )}

                            <button
                                onClick={() => handleSelect(integration.route)}
                                disabled={!integration.available}
                                className={`w-full rounded-lg px-4 py-2 font-medium transition-colors ${
                                    integration.available
                                        ? 'bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600'
                                        : 'cursor-not-allowed bg-gray-300 text-gray-500 dark:bg-gray-700 dark:text-gray-500'
                                }`}
                            >
                                {integration.available
                                    ? 'Launch'
                                    : 'Not Available'}
                            </button>
                        </div>
                    ))}
                </div>

                <div className="mt-8 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                    <h4 className="mb-2 font-semibold text-blue-900 dark:text-blue-200">
                        📚 Documentation
                    </h4>
                    <ul className="space-y-1 text-sm text-blue-800 dark:text-blue-300">
                        <li>
                            • Jan Integration: See JAN_INTEGRATION.md for setup
                            instructions
                        </li>
                        <li>
                            • AnythingLLM Integration: See
                            ANYTHINGLLM_INTEGRATION.md for details
                        </li>
                    </ul>
                </div>
            </div>
        </AppLayout>
    );
}
