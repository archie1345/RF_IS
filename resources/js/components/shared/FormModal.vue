<script setup lang="ts">
import { X } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        open: boolean;
        maxWidthClass?: string;
    }>(),
    {
        maxWidthClass: 'max-w-3xl',
    },
);

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
        <div class="absolute inset-0 bg-black/60" @click="emit('close')" />
        <div
            :class="[
                'relative z-10 max-h-[calc(100dvh-0.5rem)] w-full overflow-y-auto overscroll-contain rounded-t-2xl border border-border/70 bg-card p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-xl sm:max-h-[90dvh] sm:rounded-xl sm:p-6',
                props.maxWidthClass,
            ]"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                class="sticky top-0 z-20 float-right -mt-1 -mr-1 flex size-10 items-center justify-center rounded-full border bg-card/95 shadow-sm backdrop-blur hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Tutup dialog"
                @click="emit('close')"
            >
                <X class="size-4" />
            </button>
            <div class="min-w-0 clear-both">
                <slot />
            </div>
        </div>
    </div>
</template>
