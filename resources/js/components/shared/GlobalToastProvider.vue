<script setup lang="ts">
import { CheckCircle2, CircleAlert, Info, X, AlertTriangle } from '@lucide/vue';
import { provide } from 'vue';
import { Button } from '@/components/ui/button';
import { appToastKey, createAppToastProviderValue, type AppToastTone } from '@/composables/useAppToast';

const toast = createAppToastProviderValue();

provide(appToastKey, toast);

const icon: Record<AppToastTone, typeof CheckCircle2> = {
    success: CheckCircle2,
    warning: AlertTriangle,
    danger: CircleAlert,
    info: Info,
};

const cardClass: Record<AppToastTone, string> = {
    success: 'border-emerald-200 bg-emerald-50/95 text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-50',
    warning: 'border-amber-200 bg-amber-50/95 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-50',
    danger: 'border-rose-200 bg-rose-50/95 text-rose-950 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-50',
    info: 'border-sky-200 bg-sky-50/95 text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-50',
};

const accentClass: Record<AppToastTone, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    info: 'bg-sky-500',
};
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[210] flex justify-center px-4 sm:justify-end">
            <TransitionGroup
                name="toast"
                tag="div"
                class="pointer-events-none flex w-full max-w-md flex-col gap-3"
            >
                <article
                    v-for="entry in toast.state"
                    :key="entry.id"
                    class="pointer-events-auto overflow-hidden rounded-2xl border shadow-xl backdrop-blur-sm"
                    :class="cardClass[entry.tone]"
                    role="status"
                    aria-live="polite"
                >
                    <div class="h-1 w-full" :class="accentClass[entry.tone]" />
                    <div class="flex items-start gap-3 p-4">
                        <div
                            class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-background/80 shadow-sm"
                        >
                            <component :is="icon[entry.tone]" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-bold tracking-tight">{{ entry.title }}</h3>
                                    <p v-if="entry.message" class="mt-1 whitespace-pre-line text-sm leading-6 opacity-90">
                                        {{ entry.message }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 shrink-0 rounded-full"
                                    @click="toast.dismiss(entry.id)"
                                >
                                    <X class="size-4" />
                                    <span class="sr-only">Tutup toast / Dismiss toast</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition:
        transform 180ms ease,
        opacity 180ms ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
}
</style>
