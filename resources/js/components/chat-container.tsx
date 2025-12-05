import { ChatInput } from '@/components/chat-input';
import { ChatMessage } from '@/components/chat-message';
import { Spinner } from '@/components/ui/spinner';
import { useEffect, useRef } from 'react';

export interface Message {
    id: string;
    content: string;
    isUser: boolean;
    timestamp: string;
}

interface ChatContainerProps {
    messages: Message[];
    onSendMessage: (message: string) => void;
    isLoading?: boolean;
    disabled?: boolean;
}

export function ChatContainer({
    messages,
    onSendMessage,
    isLoading = false,
    disabled = false,
}: ChatContainerProps) {
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    return (
        <div className="flex h-full flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            <div className="flex items-center gap-3 border-b border-sidebar-border bg-muted/30 px-4 py-3">
                <div className="flex size-8 items-center justify-center rounded-full bg-primary/10">
                    <span className="text-sm font-semibold text-primary">
                        AI
                    </span>
                </div>
                <div className="flex-1">
                    <h2 className="text-sm font-semibold">AI Agent Chat</h2>
                    <p className="text-xs text-muted-foreground">
                        Ask me anything
                    </p>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto">
                {messages.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                        <div className="flex size-16 items-center justify-center rounded-full bg-muted">
                            <span className="text-2xl font-bold text-muted-foreground">
                                AI
                            </span>
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-lg font-semibold">
                                Start a conversation
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Send a message to begin chatting with the AI
                                agent
                            </p>
                        </div>
                    </div>
                ) : (
                    <>
                        {messages.map((message) => (
                            <ChatMessage
                                key={message.id}
                                message={message.content}
                                isUser={message.isUser}
                                timestamp={message.timestamp}
                            />
                        ))}
                        {isLoading && (
                            <div className="flex gap-3 px-4 py-4">
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                    <Spinner className="size-4" />
                                </div>
                                <div className="flex flex-1 items-center">
                                    <span className="text-sm text-muted-foreground">
                                        AI is thinking...
                                    </span>
                                </div>
                            </div>
                        )}
                        <div ref={messagesEndRef} />
                    </>
                )}
            </div>

            <ChatInput
                onSend={onSendMessage}
                disabled={isLoading || disabled}
            />
        </div>
    );
}
