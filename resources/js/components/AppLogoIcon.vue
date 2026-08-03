<script setup lang="ts">
import type { Props } from './AppLogoIcon.types';

defineOptions({
    inheritAttrs: false,
});

defineProps<Props>();
</script>

<template>
    <img src="/rffull.svg" alt="" aria-hidden="true" :class="className" v-bind="$attrs" />
</template>
