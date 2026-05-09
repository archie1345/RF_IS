<script setup lang="ts">
withDefaults(defineProps<{
    justify?: 'start' | 'end';
    wrap?: boolean;
}>(), {
    justify: 'end',
    wrap: false,
});
</script>

<template>
    <div :class="['flex items-center gap-2', justify === 'start' ? 'justify-start' : 'justify-end', wrap ? 'flex-wrap' : '']">
        <slot />
    </div>
</template>
