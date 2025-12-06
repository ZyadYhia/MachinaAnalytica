import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

export interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
    tool_calls?: any[];
    metadata?: Record<string, any>;
    created_at?: string;
}

export interface Conversation {
    id: number;
    title: string;
    provider: string;
    model?: string;
    last_message_at?: string;
    messages?: ChatMessage[];
}

interface UseChatWidgetReturn {
    isOpen: boolean;
    isMinimized: boolean;
    messages: ChatMessage[];
    currentConversation: Conversation | null;
    conversations: Conversation[];
    isLoading: boolean;
    isSending: boolean;
    error: string | null;
    toggleOpen: () => void;
    toggleMinimize: () => void;
    sendMessage: (content: string) => Promise<void>;
    loadConversation: (conversationId: number) => Promise<void>;
    createNewConversation: () => void;
    deleteConversation: (conversationId: number) => Promise<void>;
    clearError: () => void;
}

export function useChatWidget(userId?: number): UseChatWidgetReturn {
    const [isOpen, setIsOpen] = useState(false);
    const [isMinimized, setIsMinimized] = useState(false);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [currentConversation, setCurrentConversation] =
        useState<Conversation | null>(null);
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Load conversations on mount
    useEffect(() => {
        loadConversations();
    }, []);

    const loadConversations = useCallback(async () => {
        try {
            const response = await fetch('/unified-chat/conversations', {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error('Failed to load conversations');
            }

            const data = await response.json();
            setConversations(data.data || []);
        } catch (err) {
            console.error('Error loading conversations:', err);
        }
    }, []);

    const loadConversation = useCallback(async (conversationId: number) => {
        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(
                `/unified-chat/conversations/${conversationId}`,
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                },
            );

            if (!response.ok) {
                throw new Error('Failed to load conversation');
            }

            const data = await response.json();
            setCurrentConversation(data.conversation);
            setMessages(data.conversation.messages || []);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'An error occurred');
            console.error('Error loading conversation:', err);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const sendMessage = useCallback(
        async (content: string) => {
            if (!content.trim()) return;

            setIsSending(true);
            setError(null);

            // Optimistically add user message
            const userMessage: ChatMessage = {
                role: 'user',
                content,
                created_at: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, userMessage]);

            return new Promise<void>((resolve, reject) => {
                router.post(
                    '/unified-chat',
                    {
                        message: content,
                        conversation_id: currentConversation?.id,
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: (page: any) => {
                            const response =
                                page.props?.response || page.props?.data;

                            if (response?.mode === 'async') {
                                // Async mode - response will come via WebSocket
                                // Update conversation ID if new conversation was created
                                if (
                                    response.conversation_id &&
                                    !currentConversation
                                ) {
                                    setCurrentConversation({
                                        id: response.conversation_id,
                                        title: 'New Conversation',
                                        provider: 'unknown',
                                    });
                                }
                            } else {
                                // Sync mode - add assistant response immediately
                                const assistantMessage: ChatMessage = {
                                    role: 'assistant',
                                    content: response?.response || '',
                                    metadata: response?.metadata,
                                    created_at: new Date().toISOString(),
                                };
                                setMessages((prev) => [
                                    ...prev,
                                    assistantMessage,
                                ]);
                                setIsSending(false);

                                // Update conversation ID if new conversation was created
                                if (
                                    response?.conversation_id &&
                                    !currentConversation
                                ) {
                                    setCurrentConversation({
                                        id: response.conversation_id,
                                        title: 'New Conversation',
                                        provider: 'unknown',
                                    });
                                }
                            }

                            loadConversations();
                            resolve();
                        },
                        onError: (errors: any) => {
                            const errorMessage =
                                typeof errors === 'object'
                                    ? errors.error ||
                                      Object.values(errors).flat().join(', ')
                                    : errors;
                            setError(errorMessage);
                            setIsSending(false);
                            // Remove optimistic user message on error
                            setMessages((prev) => prev.slice(0, -1));
                            reject(new Error(errorMessage));
                        },
                    },
                );
            });
        },
        [currentConversation, loadConversations],
    );

    const createNewConversation = useCallback(() => {
        setCurrentConversation(null);
        setMessages([]);
        setError(null);
    }, []);

    const deleteConversation = useCallback(
        async (conversationId: number) => {
            try {
                const response = await fetch(
                    `/unified-chat/conversations/${conversationId}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN':
                                document
                                    .querySelector('meta[name="csrf-token"]')
                                    ?.getAttribute('content') || '',
                        },
                        credentials: 'include',
                    },
                );

                if (!response.ok) {
                    throw new Error('Failed to delete conversation');
                }

                if (currentConversation?.id === conversationId) {
                    createNewConversation();
                }

                await loadConversations();
            } catch (err) {
                setError(
                    err instanceof Error
                        ? err.message
                        : 'Failed to delete conversation',
                );
                console.error('Error deleting conversation:', err);
            }
        },
        [currentConversation, createNewConversation, loadConversations],
    );

    const toggleOpen = useCallback(() => {
        setIsOpen((prev) => !prev);
        if (isMinimized) {
            setIsMinimized(false);
        }
    }, [isMinimized]);

    const toggleMinimize = useCallback(() => {
        setIsMinimized((prev) => !prev);
    }, []);

    const clearError = useCallback(() => {
        setError(null);
    }, []);

    return {
        isOpen,
        isMinimized,
        messages,
        currentConversation,
        conversations,
        isLoading,
        isSending,
        error,
        toggleOpen,
        toggleMinimize,
        sendMessage,
        loadConversation,
        createNewConversation,
        deleteConversation,
        clearError,
    };
}
