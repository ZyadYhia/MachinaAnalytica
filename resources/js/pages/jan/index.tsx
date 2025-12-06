import { ChatContainer, type Message } from '@/components/chat-container';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Model {
    id: string;
    name?: string;
    object?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jan AI',
        href: '/jan',
    },
];

export default function JanIndex() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [models, setModels] = useState<Model[]>([]);
    const [selectedModel, setSelectedModel] = useState<string>('');
    const [isConnected, setIsConnected] = useState<boolean | null>(null);
    const [conversationId, setConversationId] = useState<string>(
        () => `conversation-${Date.now()}`,
    );

    useEffect(() => {
        checkConnection();
        fetchModels();
    }, []);

    const checkConnection = async () => {
        try {
            const response = await fetch('/jan/check-connection');
            const data = await response.json();
            setIsConnected(data.success);
        } catch (error) {
            console.error('Connection check failed:', error);
            setIsConnected(false);
        }
    };

    const fetchModels = async () => {
        try {
            const response = await fetch('/jan/models');
            const data = await response.json();

            if (data.success && data.data?.data) {
                setModels(data.data.data);
                if (data.data.data.length > 0 && !selectedModel) {
                    setSelectedModel(data.data.data[0].id);
                }
            }
        } catch (error) {
            console.error('Failed to fetch models:', error);
        }
    };

    const handleSendMessage = async (content: string) => {
        if (!selectedModel) {
            const errorMessage: Message = {
                id: Date.now().toString(),
                content: 'Please select a model first.',
                isUser: false,
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
            return;
        }

        const userMessage: Message = {
            id: Date.now().toString(),
            content,
            isUser: true,
            timestamp: new Date().toISOString(),
        };

        setMessages((prev) => [...prev, userMessage]);
        setIsLoading(true);

        try {
            const csrfToken =
                (window as { csrfToken?: string }).csrfToken ||
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ||
                '';

            // Create AbortController with 5 minute timeout for Jan AI responses
            const abortController = new AbortController();
            const timeoutId = setTimeout(() => abortController.abort(), 300000);

            try {
                const response = await fetch('/jan/chat/history', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        model: selectedModel,
                        message: content,
                        conversation_id: conversationId,
                        temperature: 0.8,
                        max_tokens: 2048,
                    }),
                    signal: abortController.signal,
                });
                clearTimeout(timeoutId);

                // Try to parse the response as JSON
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error(
                        'Failed to parse response as JSON:',
                        jsonError,
                    );
                    throw new Error(
                        'Invalid response from server. Please try again.',
                    );
                }

                if (!response.ok || !data.success) {
                    throw new Error(
                        data.message || data.error || 'Failed to send message',
                    );
                }

                // Debug: Log the full response
                console.log('Full response:', data);

                // Extract response from Jan API structure (now wrapped in data.data)
                const janResponse = data.data;
                const choice = janResponse?.choices?.[0];
                const messageContent =
                    choice?.message?.content || 'No response from model';
                const reasoningContent = choice?.message?.reasoning_content;
                const usage = janResponse?.usage;
                const timings = janResponse?.timings;

                // Debug: Log what we extracted
                console.log('Message content:', messageContent);
                console.log('Conversation ID:', janResponse?.conversation_id);
                console.log('History length:', janResponse?.history_length);

                // Build the AI response with metadata
                const fullResponse = messageContent;

                // Optionally show reasoning in development
                if (reasoningContent && import.meta.env.DEV) {
                    console.log('Reasoning:', reasoningContent);
                }

                // Log usage and timings for debugging
                if (usage || timings) {
                    console.log('Usage:', usage);
                    console.log('Timings:', timings);
                }

                const aiMessage: Message = {
                    id: (Date.now() + 1).toString(),
                    content: fullResponse,
                    isUser: false,
                    timestamp: new Date().toISOString(),
                    metadata: {
                        model: janResponse?.model,
                        usage: usage,
                        timings: timings,
                        finishReason: choice?.finish_reason,
                    },
                };

                setMessages((prev) => [...prev, aiMessage]);
            } catch (fetchError) {
                clearTimeout(timeoutId);
                throw fetchError;
            }
        } catch (error) {
            console.error('Error sending message:', error);

            let errorMsg =
                'Sorry, I encountered an error. Please try again later.';
            if (error instanceof Error) {
                if (error.name === 'AbortError') {
                    errorMsg =
                        'Request timed out after 5 minutes. The model may be taking too long to respond.';
                } else {
                    errorMsg = error.message;
                }
            }

            const errorMessage: Message = {
                id: (Date.now() + 1).toString(),
                content: errorMsg,
                isUser: false,
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    const handleClearConversation = () => {
        setMessages([]);
        setConversationId(`conversation-${Date.now()}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jan AI" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden p-4">
                {models.length > 0 && (
                    <div className="flex items-center gap-3 rounded-lg border border-sidebar-border bg-card p-3">
                        <label
                            htmlFor="model-select"
                            className="text-sm font-medium text-muted-foreground"
                        >
                            Model:
                        </label>
                        <select
                            id="model-select"
                            value={selectedModel}
                            onChange={(e) => setSelectedModel(e.target.value)}
                            className="rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {models.map((model) => (
                                <option key={model.id} value={model.id}>
                                    {model.name || model.id}
                                </option>
                            ))}
                        </select>
                        <span className="text-xs text-muted-foreground">
                            Powered by Jan AI
                        </span>
                        {messages.length > 0 && (
                            <button
                                onClick={handleClearConversation}
                                className="ml-auto rounded-md border border-input bg-background px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                New Chat
                            </button>
                        )}
                        {isConnected && (
                            <span
                                className={`flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 ${messages.length > 0 ? '' : 'ml-auto'}`}
                            >
                                <span className="size-2 rounded-full bg-green-600 dark:bg-green-400"></span>
                                Connected
                            </span>
                        )}
                    </div>
                )}
                {isConnected === false && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        <p className="font-semibold">
                            Jan service is not available
                        </p>
                        <p className="mt-1 text-xs">
                            Please ensure Jan is running on{' '}
                            {import.meta.env.VITE_JAN_URL ||
                                'http://localhost:1337'}
                        </p>
                    </div>
                )}
                {models.length === 0 && isConnected !== false && (
                    <div className="rounded-lg border border-yellow-500/50 bg-yellow-500/10 p-4 text-sm text-yellow-700 dark:text-yellow-400">
                        <p className="font-semibold">No models available</p>
                        <p className="mt-1 text-xs">
                            Please load at least one model in Jan to start
                            chatting.
                        </p>
                    </div>
                )}
                <ChatContainer
                    messages={messages}
                    onSendMessage={handleSendMessage}
                    isLoading={isLoading}
                    disabled={models.length === 0 || isConnected === false}
                />
            </div>
        </AppLayout>
    );
}
