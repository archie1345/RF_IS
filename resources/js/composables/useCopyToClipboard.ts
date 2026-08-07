import { computed, onBeforeUnmount, ref } from 'vue';

export type CopyStatus = 'idle' | 'copying' | 'copied' | 'error';

type CopyOptions = {
    successResetMs?: number;
};

const isBrowser = () => typeof window !== 'undefined';

async function fallbackCopy(text: string): Promise<void> {
    if (!isBrowser()) {
        throw new Error('Clipboard is unavailable outside the browser.');
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', 'true');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.pointerEvents = 'none';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        const succeeded = document.execCommand('copy');
        if (!succeeded) {
            throw new Error('The browser rejected the copy request.');
        }
    } finally {
        document.body.removeChild(textarea);
    }
}

export function useCopyToClipboard(options: CopyOptions = {}) {
    const copied = ref(false);
    const copying = ref(false);
    const error = ref<string | null>(null);
    const timeoutId = ref<number | null>(null);

    function clearTimer(): void {
        if (timeoutId.value !== null && isBrowser()) {
            window.clearTimeout(timeoutId.value);
        }
        timeoutId.value = null;
    }

    function reset(): void {
        clearTimer();
        copied.value = false;
        copying.value = false;
        error.value = null;
    }

    async function copy(text: string): Promise<boolean> {
        if (!text.trim()) {
            error.value = 'There is no text to copy.';
            copied.value = false;
            return false;
        }

        copying.value = true;
        copied.value = false;
        error.value = null;

        try {
            if (isBrowser() && window.navigator.clipboard?.writeText) {
                await window.navigator.clipboard.writeText(text);
            } else {
                await fallbackCopy(text);
            }

            copied.value = true;
            clearTimer();

            const delay = options.successResetMs ?? 1800;
            timeoutId.value = window.setTimeout(() => {
                copied.value = false;
                timeoutId.value = null;
            }, delay);

            return true;
        } catch (copyError) {
            error.value = copyError instanceof Error ? copyError.message : 'Copy failed.';
            copied.value = false;
            return false;
        } finally {
            copying.value = false;
        }
    }

    onBeforeUnmount(() => {
        clearTimer();
    });

    const status = computed<CopyStatus>(() => {
        if (copying.value) return 'copying';
        if (copied.value) return 'copied';
        if (error.value) return 'error';

        return 'idle';
    });

    return {
        copied,
        copying,
        error,
        status,
        copy,
        reset,
    };
}
