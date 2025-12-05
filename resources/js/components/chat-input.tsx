import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Send } from 'lucide-react';
import { type FormEvent, type KeyboardEvent, useRef } from 'react';

interface ChatInputProps {
    onSend: (message: string) => void;
    disabled?: boolean;
    placeholder?: string;
}

export function ChatInput({
    onSend,
    disabled = false,
    placeholder = 'Type your message...',
}: ChatInputProps) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const message = textareaRef.current?.value.trim();
        if (message && !disabled) {
            onSend(message);
            if (textareaRef.current) {
                textareaRef.current.value = '';
                textareaRef.current.style.height = 'auto';
            }
        }
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    const handleInput = () => {
        if (textareaRef.current) {
            textareaRef.current.style.height = 'auto';
            textareaRef.current.style.height = `${textareaRef.current.scrollHeight}px`;
        }
    };

    return (
        <form
            onSubmit={handleSubmit}
            className="flex items-end gap-2 border-t border-sidebar-border bg-background p-4"
        >
            <textarea
                ref={textareaRef}
                placeholder={placeholder}
                disabled={disabled}
                onKeyDown={handleKeyDown}
                onInput={handleInput}
                rows={1}
                className={cn(
                    'max-h-[200px] min-h-[40px] flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm',
                    'placeholder:text-muted-foreground',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                )}
            />
            <Button
                type="submit"
                size="icon"
                disabled={disabled}
                className="shrink-0"
            >
                <Send className="size-4" />
                <span className="sr-only">Send message</span>
            </Button>
        </form>
    );
}
