<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';

type ErrorSource = Record<string, string | string[] | undefined | null>;

const props = withDefaults(
    defineProps<{
        errors?: ErrorSource;
        title?: string;
        description?: string;
    }>(),
    {
        title: 'Form Validation / Validasi Form',
        description: 'Please review the fields below and fix the backend validation errors. / Periksa kolom berikut dan perbaiki error validasi backend.',
    },
);

const summary = computed(() =>
    Object.entries(props.errors ?? {})
        .flatMap(([field, value]) => {
            if (!value) return [];
            const messages = Array.isArray(value) ? value : [value];

            return messages
                .filter((message) => typeof message === 'string' && message.trim() !== '')
                .map((message) => ({
                    field,
                    message: message.trim(),
                }));
        }),
);
</script>

<template>
    <section v-if="summary.length > 0" class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-50">
        <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-300">
                <AlertTriangle class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold tracking-tight">{{ title }}</h2>
                <p class="mt-1 text-sm leading-6 opacity-90">{{ description }}</p>

                <ul class="mt-3 space-y-2 text-sm leading-6">
                    <li
                        v-for="item in summary"
                        :key="`${item.field}:${item.message}`"
                        class="rounded-xl bg-background/80 px-3 py-2 text-foreground shadow-sm ring-1 ring-border/70 dark:bg-background/10"
                    >
                        <span class="font-semibold">{{ item.field }}</span>
                        <span class="mx-2 text-muted-foreground">-</span>
                        <span>{{ item.message }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
