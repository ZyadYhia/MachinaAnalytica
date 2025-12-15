# Wayfinder Quick Reference

A lightweight routing utility for Inertia.js-based Laravel applications.

## Installation

The Wayfinder utility is already created at `resources/js/lib/wayfinder.ts`. Just import it:

```typescript
import { Wayfinder, chatRoutes, settingsRoutes } from '@/lib/wayfinder';
```

## Route Groups

### Chat Routes

```typescript
chatRoutes.sendMessage; // POST   /unified-chat
chatRoutes.conversations; // GET    /unified-chat/conversations
chatRoutes.conversation(123); // GET    /unified-chat/conversations/123
chatRoutes.deleteConversation(123); // DELETE /unified-chat/conversations/123
```

### Settings Routes

```typescript
settingsRoutes.integrations; // GET    /settings/integrations
settingsRoutes.integrationsShow; // GET    /settings/integrations/show
settingsRoutes.password; // GET    /settings/password
settingsRoutes.profile; // GET    /settings/profile
```

### API Routes

```typescript
apiRoutes.health; // GET    /api/health
apiRoutes.models; // GET    /api/models
```

## API Methods

### GET Request (for API calls)

```typescript
const data = await Wayfinder.get<ResponseType>('/api/endpoint');

// With error handling
try {
    const result = await Wayfinder.get<MyType>(chatRoutes.conversations);
    console.log(result);
} catch (error) {
    console.error('Failed to fetch:', error);
}
```

### POST Request (using Inertia)

```typescript
await Wayfinder.post(
    chatRoutes.sendMessage,
    { message: 'Hello' },
    {
        preserveScroll: true,
        onSuccess: (page) => {
            console.log('Message sent!');
        },
        onError: (errors) => {
            console.error('Error:', errors);
        },
    },
);
```

### PATCH Request

```typescript
await Wayfinder.patch(
    '/settings/profile',
    { name: 'John' },
    { onSuccess: () => alert('Updated!') },
);
```

### DELETE Request

```typescript
await Wayfinder.delete(chatRoutes.deleteConversation(123), {
    onSuccess: () => console.log('Deleted'),
    onError: (errors) => console.error(errors),
});
```

### Navigate

```typescript
Wayfinder.visit('/some-page', {
    method: 'get',
    preserveScroll: true,
    onSuccess: (page) => {
        /* ... */
    },
});
```

### Reload Current Page

```typescript
// Reload entire page
Wayfinder.reload();

// Reload specific props only
Wayfinder.reload({
    only: ['conversations'],
    onSuccess: (page) => {
        /* ... */
    },
});
```

## Options

### Common Options

```typescript
{
    // Maintain scroll position on navigation
    preserveScroll?: boolean;

    // Keep component state between requests
    preserveState?: boolean;

    // Only reload specific page props
    only?: string[];

    // Success callback
    onSuccess?: (page: Page<PageProps>) => void;

    // Error callback
    onError?: (errors: unknown) => void;

    // Cleanup callback (POST/PATCH only)
    onFinish?: () => void;
}
```

## Error Handling

```typescript
// Validation errors
await Wayfinder.post(url, data, {
    onError: (errors) => {
        // errors is ValidationErrors from Laravel
        if (typeof errors === 'object') {
            console.log(errors.message?.email); // Access specific field
        }
    },
});

// Try-catch pattern
try {
    await Wayfinder.post(url, data);
} catch (error) {
    console.error('Request failed:', error);
}
```

## Type Safety

All methods are fully typed with TypeScript:

```typescript
// Response type inference
interface ChatResponse {
    conversation_id: number;
    response: string;
    mode: 'sync' | 'async';
}

const data = await Wayfinder.get<ChatResponse>('/api/chat');
// data is typed as ChatResponse
```

## Examples

### Send Chat Message

```typescript
const message = await Wayfinder.post(
    chatRoutes.sendMessage,
    {
        message: userInput,
        conversation_id: currentConvId,
    },
    {
        onSuccess: (page) => {
            // Conversation created/updated
            loadConversations();
        },
        onError: (errors) => {
            console.error('Failed to send:', errors);
        },
    },
);
```

### Load Conversations

```typescript
interface ConversationResponse {
    data: Conversation[];
}

const result = await Wayfinder.get<ConversationResponse>(
    chatRoutes.conversations,
);
const conversations = result.data;
```

### Delete with Confirmation

```typescript
const handleDelete = async (id: number) => {
    if (!confirm('Delete conversation?')) return;

    try {
        await Wayfinder.delete(chatRoutes.deleteConversation(id), {
            onSuccess: () => {
                console.log('Deleted successfully');
                reloadConversations();
            },
        });
    } catch (error) {
        alert('Failed to delete');
    }
};
```

## Adding New Routes

Edit [resources/js/lib/wayfinder.ts](resources/js/lib/wayfinder.ts):

```typescript
// Add route group
export const newRoutes = {
    endpoint1: '/path/to/endpoint',
    endpoint2: (id: number) => `/path/${id}/endpoint`,
    endpoint3: (id: number, type: string) => `/path/${id}/${type}`,
} as const;

// Export in main routes object
export const routes = {
    chat: chatRoutes,
    settings: settingsRoutes,
    api: apiRoutes,
    new: newRoutes, // Add here
} as const;
```

Then use:

```typescript
import { newRoutes } from '@/lib/wayfinder';

await Wayfinder.post(newRoutes.endpoint1, data);
```

## Best Practices

1. **Use Wayfinder for all HTTP requests** - Consistency across the app
2. **Type your responses** - Use generics for `get<T>()`
3. **Handle errors appropriately** - Use `onError` or try-catch
4. **Preserve state when needed** - Set `preserveState: true` for reactive updates
5. **Group related routes** - Keep routes organized in `wayfinder.ts`
6. **Avoid magic strings** - Always use route constants

## See Also

- [Chat Widget Usage Guide](resources/js/hooks/CHAT_WIDGET_USAGE.md)
- [Enhancement Summary](CHAT_WIDGET_ENHANCEMENT.md)
- [Inertia.js Docs](https://inertiajs.com)
