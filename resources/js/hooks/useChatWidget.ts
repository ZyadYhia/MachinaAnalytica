import { chatRoutes, Wayfinder } from '@/lib/wayfinder';
import { useCallback, useEffect, useState } from 'react';

export interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
    tool_calls?: Record<string, unknown>[];
    metadata?: Record<string, unknown>;
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
    // Initialize state from localStorage if available
    const [isOpen, setIsOpen] = useState(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('chat_is_open');
            return saved ? JSON.parse(saved) : false;
        }
        return false;
    });

    const [isMinimized, setIsMinimized] = useState(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('chat_is_minimized');
            return saved ? JSON.parse(saved) : false;
        }
        return false;
    });

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [currentConversation, setCurrentConversation] =
        useState<Conversation | null>(null);
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Persist state changes to localStorage
    useEffect(() => {
        if (typeof window !== 'undefined') {
            localStorage.setItem('chat_is_open', JSON.stringify(isOpen));
            localStorage.setItem(
                'chat_is_minimized',
                JSON.stringify(isMinimized),
            );
        }
    }, [isOpen, isMinimized]);

    // Define loadConversations callback first
    const loadConversations = useCallback(async () => {
        try {
            const data = await Wayfinder.get<{ data: Conversation[] }>(
                chatRoutes.conversations,
            );
            setConversations(data.data || []);
        } catch (err) {
            console.error('Error loading conversations:', err);
            setError(
                err instanceof Error
                    ? err.message
                    : 'Failed to load conversations',
            );
        }
    }, []);

    // Load conversations on mount
    useEffect(() => {
        loadConversations();
    }, [loadConversations]);

    // Restore active conversation on mount
    useEffect(() => {
        if (!currentConversation && typeof window !== 'undefined' && userId) {
            const savedId = localStorage.getItem('chat_active_conversation_id');
            if (savedId) {
                const conversationId = parseInt(savedId, 10);
                if (!isNaN(conversationId)) {
                    loadConversation(conversationId).catch(() => {
                        // If the saved conversation no longer exists, clear it from storage
                        console.log(
                            'Saved conversation not found, clearing from storage',
                        );
                        localStorage.removeItem('chat_active_conversation_id');
                    });
                }
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [userId]); // Only depend on userId to ensure we have context

    // Set up WebSocket listener for async chat responses
    useEffect(() => {
        if (!userId || !currentConversation?.id) {
            return;
        }

        const channelName = `jan-chat.${userId}.${currentConversation.id}`;
        console.log('Setting up WebSocket listener for:', channelName);

        // Subscribe to the private channel
        const channel = (
            window as unknown as {
                Echo?: { private: (name: string) => unknown };
            }
        ).Echo?.private(channelName) as
            | {
                  listen: (
                      event: string,
                      callback: (data: unknown) => void,
                  ) => void;
              }
            | undefined;

        if (channel) {
            // Listen for chat completion
            channel.listen('.jan.chat.completed', (event: unknown) => {
                console.log('Chat completed event received:', event);
                const eventData = event as {
                    response?: {
                        content?: string;
                        metadata?: Record<string, unknown>;
                    };
                    timestamp?: string;
                };

                const assistantMessage: ChatMessage = {
                    role: 'assistant',
                    content: eventData.response?.content || '',
                    metadata: eventData.response?.metadata,
                    created_at: eventData.timestamp,
                };

                console.log('Adding assistant message:', assistantMessage);
                setMessages((prev) => {
                    const newMessages = [...prev, assistantMessage];
                    return newMessages;
                });
                setIsSending(false);
                loadConversations();
            });

            // Listen for chat failures
            channel.listen('.jan.chat.failed', (event: unknown) => {
                console.error('Chat failed event received:', event);
                const errorData = event as { error?: string };
                setError(errorData.error || 'Chat request failed');
                setIsSending(false);
                // Remove the optimistic user message on failure
                setMessages((prev) => prev.slice(0, -1));
            });

            console.log(
                'WebSocket listeners attached to channel:',
                channelName,
            );
        } else {
            console.error('Echo not available for WebSocket connection');
        }

        // Cleanup: leave the channel when component unmounts or conversation changes
        // Listen for conversation updates (e.g. title change)
        if (channel) {
            channel.listen(
                '.jan.chat.conversation_updated',
                (event: unknown) => {
                    console.log('Conversation updated event received:', event);
                    const updateData = event as { conversation?: Conversation };

                    if (
                        updateData.conversation &&
                        currentConversation?.id === updateData.conversation.id
                    ) {
                        setCurrentConversation((prev) =>
                            prev
                                ? { ...prev, ...updateData.conversation }
                                : (updateData.conversation ?? null),
                        );
                    }

                    loadConversations();
                },
            );
        }

        return () => {
            if (channel) {
                console.log('Leaving WebSocket channel:', channelName);
                (
                    window as unknown as {
                        Echo?: { leave: (name: string) => void };
                    }
                ).Echo?.leave(channelName);
            }
        };
    }, [userId, currentConversation?.id, loadConversations]);

    const loadConversation = useCallback(async (conversationId: number) => {
        setIsLoading(true);
        setError(null);

        try {
            const data = await Wayfinder.get<{ conversation: Conversation }>(
                chatRoutes.conversation(conversationId),
            );
            setCurrentConversation(data.conversation);

            // Persist active conversation ID
            if (typeof window !== 'undefined') {
                localStorage.setItem(
                    'chat_active_conversation_id',
                    data.conversation.id.toString(),
                );
            }

            const loadedMessages = data.conversation.messages || [];
            console.log('Loading conversation messages:', loadedMessages);
            setMessages(loadedMessages);

            // Check for pending response
            // If the last message is from the user and is recent (< 2 minutes), assume AI is processing
            if (loadedMessages.length > 0) {
                const lastMessage = loadedMessages[loadedMessages.length - 1];
                if (lastMessage.role === 'user' && lastMessage.created_at) {
                    const messageTime = new Date(
                        lastMessage.created_at,
                    ).getTime();
                    const now = new Date().getTime();
                    const twoMinutesInMs = 2 * 60 * 1000;

                    if (now - messageTime < twoMinutesInMs) {
                        console.log(
                            'Detected pending response based on recent user message',
                        );
                        setIsSending(true);
                    }
                }
            }
        } catch (err) {
            // Don't show error for 404 (conversation not found) - this is normal when
            // restoring a deleted conversation from localStorage
            const is404 =
                err instanceof Error &&
                (err.message.includes('No query results') ||
                    err.message.includes('404') ||
                    err.message.includes('not found'));

            if (!is404) {
                setError(
                    err instanceof Error ? err.message : 'An error occurred',
                );
            }
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
            console.log('Adding user message:', userMessage);
            setMessages((prev) => {
                const newMessages = [...prev, userMessage];
                return newMessages;
            });

            await Wayfinder.postJson<{
                mode?: string;
                conversation_id?: string;
                provider?: string;
                response?: string;
                metadata?: Record<string, unknown>;
            }>(
                chatRoutes.sendMessage,
                {
                    message: content,
                    conversation_id: currentConversation?.id,
                },
                {
                    onSuccess: (data) => {
                        console.log('Chat response received:', data);

                        if (data.mode === 'async') {
                            // Async mode - response will come via WebSocket
                            if (data.conversation_id && !currentConversation) {
                                console.log(
                                    'Setting new conversation ID:',
                                    data.conversation_id,
                                );
                                setCurrentConversation({
                                    id: parseInt(data.conversation_id, 10),
                                    title: 'New Conversation',
                                    provider: data.provider || 'unknown',
                                });

                                // Persist new conversation ID
                                if (typeof window !== 'undefined') {
                                    localStorage.setItem(
                                        'chat_active_conversation_id',
                                        data.conversation_id,
                                    );
                                }
                            }
                        } else {
                            // Sync mode - add assistant response immediately
                            const assistantMessage: ChatMessage = {
                                role: 'assistant',
                                content: data.response || '',
                                metadata: data.metadata,
                                created_at: new Date().toISOString(),
                            };
                            console.log(
                                'Sync mode - adding assistant message:',
                                assistantMessage,
                            );
                            setMessages((prev) => [...prev, assistantMessage]);

                            if (data.conversation_id && !currentConversation) {
                                const newConv = {
                                    id: parseInt(data.conversation_id, 10),
                                    title: 'New Conversation',
                                    provider: data.provider || 'unknown',
                                };
                                setCurrentConversation(newConv);

                                if (typeof window !== 'undefined') {
                                    localStorage.setItem(
                                        'chat_active_conversation_id',
                                        data.conversation_id,
                                    );
                                }
                            }

                            setIsSending(false);
                            loadConversations();
                        }
                    },
                    onError: (errors: unknown) => {
                        console.error('Chat error:', errors);
                        const errorObj = errors as
                            | { message?: string }
                            | string
                            | Record<string, string[]>;
                        const errorMessage =
                            typeof errorObj === 'string'
                                ? errorObj
                                : (errorObj as { message?: string }).message ||
                                  Object.values(
                                      errorObj as Record<string, string[]>,
                                  )
                                      .flat()
                                      .join(', ') ||
                                  'Failed to send message';
                        setError(errorMessage);
                        setIsSending(false);
                        setMessages((prev) => prev.slice(0, -1));
                    },
                },
            );
        },
        [currentConversation, loadConversations],
    );

    const createNewConversation = useCallback(() => {
        setCurrentConversation(null);
        setMessages([]);
        setError(null);
        if (typeof window !== 'undefined') {
            localStorage.removeItem('chat_active_conversation_id');
        }
    }, []);

    const deleteConversation = useCallback(
        async (conversationId: number) => {
            await Wayfinder.delete(
                chatRoutes.deleteConversation(conversationId),
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        if (currentConversation?.id === conversationId) {
                            createNewConversation();
                        }
                        loadConversations();
                    },
                    onError: (errors: unknown) => {
                        const errorMessage =
                            typeof errors === 'string'
                                ? errors
                                : 'Failed to delete conversation';
                        setError(errorMessage);
                        console.error('Error deleting conversation:', errors);
                    },
                },
            );
        },
        [currentConversation, createNewConversation, loadConversations],
    );

    const toggleOpen = useCallback(() => {
        setIsOpen((prev: boolean) => !prev);
        if (isMinimized) {
            setIsMinimized(false);
        }
    }, [isMinimized]);

    const toggleMinimize = useCallback(() => {
        setIsMinimized((prev: boolean) => !prev);
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
