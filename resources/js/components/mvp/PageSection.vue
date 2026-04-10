<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
    eyebrow?: string;
}>();
</script>

<template>
    <section class="space-y-4 rounded-3xl border border-border/70 bg-card/80 p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold uppercase tracking-[0.22em] text-muted-foreground"
                >
                    {{ eyebrow }}
                </p>
                <div class="space-y-1">
                    <h2 class="text-2xl font-semibold tracking-tight text-foreground">
                        {{ title }}
                    </h2>
                    <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-3">
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </section>
</template>
