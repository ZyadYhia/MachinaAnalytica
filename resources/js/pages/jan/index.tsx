import { ChatContainer, type Message } from '@/components/chat-container';
import { useJanChat } from '@/hooks/use-jan-chat';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
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

// Generate unique IDs for messages
let messageIdCounter = 0;
const generateMessageId = () => {
    return `msg-${Date.now()}-${++messageIdCounter}-${Math.random().toString(36).substr(2, 9)}`;
};

export default function JanIndex() {
    const { auth } = usePage().props as { auth: { user: { id: number } } };
    const [messages, setMessages] = useState<Message[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [models, setModels] = useState<Model[]>([]);
    const [selectedModel, setSelectedModel] = useState<string>('');
    const [isConnected, setIsConnected] = useState<boolean | null>(null);
    const [conversationId, setConversationId] = useState<string>(
        () => `conversation-${Date.now()}`,
    );
    const [useAsync, setUseAsync] = useState<boolean>(true);

    const { progressStatus, currentIteration, isProcessing } = useJanChat({
        conversationId,
        useAsync,
        onCompleted: (data) => {
            console.log('=== CHAT COMPLETED EVENT RECEIVED ===');
            console.log('Full data:', data);
            console.log('data.response:', data.response);

            // Try to extract the message content from various possible structures
            let messageContent = 'No response from model';
            let metadata: any = {};

            // Structure 1: data.response.data.choices[0].message.content (expected)
            if (data.response?.data?.choices?.[0]?.message?.content) {
                const janResponse = data.response.data;
                const choice = janResponse.choices[0];
                messageContent = choice.message.content;
                metadata = {
                    model: janResponse.model,
                    usage: janResponse.usage,
                    timings: janResponse.timings,
                    finishReason: choice.finish_reason,
                    metrics: data.metrics,
                };
            }
            // Structure 2: data.response.choices[0].message.content (if data is already unwrapped)
            else if (data.response?.choices?.[0]?.message?.content) {
                const choice = data.response.choices[0];
                messageContent = choice.message.content;
                metadata = {
                    model: data.response.model,
                    usage: data.response.usage,
                    timings: data.response.timings,
                    finishReason: choice.finish_reason,
                    metrics: data.metrics,
                };
            }
            // Structure 3: data.response is a string (fallback)
            else if (typeof data.response === 'string') {
                messageContent = data.response;
            }
            // Structure 4: Check if response.data is a string
            else if (typeof data.response?.data === 'string') {
                messageContent = data.response.data;
            }

            console.log('Extracted message content:', messageContent);

            const aiMessage: Message = {
                id: generateMessageId(),
                content: messageContent,
                isUser: false,
                timestamp: new Date().toISOString(),
                metadata,
            };

            // Prevent duplicate messages - check if message with same timestamp already exists
            setMessages((prev) => {
                const isDuplicate = prev.some(
                    (msg) =>
                        !msg.isUser &&
                        msg.content === messageContent &&
                        Math.abs(
                            new Date(msg.timestamp).getTime() -
                                new Date(aiMessage.timestamp).getTime(),
                        ) < 1000,
                );
                if (isDuplicate) {
                    console.warn('Duplicate message detected, skipping...');
                    return prev;
                }
                return [...prev, aiMessage];
            });
            setIsLoading(false);
        },
        onError: (error) => {
            const errorMessage: Message = {
                id: generateMessageId(),
                content: `Error: ${error}`,
                isUser: false,
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
            setIsLoading(false);
        },
    });

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
                id: generateMessageId(),
                content: 'Please select a model first.',
                isUser: false,
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
            return;
        }

        const userMessage: Message = {
            id: generateMessageId(),
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
                    async: useAsync ? '1' : '0',
                }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message || data.error || 'Failed to send message',
                );
            }

            // If async mode, response will come via WebSocket
            if (useAsync && response.status === 202) {
                console.log('Request queued for async processing:', data);
                // Loading state will be cleared when chat.completed event arrives
                return;
            }

            // Synchronous mode - process response immediately
            const janResponse = data.data;
            const choice = janResponse?.choices?.[0];
            const messageContent =
                choice?.message?.content || 'No response from model';

            const aiMessage: Message = {
                id: generateMessageId(),
                content: messageContent,
                isUser: false,
                timestamp: new Date().toISOString(),
                metadata: {
                    model: janResponse?.model,
                    usage: janResponse?.usage,
                    timings: janResponse?.timings,
                    finishReason: choice?.finish_reason,
                },
            };

            setMessages((prev) => [...prev, aiMessage]);
        } catch (error) {
            console.error('Error sending message:', error);

            let errorMsg =
                'Sorry, I encountered an error. Please try again later.';
            if (error instanceof Error) {
                errorMsg = error.message;
            }

            const errorMessage: Message = {
                id: generateMessageId(),
                content: errorMsg,
                isUser: false,
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
        } finally {
            if (!useAsync) {
                setIsLoading(false);
            }
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
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={useAsync}
                                onChange={(e) => setUseAsync(e.target.checked)}
                                className="size-4 rounded border-gray-300 text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            />
                            <span className="text-xs text-muted-foreground">
                                Async Mode
                            </span>
                        </label>
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
                {progressStatus && useAsync && (
                    <div className="rounded-lg border border-blue-500/50 bg-blue-500/10 p-3 text-sm text-blue-700 dark:text-blue-400">
                        <div className="flex items-center gap-2">
                            <div className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                            <span className="font-medium">
                                {progressStatus}
                            </span>
                            {currentIteration > 0 && (
                                <span className="text-xs opacity-75">
                                    (Iteration {currentIteration})
                                </span>
                            )}
                        </div>
                    </div>
                )}
                <ChatContainer
                    messages={messages}
                    onSendMessage={handleSendMessage}
                    isLoading={isLoading || isProcessing}
                    disabled={models.length === 0 || isConnected === false}
                />
            </div>
        </AppLayout>
    );
}
