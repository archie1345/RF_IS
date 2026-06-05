<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const props = defineProps<{
    status: number;
    title?: string;
    message?: string;
    diagnosis?: string;
}>();

const defaults: Record<number, { title: string; message: string; diagnosis: string }> = {
    400: {
        title: 'Bad request',
        message: 'The request could not be understood by the server.',
        diagnosis: 'The page received missing, malformed, or unexpected request data.',
    },
    401: {
        title: 'Sign in required',
        message: 'You need to sign in before opening this page.',
        diagnosis: 'Your session may have expired, or this page requires authentication.',
    },
    403: {
        title: 'Access denied',
        message: 'You do not have permission to view this page.',
        diagnosis: 'Your account role does not match the permission required for this action.',
    },
    404: {
        title: 'Page not found',
        message: 'The page or record you are looking for could not be found.',
        diagnosis: 'The link may be outdated, the item may have been deleted, or the route does not exist.',
    },
    419: {
        title: 'Session expired',
        message: 'Your secure session token is no longer valid.',
        diagnosis: 'The form was open for too long, or the browser sent an old CSRF token.',
    },
    429: {
        title: 'Too many requests',
        message: 'This action was attempted too many times in a short period.',
        diagnosis: 'Rate limiting is active to protect the application.',
    },
    500: {
        title: 'Server error',
        message: 'The application hit an unexpected server-side problem.',
        diagnosis: 'A controller, query, integration, or stored value caused an exception.',
    },
    503: {
        title: 'Service unavailable',
        message: 'The application is temporarily unavailable.',
        diagnosis: 'The server may be in maintenance mode or temporarily overloaded.',
    },
};

const fallback = {
    title: 'Something went wrong',
    message: 'The application could not complete this request.',
    diagnosis: 'The response status is not one of the common handled error templates.',
};

const error = computed(() => defaults[props.status] ?? fallback);
const title = computed(() => props.title ?? error.value.title);
const message = computed(() => props.message ?? error.value.message);
const diagnosis = computed(() => props.diagnosis ?? error.value.diagnosis);

</script>

<template>
    <Head :title="`${status} - ${title}`" />

    <main class="flex min-h-screen items-center justify-center bg-background px-4 py-10 text-foreground">
        <section class="grid w-full max-w-2xl gap-6">
            <div class="space-y-3">
                <div class="text-sm font-medium text-muted-foreground">Error {{ status }}</div>
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ title }}</h1>
                <p class="max-w-xl text-base leading-7 text-muted-foreground">{{ message }}</p>
            </div>

            <Alert variant="destructive" class="shadow-sm">
                <AlertCircle class="size-4" />
                <AlertTitle>Diagnosis</AlertTitle>
                <AlertDescription>
                    {{ diagnosis }}
                </AlertDescription>
            </Alert>

        </section>
    </main>
</template>
