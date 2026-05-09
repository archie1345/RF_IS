<script setup lang="ts">
const props = withDefaults(defineProps<{
    open: boolean;
    maxWidthClass?: string;
}>(), {
    maxWidthClass: 'max-w-2xl',
});

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')" />
        <div :class="['relative z-10 max-h-[90vh] w-full overflow-y-auto rounded-lg border bg-background p-4 shadow-lg sm:p-6', props.maxWidthClass]">
            <slot />
        </div>
    </div>
</template>
