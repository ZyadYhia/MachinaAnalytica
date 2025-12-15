import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface JanChatProgress {
    status:
        | 'queued'
        | 'tools_executing'
        | 'tools_completed'
        | 'jan_api_responding'
        | 'completed'
        | 'failed';
    message?: string;
    context?: Record<string, unknown>;
    tool_calls?: Array<{ function: { name: string } }>;
    iteration?: number;
    response?: {
        data: {
            choices: Array<{
                message: { content: string; reasoning_content?: string };
                finish_reason?: string;
            }>;
            model?: string;
            usage?: Record<string, unknown>;
            timings?: Record<string, unknown>;
        };
    };
    metrics?: {
        iterations: number;
        duration_seconds: number;
        message_count: number;
    };
    error?: string;
}

interface UseJanChatOptions {
    conversationId: string;
    onError?: (error: string) => void;
    onCompleted?: (data: JanChatProgress) => void;
    useAsync?: boolean;
}

export function useJanChat({
    conversationId,
    onError,
    onCompleted,
    useAsync = true,
}: UseJanChatOptions) {
    const { auth } = usePage<propsData>().props as {
        auth: { user: { id: number } };
    };
    const [progressStatus, setProgressStatus] = useState<string>('');
    const [currentIteration, setCurrentIteration] = useState<number>(0);
    const channelRef = useRef<any>(null);
    const onErrorRef = useRef(onError);
    const onCompletedRef = useRef(onCompleted);

    // Update refs when callbacks change
    useEffect(() => {
        onErrorRef.current = onError;
        onCompletedRef.current = onCompleted;
    }, [onError, onCompleted]);

    const setupEchoChannel = useCallback(() => {
        if (!window.Echo || !auth?.user?.id || !useAsync) {
            return;
        }

        const channelName = `jan-chat.${auth.user.id}.${conversationId}`;

        try {
            // Leave existing channel if any
            if (channelRef.current) {
                console.log(
                    'Leaving existing Echo channel:',
                    channelRef.current.name,
                );
                window.Echo.leave(channelRef.current.name);
                channelRef.current = null;
            }

            console.log('Setting up Echo channel:', channelName);

            // Subscribe to private channel
            const channel = window.Echo.private(channelName);

            channel
                .listen('.jan.chat.queued', (data: JanChatProgress) => {
                    console.log('Event received: jan.chat.queued');
                    setProgressStatus('Queued for processing...');
                    setCurrentIteration(0);
                })
                .listen('.jan.api.responding', (data: JanChatProgress) => {
                    console.log('Event received: jan.api.responding');
                    setProgressStatus(
                        `Waiting for AI response (iteration ${data.iteration})...`,
                    );
                    setCurrentIteration(data.iteration || 0);
                })
                .listen('.jan.tools.executing', (data: JanChatProgress) => {
                    console.log('Event received: jan.tools.executing');
                    const toolNames =
                        data.tool_calls
                            ?.map((tc) => tc.function.name)
                            .join(', ') || 'tools';
                    setProgressStatus(
                        `Executing tools: ${toolNames} (iteration ${data.iteration})...`,
                    );
                    setCurrentIteration(data.iteration || 0);
                })
                .listen('.jan.tools.completed', (data: JanChatProgress) => {
                    console.log('Event received: jan.tools.completed');
                    setProgressStatus(
                        `Tools completed (iteration ${data.iteration})...`,
                    );
                })
                .listen('.jan.chat.completed', (data: JanChatProgress) => {
                    console.log('Event received: jan.chat.completed', {
                        conversationId,
                        channelName,
                    });
                    setProgressStatus('');
                    setCurrentIteration(0);
                    if (onCompletedRef.current) {
                        onCompletedRef.current(data);
                    }
                })
                .listen('.jan.chat.failed', (data: JanChatProgress) => {
                    console.log('Event received: jan.chat.failed');
                    setProgressStatus('');
                    setCurrentIteration(0);
                    if (onErrorRef.current) {
                        onErrorRef.current(
                            data.error ||
                                'An unknown error occurred during processing',
                        );
                    }
                });

            channelRef.current = channel;
            console.log('Echo channel setup complete:', channelName);
        } catch (error) {
            console.error('Failed to setup Echo channel:', error);
        }
    }, [auth?.user?.id, conversationId, useAsync]);

    useEffect(() => {
        setupEchoChannel();

        return () => {
            if (channelRef.current) {
                window.Echo.leave(channelRef.current.name);
                channelRef.current = null;
            }
        };
    }, [auth?.user?.id, conversationId, useAsync]);

    return {
        progressStatus,
        currentIteration,
        isProcessing: progressStatus.length > 0,
    };
}
