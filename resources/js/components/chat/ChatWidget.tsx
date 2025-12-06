import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useChatWidget } from '@/hooks/useChatWidget';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    Loader2,
    MessageCircle,
    Minus,
    Plus,
    Send,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export default function ChatWidget() {
    const { auth } = usePage<SharedData>().props;
    const userId = auth?.user?.id;

    const {
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
    } = useChatWidget(userId);

    const [inputMessage, setInputMessage] = useState('');
    const [showConversations, setShowConversations] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages]);

    const handleSend = async () => {
        if (!inputMessage.trim() || isSending) return;

        const message = inputMessage;
        setInputMessage('');

        try {
            await sendMessage(message);
        } catch (err) {
            console.error('Failed to send message:', err);
        }
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    if (!auth?.user) {
        return null;
    }

    return (
        <>
            {/* Chat Bubble */}
            {!isOpen && (
                <button
                    onClick={toggleOpen}
                    className="fixed right-6 bottom-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg transition-all hover:scale-110 hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                    aria-label="Open chat"
                >
                    <MessageCircle className="h-6 w-6" />
                    {isSending && (
                        <span className="absolute -top-1 -right-1 flex h-4 w-4">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"></span>
                            <span className="relative inline-flex h-4 w-4 rounded-full bg-blue-500"></span>
                        </span>
                    )}
                </button>
            )}

            {/* Chat Window */}
            {isOpen && (
                <div
                    className={`fixed right-6 bottom-6 z-50 flex flex-col rounded-lg border border-gray-200 bg-white shadow-2xl transition-all dark:border-gray-700 dark:bg-gray-900 ${
                        isMinimized ? 'h-14 w-80' : 'h-[600px] w-96'
                    }`}
                >
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                        <div className="flex items-center gap-2">
                            <MessageCircle className="h-5 w-5 text-blue-600" />
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                {currentConversation?.title || 'AI Chat'}
                            </h3>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() =>
                                    setShowConversations(!showConversations)
                                }
                                className="rounded p-1 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                                aria-label="Show conversations"
                                title="Conversations"
                            >
                                <Plus className="h-4 w-4" />
                            </button>
                            <button
                                onClick={toggleMinimize}
                                className="rounded p-1 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                                aria-label="Minimize"
                            >
                                <Minus className="h-4 w-4" />
                            </button>
                            <button
                                onClick={toggleOpen}
                                className="rounded p-1 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                                aria-label="Close"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {!isMinimized && (
                        <>
                            {/* Conversations List */}
                            {showConversations && (
                                <div className="flex-1 overflow-hidden border-b border-gray-200 dark:border-gray-700">
                                    <ScrollArea className="h-full p-4">
                                        <div className="space-y-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full justify-start"
                                                onClick={() => {
                                                    createNewConversation();
                                                    setShowConversations(false);
                                                }}
                                            >
                                                <Plus className="mr-2 h-4 w-4" />
                                                New Conversation
                                            </Button>
                                            {conversations.map((conv) => (
                                                <div
                                                    key={conv.id}
                                                    className="flex cursor-pointer items-center gap-2 rounded p-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                                                >
                                                    <button
                                                        onClick={() => {
                                                            loadConversation(
                                                                conv.id,
                                                            );
                                                            setShowConversations(
                                                                false,
                                                            );
                                                        }}
                                                        className="flex-1 truncate text-left text-sm"
                                                    >
                                                        {conv.title}
                                                    </button>
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            deleteConversation(
                                                                conv.id,
                                                            );
                                                        }}
                                                        className="rounded p-1 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/20"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                </div>
                            )}

                            {/* Messages Area */}
                            <ScrollArea className="flex-1 p-4">
                                {isLoading && (
                                    <div className="flex h-full items-center justify-center">
                                        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
                                    </div>
                                )}

                                {!isLoading && messages.length === 0 && (
                                    <div className="flex h-full items-center justify-center text-center">
                                        <div className="text-gray-500 dark:text-gray-400">
                                            <MessageCircle className="mx-auto mb-2 h-12 w-12 opacity-50" />
                                            <p className="text-sm">
                                                Start a conversation
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-4">
                                    {messages.map((message, index) => (
                                        <div
                                            key={index}
                                            className={`flex ${
                                                message.role === 'user'
                                                    ? 'justify-end'
                                                    : 'justify-start'
                                            }`}
                                        >
                                            <div
                                                className={`max-w-[80%] rounded-lg px-4 py-2 ${
                                                    message.role === 'user'
                                                        ? 'bg-blue-600 text-white'
                                                        : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100'
                                                }`}
                                            >
                                                <p className="text-sm whitespace-pre-wrap">
                                                    {message.content}
                                                </p>
                                                {message.created_at && (
                                                    <p className="mt-1 text-xs opacity-70">
                                                        {new Date(
                                                            message.created_at,
                                                        ).toLocaleTimeString()}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}

                                    {isSending && (
                                        <div className="flex justify-start">
                                            <div className="rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-800">
                                                <Loader2 className="h-4 w-4 animate-spin text-gray-400" />
                                            </div>
                                        </div>
                                    )}

                                    <div ref={messagesEndRef} />
                                </div>
                            </ScrollArea>

                            {/* Error Message */}
                            {error && (
                                <div className="border-t border-red-200 bg-red-50 px-4 py-2 dark:border-red-800 dark:bg-red-900/20">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="flex-1 text-sm text-red-800 dark:text-red-200">
                                            {error}
                                        </p>
                                        <button
                                            onClick={clearError}
                                            className="text-red-800 hover:text-red-900 dark:text-red-200 dark:hover:text-red-100"
                                        >
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Input Area */}
                            <div className="border-t border-gray-200 p-4 dark:border-gray-700">
                                <div className="flex items-center gap-2">
                                    <Input
                                        value={inputMessage}
                                        onChange={(e) =>
                                            setInputMessage(e.target.value)
                                        }
                                        onKeyPress={handleKeyPress}
                                        placeholder="Type a message..."
                                        disabled={isSending}
                                        className="flex-1"
                                    />
                                    <Button
                                        onClick={handleSend}
                                        disabled={
                                            !inputMessage.trim() || isSending
                                        }
                                        size="icon"
                                    >
                                        {isSending ? (
                                            <Loader2 className="h-4 w-4 animate-spin" />
                                        ) : (
                                            <Send className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            )}
        </>
    );
}
