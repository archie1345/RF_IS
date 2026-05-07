import { onMounted, onUnmounted } from 'vue';

export function useLiveReload(enabled: () => boolean, reload: () => void, intervalMs = 10000): void {
    let intervalId: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        if (!enabled()) {
            return;
        }

        intervalId = setInterval(reload, intervalMs);
    });

    onUnmounted(() => {
        if (intervalId) {
            clearInterval(intervalId);
        }
    });
}
