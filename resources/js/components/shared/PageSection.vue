<script setup lang="ts">
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
                    class="break-words text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    {{ eyebrow }}
                </p>
                <div class="min-w-0 space-y-1">
                    <h2 class="break-words text-xl font-semibold text-foreground sm:text-2xl">
                        {{ title }}
                    </h2>
                    <p class="max-w-3xl break-words text-sm leading-6 text-muted-foreground">
                        {{ description }}
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
