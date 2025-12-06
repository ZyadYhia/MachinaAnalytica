import { Avatar } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';
import 'highlight.js/styles/github-dark.css';
import { Bot, Settings, User } from 'lucide-react';
import { useMemo } from 'react';
import ReactMarkdown from 'react-markdown';
import rehypeHighlight from 'rehype-highlight';
import remarkGfm from 'remark-gfm';

interface ChatMessageProps {
    message: string;
    isUser: boolean;
    timestamp?: string;
    isSystem?: boolean;
}

export function ChatMessage({
    message,
    isUser,
    timestamp,
    isSystem,
}: ChatMessageProps) {
    // Process message to remove any image tags that might cause broken images
    const processedMessage = useMemo(() => {
        return message.replace(/!\[.*?\]\(.*?\)/g, '').trim();
    }, [message]);

    if (isSystem) {
        return (
            <div className="flex w-full gap-3 border-l-4 border-primary/50 bg-primary/5 px-4 py-3">
                <Avatar className="size-8 shrink-0">
                    <div className="flex size-full items-center justify-center bg-primary/20 text-primary">
                        <Settings className="size-4" />
                    </div>
                </Avatar>
                <div className="flex flex-1 flex-col gap-2">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold text-primary">
                            System
                        </span>
                        {timestamp && (
                            <span className="text-xs text-muted-foreground">
                                {new Date(timestamp).toLocaleTimeString()}
                            </span>
                        )}
                    </div>
                    <div className="prose prose-sm dark:prose-invert max-w-none">
                        <ReactMarkdown
                            remarkPlugins={[remarkGfm]}
                            rehypePlugins={[rehypeHighlight]}
                        >
                            {message}
                        </ReactMarkdown>
                    </div>
                </div>
            </div>
        );
    }

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
                <div className="prose prose-sm dark:prose-invert prose-headings:font-bold prose-headings:tracking-tight prose-headings:text-foreground prose-h1:text-2xl prose-h1:mb-5 prose-h1:mt-7 prose-h2:text-xl prose-h2:mb-5 prose-h2:mt-7 prose-h2:border-b-2 prose-h2:border-primary/30 prose-h2:pb-3 prose-h3:text-lg prose-h3:mb-4 prose-h3:mt-6 prose-h3:text-primary prose-h3:font-extrabold prose-h4:text-base prose-h4:mb-3 prose-h4:mt-5 prose-p:leading-7 prose-p:my-5 prose-p:text-foreground/90 prose-a:text-primary prose-a:font-semibold prose-a:no-underline hover:prose-a:underline prose-a:transition-all prose-strong:font-extrabold prose-strong:text-foreground dark:prose-strong:text-foreground prose-em:text-muted-foreground prose-em:italic prose-code:rounded-md prose-code:bg-primary/15 prose-code:px-2.5 prose-code:py-1 prose-code:text-[0.9em] prose-code:font-mono prose-code:text-primary prose-code:border prose-code:border-primary/30 prose-code:font-semibold prose-code:before:content-[''] prose-code:after:content-[''] prose-pre:!bg-slate-950 dark:prose-pre:!bg-slate-900 prose-pre:border prose-pre:border-primary/20 prose-pre:rounded-xl prose-pre:shadow-xl prose-pre:p-5 prose-pre:my-7 prose-blockquote:border-l-[5px] prose-blockquote:border-l-primary prose-blockquote:bg-primary/8 prose-blockquote:pl-5 prose-blockquote:pr-5 prose-blockquote:py-4 prose-blockquote:rounded-r-lg prose-blockquote:italic prose-blockquote:text-muted-foreground prose-blockquote:my-7 prose-blockquote:shadow-sm prose-table:border-separate prose-table:border-spacing-0 prose-table:w-auto prose-table:my-7 prose-table:rounded-xl prose-table:overflow-hidden prose-table:shadow-lg prose-table:border-2 prose-table:border-border/60 prose-thead:bg-gradient-to-r prose-thead:from-primary/25 prose-thead:to-primary/20 prose-th:border-r prose-th:border-r-border/50 prose-th:last:border-r-0 prose-th:border-b-2 prose-th:border-b-primary/40 prose-th:bg-transparent prose-th:px-6 prose-th:py-4 prose-th:text-center prose-th:font-black prose-th:text-foreground prose-th:text-[0.8rem] prose-th:uppercase prose-th:tracking-wider prose-th:first:rounded-tl-xl prose-th:last:rounded-tr-xl prose-td:border-r prose-td:border-r-border/30 prose-td:last:border-r-0 prose-td:border-b prose-td:border-b-border/30 prose-td:px-6 prose-td:py-4 prose-td:text-center prose-td:text-foreground/90 prose-td:text-[0.85rem] prose-td:font-medium prose-tbody:divide-y prose-tbody:divide-border/30 prose-tr:transition-all prose-tr:duration-200 prose-tr:even:bg-muted/30 hover:prose-tr:bg-primary/12 hover:prose-tr:scale-[1.001] prose-tr:last:prose-td:first:rounded-bl-xl prose-tr:last:prose-td:last:rounded-br-xl prose-hr:border-primary/20 prose-hr:my-8 prose-hr:border-t-2 prose-ul:my-5 prose-ul:pl-6 prose-ul:space-y-2.5 prose-ol:my-5 prose-ol:pl-6 prose-ol:space-y-2.5 prose-li:my-2.5 prose-li:text-foreground/90 prose-li:leading-7 prose-li:marker:text-primary prose-li:marker:font-extrabold prose-li:marker:text-base prose-img:rounded-xl prose-img:shadow-lg prose-img:my-7 prose-img:border prose-img:border-border/30 max-w-none">
                    {isUser ? (
                        <p className="m-0 text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground">
                            {message}
                        </p>
                    ) : (
                        <>
                            <ReactMarkdown
                                remarkPlugins={[remarkGfm]}
                                rehypePlugins={[rehypeHighlight]}
                                components={{
                                    h1: ({ node, ...props }) => (
                                        <h1
                                            className="flex items-center gap-2"
                                            {...props}
                                        />
                                    ),
                                    h2: ({ node, ...props }) => (
                                        <h2
                                            className="flex items-center gap-2"
                                            {...props}
                                        />
                                    ),
                                    h3: ({ node, ...props }) => (
                                        <h3
                                            className="flex items-center gap-2"
                                            {...props}
                                        />
                                    ),
                                    h4: ({ node, ...props }) => (
                                        <h4
                                            className="flex items-center gap-2 text-primary"
                                            {...props}
                                        />
                                    ),
                                    p: ({ node, ...props }) => (
                                        <p className="leading-7" {...props} />
                                    ),
                                    table: ({ node, ...props }) => (
                                        <div className="my-7 inline-block overflow-x-auto rounded-xl border-2 border-border/60 bg-card shadow-xl">
                                            <table {...props} />
                                        </div>
                                    ),
                                    thead: ({ node, ...props }) => (
                                        <thead
                                            className="sticky top-0 bg-gradient-to-r from-primary/25 to-primary/20"
                                            {...props}
                                        />
                                    ),
                                    tbody: ({ node, ...props }) => (
                                        <tbody
                                            className="divide-y divide-border/30 bg-background"
                                            {...props}
                                        />
                                    ),
                                    tr: ({ node, ...props }) => (
                                        <tr
                                            className="group transition-all duration-200 hover:bg-primary/12 hover:shadow-sm"
                                            {...props}
                                        />
                                    ),
                                    th: ({ node, ...props }) => (
                                        <th
                                            className="border-r border-b-2 border-r-border/50 border-b-primary/40 px-6 py-4 text-center text-[0.8rem] font-black tracking-wider whitespace-nowrap text-foreground uppercase last:border-r-0"
                                            {...props}
                                        />
                                    ),
                                    td: ({ node, ...props }) => (
                                        <td
                                            className="border-r border-b border-r-border/30 border-b-border/30 px-6 py-4 text-center text-[0.85rem] font-medium whitespace-nowrap text-foreground/90 transition-colors group-hover:text-foreground last:border-r-0"
                                            {...props}
                                        />
                                    ),
                                    code: ({
                                        node,
                                        className,
                                        children,
                                        ...props
                                    }: any) => {
                                        const inline = !className;
                                        if (inline) {
                                            return (
                                                <code
                                                    className={className}
                                                    {...props}
                                                >
                                                    {children}
                                                </code>
                                            );
                                        }
                                        return (
                                            <div className="group relative">
                                                <code
                                                    className={className}
                                                    {...props}
                                                >
                                                    {children}
                                                </code>
                                            </div>
                                        );
                                    },
                                    ul: ({ node, ...props }) => (
                                        <ul
                                            className="list-disc space-y-2.5"
                                            {...props}
                                        />
                                    ),
                                    ol: ({ node, ...props }) => (
                                        <ol
                                            className="list-decimal space-y-2.5"
                                            {...props}
                                        />
                                    ),
                                    li: ({ node, ...props }) => (
                                        <li
                                            className="pl-1 leading-7"
                                            {...props}
                                        />
                                    ),
                                    strong: ({ node, ...props }) => (
                                        <strong
                                            className="font-extrabold text-foreground"
                                            {...props}
                                        />
                                    ),
                                    em: ({ node, ...props }) => (
                                        <em
                                            className="text-muted-foreground italic"
                                            {...props}
                                        />
                                    ),
                                    blockquote: ({ node, ...props }) => (
                                        <blockquote
                                            className="my-7 rounded-r-lg border-l-[5px] border-primary bg-primary/8 py-4 pr-5 pl-5 text-muted-foreground italic shadow-sm"
                                            {...props}
                                        />
                                    ),
                                    hr: ({ node, ...props }) => (
                                        <hr
                                            className="my-8 border-t-2 border-primary/20"
                                            {...props}
                                        />
                                    ),
                                }}
                            >
                                {processedMessage}
                            </ReactMarkdown>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
