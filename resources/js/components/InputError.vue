<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-brand-coral dark:text-brand-coral">
            {{ message }}
        </p>
    </div>
</template>
