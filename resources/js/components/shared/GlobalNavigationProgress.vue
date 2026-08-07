<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const isVisible = ref(false);
let showTimer: number | null = null;
let hideTimer: number | null = null;
let removeStart: (() => void) | null = null;
let removeFinish: (() => void) | null = null;
let removeCancel: (() => void) | null = null;
let removeError: (() => void) | null = null;
let removeInvalid: (() => void) | null = null;
let removeException: (() => void) | null = null;

function clearTimers(): void {
    if (showTimer !== null) {
        window.clearTimeout(showTimer);
        showTimer = null;
    }
    if (hideTimer !== null) {
        window.clearTimeout(hideTimer);
        hideTimer = null;
    }
}

function handleStart(): void {
    clearTimers();
    showTimer = window.setTimeout(() => {
        isVisible.value = true;
    }, 120);
}

function handleFinish(): void {
    clearTimers();
    hideTimer = window.setTimeout(() => {
        isVisible.value = false;
    }, 160);
}

onMounted(() => {
    removeStart = router.on('start', handleStart);
    removeFinish = router.on('finish', handleFinish);
    removeCancel = router.on('cancel', handleFinish);
    removeError = router.on('error', handleFinish);
    removeInvalid = router.on('invalid', handleFinish);
    removeException = router.on('exception', handleFinish);
});

onBeforeUnmount(() => {
    removeStart?.();
    removeFinish?.();
    removeCancel?.();
    removeError?.();
    removeInvalid?.();
    removeException?.();
    clearTimers();
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isVisible" class="pointer-events-none fixed inset-x-0 top-0 z-[240]" aria-hidden="true">
                <div class="h-1 bg-primary/15">
                    <div class="navigation-progress-bar h-full w-1/3 rounded-r-full bg-gradient-to-r from-primary via-primary/80 to-primary/20" />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.navigation-progress-bar {
    animation: navigation-progress 1.1s ease-in-out infinite;
    transform-origin: left center;
}

@keyframes navigation-progress {
    0% {
        transform: translateX(-20%) scaleX(0.65);
        opacity: 0.45;
    }
    50% {
        transform: translateX(95%) scaleX(1);
        opacity: 1;
    }
    100% {
        transform: translateX(240%) scaleX(0.65);
        opacity: 0.45;
    }
}
</style>
