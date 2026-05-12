<script setup lang="ts">
const props = withDefaults(defineProps<{
    open: boolean;
    maxWidthClass?: string;
}>(), {
    maxWidthClass: 'max-w-3xl',
});

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4">
        <div class="absolute inset-0 bg-black/60" @click="emit('close')" />
        <div :class="['relative z-10 max-h-[calc(100vh-1.5rem)] w-full overflow-y-auto rounded-xl border border-border/70 bg-card p-4 shadow-xl sm:max-h-[90vh] sm:p-6', props.maxWidthClass]">
            <slot />
        </div>
    </div>
</template>
