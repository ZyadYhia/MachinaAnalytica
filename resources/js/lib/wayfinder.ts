/**
 * Wayfinder - A utility for type-safe route navigation and API calls
 * Provides a centralized way to handle routes in the application
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import type { Page, PageProps } from '@inertiajs/core';
import { router } from '@inertiajs/react';

/**
 * Chat routes configuration
 */
export const chatRoutes = {
    sendMessage: '/api/unified-chat',
    conversations: '/api/unified-chat/conversations',
    conversation: (id: number) => `/api/unified-chat/conversations/${id}`,
    deleteConversation: (id: number) => `/api/unified-chat/conversations/${id}`,
} as const;

/**
 * Settings routes configuration
 */
export const settingsRoutes = {
    integrations: '/settings/integrations',
    integrationsShow: '/settings/integrations/show',
    password: '/settings/password',
    profile: '/settings/profile',
} as const;

/**
 * API routes configuration
 */
export const apiRoutes = {
    health: '/api/health',
    models: '/api/models',
} as const;

/**
 * All routes combined
 */
export const routes = {
    chat: chatRoutes,
    settings: settingsRoutes,
    api: apiRoutes,
} as const;

/**
 * Wayfinder class - Provides methods for navigation and API calls
 */
export class Wayfinder {
    /**
     * Navigate to a route using Inertia
     */
    static visit(
        url: string,
        options?: {
            method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
            data?: Record<string, unknown>;
            preserveScroll?: boolean;
            preserveState?: boolean;
            only?: string[];
            onSuccess?: (page: Page<PageProps>) => void;
            onError?: (errors: unknown) => void;
        },
    ): void {
        const method = options?.method || 'get';

        if (method === 'get') {
            router.get(url, {
                preserveScroll: options?.preserveScroll,
                preserveState: options?.preserveState,
                only: options?.only,
                onSuccess: options?.onSuccess as any,
                onError: options?.onError as any,
            });
        } else {
            router[method](url, (options?.data || {}) as any, {
                preserveScroll: options?.preserveScroll,
                preserveState: options?.preserveState,
                only: options?.only,
                onSuccess: options?.onSuccess as any,
                onError: options?.onError as any,
            });
        }
    }

    /**
     * Make a GET request using fetch (for API calls that don't need Inertia)
     */
    static async get<T = unknown>(
        url: string,
        options?: RequestInit,
    ): Promise<T> {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options?.headers,
            },
            credentials: 'include',
            ...options,
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(
                error.message || `Request failed: ${response.status}`,
            );
        }

        return response.json();
    }

    /**
     * Make a POST request using fetch (for plain JSON responses, not Inertia)
     */
    static async postJson<T = unknown>(
        url: string,
        data: Record<string, unknown>,
        options?: {
            onSuccess?: (data: T) => void;
            onError?: (errors: unknown) => void;
        },
    ): Promise<T> {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                body: JSON.stringify(data),
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                const errorMessage =
                    error.message || `Request failed: ${response.status}`;
                options?.onError?.(error);
                throw new Error(errorMessage);
            }

            const result = (await response.json()) as T;
            options?.onSuccess?.(result);
            return result;
        } catch (error) {
            options?.onError?.(error);
            throw error;
        }
    }

    /**
     * Make a POST request using Inertia
     */
    static post(
        url: string,
        data: Record<string, unknown>,
        options?: {
            preserveScroll?: boolean;
            preserveState?: boolean;
            only?: string[];
            onSuccess?: (page: Page<PageProps>) => void;
            onError?: (errors: unknown) => void;
            onFinish?: () => void;
        },
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            router.post(url, data as any, {
                ...options,
                onSuccess: (page) => {
                    options?.onSuccess?.(page);
                    resolve();
                },
                onError: (errors) => {
                    options?.onError?.(errors);
                    reject(errors);
                },
            });
        });
    }

    /**
     * Make a PATCH request using Inertia
     */
    static patch(
        url: string,
        data: Record<string, unknown>,
        options?: {
            preserveScroll?: boolean;
            preserveState?: boolean;
            only?: string[];
            onSuccess?: (page: Page<PageProps>) => void;
            onError?: (errors: unknown) => void;
        },
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            router.patch(url, data as any, {
                ...options,
                onSuccess: (page) => {
                    options?.onSuccess?.(page);
                    resolve();
                },
                onError: (errors) => {
                    options?.onError?.(errors);
                    reject(errors);
                },
            });
        });
    }

    /**
     * Make a DELETE request using Inertia
     */
    static delete(
        url: string,
        options?: {
            preserveScroll?: boolean;
            preserveState?: boolean;
            only?: string[];
            onSuccess?: (page: Page<PageProps>) => void;
            onError?: (errors: unknown) => void;
        },
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            router.delete(url, {
                ...options,
                onSuccess: (page) => {
                    options?.onSuccess?.(page);
                    resolve();
                },
                onError: (errors) => {
                    options?.onError?.(errors);
                    reject(errors);
                },
            });
        });
    }

    /**
     * Reload current page with fresh props from server
     */
    static reload(options?: {
        only?: string[];
        onSuccess?: (page: Page<PageProps>) => void;
    }): void {
        router.reload({
            only: options?.only,
            onSuccess: options?.onSuccess as any,
        });
    }
}

/**
 * Helper to get route URL
 */
export function route(
    path: string,
    params?: Record<string, string | number>,
): string {
    let url = path;

    if (params) {
        Object.entries(params).forEach(([key, value]) => {
            url = url.replace(`:${key}`, String(value));
        });
    }

    return url;
}
