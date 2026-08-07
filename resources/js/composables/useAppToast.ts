import { inject, readonly, reactive } from 'vue';

export type AppToastTone = 'info' | 'success' | 'warning' | 'danger';

export type AppToastOptions = {
    title: string;
    message?: string;
    tone?: AppToastTone;
    duration?: number;
};

type ToastEntry = Required<Omit<AppToastOptions, 'message' | 'duration'>> & {
    id: number;
    message: string;
    duration: number;
};

export type AppToastApi = {
    state: Readonly<ToastEntry[]>;
    show: (options: AppToastOptions) => number;
    dismiss: (id: number) => void;
    clear: () => void;
    info: (title: string, message?: string, duration?: number) => number;
    success: (title: string, message?: string, duration?: number) => number;
    warning: (title: string, message?: string, duration?: number) => number;
    error: (title: string, message?: string, duration?: number) => number;
};

export const appToastKey = Symbol('AppToast');

const toastState = reactive<ToastEntry[]>([]);
const toastTimers = new Map<number, number>();
let nextToastId = 1;

function clearToastTimer(id: number): void {
    const timer = toastTimers.get(id);
    if (timer !== undefined) {
        window.clearTimeout(timer);
        toastTimers.delete(id);
    }
}

function dismissToast(id: number): void {
    clearToastTimer(id);
    const index = toastState.findIndex((toast) => toast.id === id);
    if (index !== -1) {
        toastState.splice(index, 1);
    }
}

function pushToast(options: AppToastOptions): number {
    const id = nextToastId++;
    const toast: ToastEntry = {
        id,
        title: options.title,
        message: options.message ?? '',
        tone: options.tone ?? 'info',
        duration: options.duration ?? 4500,
    };

    toastState.unshift(toast);

    if (toast.duration > 0) {
        toastTimers.set(id, window.setTimeout(() => dismissToast(id), toast.duration));
    }

    return id;
}

function clearToasts(): void {
    toastState.splice(0, toastState.length);
    toastTimers.forEach((timer) => window.clearTimeout(timer));
    toastTimers.clear();
}

const sharedToastApi: AppToastApi = {
    state: readonly(toastState),
    show: pushToast,
    dismiss: dismissToast,
    clear: clearToasts,
    info: (title: string, message?: string, duration?: number) => pushToast({ title, message, tone: 'info', duration }),
    success: (title: string, message?: string, duration?: number) =>
        pushToast({ title, message, tone: 'success', duration }),
    warning: (title: string, message?: string, duration?: number) =>
        pushToast({ title, message, tone: 'warning', duration }),
    error: (title: string, message?: string, duration?: number) =>
        pushToast({ title, message, tone: 'danger', duration }),
};

export function useAppToast(): AppToastApi {
    return inject(appToastKey, sharedToastApi);
}

export function createAppToastProviderValue(): AppToastApi {
    return {
        state: readonly(toastState),
        show: pushToast,
        dismiss: dismissToast,
        clear: clearToasts,
        info: (title: string, message?: string, duration?: number) => pushToast({ title, message, tone: 'info', duration }),
        success: (title: string, message?: string, duration?: number) =>
            pushToast({ title, message, tone: 'success', duration }),
        warning: (title: string, message?: string, duration?: number) =>
            pushToast({ title, message, tone: 'warning', duration }),
        error: (title: string, message?: string, duration?: number) =>
            pushToast({ title, message, tone: 'danger', duration }),
    };
}
