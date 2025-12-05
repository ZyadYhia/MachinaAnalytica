import { Avatar } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import { Bot, User } from 'lucide-react';

interface ChatMessageProps {
    message: string;
    isUser: boolean;
    timestamp?: string;
}

export function ChatMessage({ message, isUser, timestamp }: ChatMessageProps) {
    return (
        <div
            className={cn(
                'flex w-full gap-3 px-4 py-4',
                isUser ? 'bg-background' : 'bg-muted/30',
            )}
        >
            <Avatar className="size-8 shrink-0">
                <div
                    className={cn(
                        'flex size-full items-center justify-center',
                        isUser
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    {isUser ? (
                        <User className="size-4" />
                    ) : (
                        <Bot className="size-4" />
                    )}
                </div>
            </Avatar>
            <div className="flex flex-1 flex-col gap-2">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-semibold">
                        {isUser ? 'You' : 'AI Agent'}
                    </span>
                    {timestamp && (
                        <span className="text-xs text-muted-foreground">
                            {new Date(timestamp).toLocaleTimeString()}
                        </span>
                    )}
                </div>
                <div className="prose prose-sm dark:prose-invert max-w-none">
                    <p className="m-0 text-sm leading-relaxed break-words whitespace-pre-wrap">
                        {message}
                    </p>
                </div>
            </div>
        </div>
    );
}
