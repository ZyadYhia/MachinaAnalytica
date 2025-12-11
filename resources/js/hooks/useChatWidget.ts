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
                    // We need to disable the exhaustive-deps rule here because we only want to run this once on mount
                    // eslint-disable-next-line @typescript-eslint/no-use-before-define
                    loadConversation(conversationId);
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
        const channel = (window as any).Echo?.private(channelName);

        if (channel) {
            // Listen for chat completion
            channel.listen('.jan.chat.completed', (event: any) => {
                console.log('Chat completed event received:', event);

                const assistantMessage: ChatMessage = {
                    role: 'assistant',
                    content: event.response?.content || '',
                    metadata: event.response?.metadata,
                    created_at: event.timestamp,
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
            channel.listen('.jan.chat.failed', (event: any) => {
                console.error('Chat failed event received:', event);
                setError(event.error || 'Chat request failed');
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
        channel.listen('.jan.chat.conversation_updated', (event: any) => {
            console.log('Conversation updated event received:', event);

            if (
                event.conversation &&
                currentConversation?.id === event.conversation.id
            ) {
                setCurrentConversation((prev) =>
                    prev
                        ? { ...prev, ...event.conversation }
                        : event.conversation,
                );
            }

            loadConversations();
        });

        return () => {
            if (channel) {
                console.log('Leaving WebSocket channel:', channelName);
                (window as any).Echo?.leave(channelName);
            }
        };
    }, [userId, currentConversation?.id, loadConversations]);

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
            console.log('Adding user message:', userMessage);
            setMessages((prev) => {
                const newMessages = [...prev, userMessage];
                return newMessages;
            });

            try {
                // Get CSRF token
                const csrfToken =
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content || '';

                const response = await fetch('/unified-chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        message: content,
                        conversation_id: currentConversation?.id,
                    }),
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(
                        errorData.error || 'Failed to send message',
                    );
                }

                const data = await response.json();
                console.log('Chat response received:', data);

                if (data.mode === 'async') {
                    // Async mode - response will come via WebSocket
                    // Update conversation ID if new conversation was created

                    if (data.conversation_id && !currentConversation) {
                        console.log(
                            'Setting new conversation ID:',
                            data.conversation_id,
                        );
                        setCurrentConversation((prev) => {
                            const newConv = {
                                id: data.conversation_id,
                                title: 'New Conversation',
                                provider: data.provider || 'unknown',
                            };

                            // Persist new conversation ID
                            if (typeof window !== 'undefined') {
                                localStorage.setItem(
                                    'chat_active_conversation_id',
                                    newConv.id.toString(),
                                );
                            }

                            return newConv;
                        });
                    }

                    // Keep isSending true for async - will be set false when WebSocket event arrives
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
                    setMessages((prev) => {
                        const newMessages = [...prev, assistantMessage];
                        return newMessages;
                    });

                    // Update conversation ID if new conversation was created
                    if (data.conversation_id && !currentConversation) {
                        const newConv = {
                            id: data.conversation_id,
                            title: 'New Conversation',
                            provider: data.provider || 'unknown',
                        };
                        setCurrentConversation(newConv);

                        // Persist new conversation ID
                        if (typeof window !== 'undefined') {
                            localStorage.setItem(
                                'chat_active_conversation_id',
                                newConv.id.toString(),
                            );
                        }
                    }

                    setIsSending(false);
                    loadConversations();
                }
            } catch (err) {
                const errorMessage =
                    err instanceof Error ? err.message : 'An error occurred';
                setError(errorMessage);
                setIsSending(false);
                // Remove optimistic user message on error
                setMessages((prev) => prev.slice(0, -1));
                throw err;
            }
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
