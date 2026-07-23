<script setup lang="ts">
withDefaults(
    defineProps<{
        justify?: 'start' | 'end';
        wrap?: boolean;
    }>(),
    {
        justify: 'end',
        wrap: true,
    },
);
</script>

<template>
    <div
        :class="[
            'flex w-full items-center gap-2 sm:w-auto',
            justify === 'start' ? 'justify-start' : 'justify-end',
            wrap ? 'flex-wrap' : 'overflow-x-auto',
            '[&>*]:min-h-10 [&>*]:flex-1 sm:[&>*]:min-h-9 sm:[&>*]:flex-none',
        ]"
    >
        <slot />
    </div>
</template>
