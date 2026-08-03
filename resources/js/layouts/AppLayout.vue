<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

import type { Props } from './AppLayout.types';

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    autoRefresh: true,
    autoRefreshIntervalMs: 15000,
});

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let refreshInFlight = false;

function focusedFormControl(): boolean {
    const activeElement = document.activeElement;
    if (!activeElement) return false;

    const tagName = activeElement.tagName.toLowerCase();

    return ['input', 'select', 'textarea'].includes(tagName) || activeElement.hasAttribute('contenteditable');
}

function refreshPageData(): void {
    if (!props.autoRefresh || refreshInFlight || document.hidden || focusedFormControl()) return;

    refreshInFlight = true;

    try {
        router.reload({
            onFinish: () => {
                refreshInFlight = false;
            },
        });
    } catch {
        refreshInFlight = false;
    }
}

function handleVisibilityChange(): void {
    if (!document.hidden) refreshPageData();
}

onMounted(() => {
    if (!props.autoRefresh) return;

    const interval = Math.max(props.autoRefreshIntervalMs, 5000);
    refreshTimer = setInterval(refreshPageData, interval);
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    if (refreshTimer) clearInterval(refreshTimer);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AppLayout>
</template>
