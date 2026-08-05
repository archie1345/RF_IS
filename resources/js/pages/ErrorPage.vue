<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Home, RefreshCw } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { httpStatusPresentation } from '@/lib/httpStatuses';

const props = defineProps<{
    status: number;
    statusText?: string | null;
    title?: string;
    message?: string;
    diagnosis?: string;
}>();

const configuredStatus = computed(() => httpStatusPresentation(props.status, props.statusText));
const category = computed(() => configuredStatus.value.category);
const title = computed(() => props.title ?? configuredStatus.value.title);
const message = computed(() => props.message ?? configuredStatus.value.message);
const diagnosis = computed(() => props.diagnosis ?? configuredStatus.value.diagnosis);
const isError = computed(() => props.status >= 400);
const homeLabel = computed(() => (props.status === 401 ? 'Go to sign in' : 'Go to home'));
const homeHref = computed(() => (props.status === 401 ? '/login' : '/'));

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    window.location.assign(homeHref.value);
}

function reloadPage() {
    window.location.reload();
}
</script>

<template>
    <Head :title="`${status} - ${title}`" />

    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-background px-4 py-10 text-foreground"
    >
        <div class="pointer-events-none absolute inset-0 opacity-50">
            <div class="absolute -top-32 left-1/2 size-96 -translate-x-1/2 rounded-full bg-muted blur-3xl"></div>
            <div class="absolute -right-32 bottom-0 size-80 rounded-full bg-muted/70 blur-3xl"></div>
        </div>

        <section
            class="relative grid w-full max-w-2xl gap-6 rounded-2xl border border-border/70 bg-card/95 p-6 shadow-sm backdrop-blur sm:p-8"
        >
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-11 items-center justify-center rounded-xl"
                        :class="isError ? 'bg-destructive/10 text-destructive' : 'bg-muted text-foreground'"
                    >
                        <AlertCircle class="size-6" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            {{ category }}
                        </p>
                        <p class="text-sm font-medium text-muted-foreground">HTTP {{ status }}</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ title }}</h1>
                    <p class="max-w-xl text-base leading-7 text-muted-foreground">{{ message }}</p>
                </div>
            </div>

            <Alert :variant="isError ? 'destructive' : 'default'" class="shadow-sm">
                <AlertCircle class="size-4" />
                <AlertTitle>{{ isError ? 'What happened' : 'Response details' }}</AlertTitle>
                <AlertDescription>
                    {{ diagnosis }}
                </AlertDescription>
            </Alert>

            <div class="flex flex-col gap-3 border-t border-border/70 pt-5 sm:flex-row sm:flex-wrap">
                <Button type="button" variant="outline" class="w-full sm:w-auto" @click="goBack">
                    <ArrowLeft class="size-4" />
                    Go back
                </Button>
                <Button type="button" variant="outline" class="w-full sm:w-auto" @click="reloadPage">
                    <RefreshCw class="size-4" />
                    Try again
                </Button>
                <Button as-child class="w-full sm:w-auto">
                    <Link :href="homeHref">
                        <Home class="size-4" />
                        {{ homeLabel }}
                    </Link>
                </Button>
            </div>

            <p class="text-xs leading-5 text-muted-foreground">
                Keep the HTTP code when reporting an unexpected response to an administrator. Avoid repeatedly
                submitting the same action while the underlying problem is still occurring.
            </p>
        </section>
    </main>
</template>
