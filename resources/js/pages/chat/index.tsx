import { ChatContainer, type Message } from '@/components/chat-container';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface Workspace {
    id?: number;
    name: string;
    slug: string;
}

interface ChatIndexProps {
    workspaces: Workspace[];
    defaultWorkspace: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chat',
        href: '/chat',
    },
];

export default function ChatIndex({
    workspaces = [],
    defaultWorkspace,
}: ChatIndexProps) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedWorkspace, setSelectedWorkspace] = useState<string>(
        defaultWorkspace || '',
    );

    const handleSendMessage = async (content: string) => {
        const userMessage: Message = {
            id: Date.now().toString(),
            content,
            isUser: true,
            timestamp: new Date().toISOString(),
        };

        setMessages((prev) => [...prev, userMessage]);
        setIsLoading(true);

        try {
            // Get CSRF token
            const csrfToken =
                (window as { csrfToken?: string }).csrfToken ||
                document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content ||
                '';

            const response = await fetch('/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    message: content,
                    workspace: selectedWorkspace,
                    mode: 'chat',
                }),
            });

            const data = await response.json();

            // Check if the response contains an error
            if (!response.ok || data.error) {
                throw new Error(
                    data.message || data.error || 'Failed to send message',
                );
            }

            const aiMessage: Message = {
                id: (Date.now() + 1).toString(),
                content: data.message,
                isUser: false,
                timestamp: data.timestamp,
            };

            setMessages((prev) => [...prev, aiMessage]);
        } catch (error) {
            console.error('Error sending message:', error);

            const errorMessage: Message = {
                id: (Date.now() + 1).toString(),
                content:
                    error instanceof Error
                        ? error.message
                        : 'Sorry, I encountered an error. Please try again later.',
                isUser: false,
                timestamp: new Date().toISOString(),
            };
            setMessages((prev) => [...prev, errorMessage]);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chat" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden p-4">
                {workspaces.length > 0 && (
                    <div className="flex items-center gap-3 rounded-lg border border-sidebar-border bg-card p-3">
                        <label
                            htmlFor="workspace-select"
                            className="text-sm font-medium text-muted-foreground"
                        >
                            Workspace:
                        </label>
                        <select
                            id="workspace-select"
                            value={selectedWorkspace}
                            onChange={(e) =>
                                setSelectedWorkspace(e.target.value)
                            }
                            className="rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {workspaces.map((workspace) => (
                                <option
                                    key={workspace.slug}
                                    value={workspace.slug}
                                >
                                    {workspace.name}
                                </option>
                            ))}
                        </select>
                        <span className="text-xs text-muted-foreground">
                            Powered by AnythingLLM
                        </span>
                    </div>
                )}
                {workspaces.length === 0 && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                        <p className="font-semibold">No workspaces available</p>
                        <p className="mt-1 text-xs">
                            Please ensure AnythingLLM is running and has at
                            least one workspace configured.
                        </p>
                    </div>
                )}
                <ChatContainer
                    messages={messages}
                    onSendMessage={handleSendMessage}
                    isLoading={isLoading}
                    disabled={workspaces.length === 0}
                />
            </div>
        </AppLayout>
    );
}
