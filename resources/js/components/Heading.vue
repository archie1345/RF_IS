<script setup lang="ts">
import type { Props } from './Heading.types';

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2 :class="variant === 'small' ? 'mb-0.5 text-base font-medium' : 'text-xl font-semibold'">
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
