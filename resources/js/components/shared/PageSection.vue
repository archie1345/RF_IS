<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

defineProps<{
    title: string;
    description: string;
    eyebrow?: string;
}>();
</script>

<template>
    <section class="min-w-0 space-y-5">
        <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0 space-y-2">
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold tracking-[0.16em] break-words text-muted-foreground uppercase"
                >
                    {{ t(eyebrow) }}
                </p>
                <div class="min-w-0 space-y-1">
                    <h2 class="text-xl font-semibold break-words text-foreground sm:text-2xl">
                        {{ t(title) }}
                    </h2>
                    <p class="max-w-3xl text-sm leading-6 break-words text-muted-foreground">
                        {{ t(description) }}
                    </p>
                </div>
            </div>
            <div
                v-if="$slots.actions"
                class="grid w-full shrink-0 grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end [&>*]:w-full sm:[&>*]:w-auto"
            >
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </section>
</template>
