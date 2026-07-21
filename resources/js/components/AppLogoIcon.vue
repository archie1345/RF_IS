<script setup lang="ts">
import type { Props } from './AppLogoIcon.types';

defineOptions({
    inheritAttrs: false,
});

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" aria-hidden="true" v-bind="$attrs">
        <path
            d="M20 3.5 34 9.7v9.8c0 8.2-5.8 15-14 18-8.2-3-14-9.8-14-18V9.7L20 3.5Z"
            fill="none"
            stroke="currentColor"
            stroke-linejoin="round"
            stroke-width="3"
        />
        <text
            x="20"
            y="24.5"
            fill="currentColor"
            font-family="Arial, Helvetica, sans-serif"
            font-size="12"
            font-weight="800"
            text-anchor="middle"
        >
            RF
        </text>
    </svg>
</template>
