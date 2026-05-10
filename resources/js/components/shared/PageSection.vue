<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
    eyebrow?: string;
}>();
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground"
                >
                    {{ eyebrow }}
                </p>
                <div class="space-y-1">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">
                        {{ title }}
                    </h2>
                    <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex w-full shrink-0 items-center gap-3 sm:w-auto">
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </section>
</template>
