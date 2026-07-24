import { readonly, reactive } from 'vue';

export type AppPopupTone = 'info' | 'success' | 'warning' | 'danger';

export type AppPopupOptions = {
    title: string;
    message?: string;
    tone?: AppPopupTone;
    confirmLabel?: string;
    cancelLabel?: string;
    showCancel?: boolean;
    dismissible?: boolean;
};

type PopupRequest = Required<Omit<AppPopupOptions, 'message'>> & {
    message: string;
    resolve: (confirmed: boolean) => void;
};

const popupState = reactive({
    open: false,
    title: '',
    message: '',
    tone: 'info' as AppPopupTone,
    confirmLabel: 'Tutup',
    cancelLabel: 'Batal',
    showCancel: false,
    dismissible: true,
});

const queue: PopupRequest[] = [];
let activeRequest: PopupRequest | null = null;

function normalizeOptions(options: AppPopupOptions): Omit<PopupRequest, 'resolve'> {
    const showCancel = options.showCancel ?? false;

    return {
        title: options.title,
        message: options.message ?? '',
        tone: options.tone ?? 'info',
        confirmLabel: options.confirmLabel ?? (showCancel ? 'Lanjutkan' : 'Tutup'),
        cancelLabel: options.cancelLabel ?? 'Batal',
        showCancel,
        dismissible: options.dismissible ?? true,
    };
}

function presentNext(): void {
    if (activeRequest || queue.length === 0) return;

    activeRequest = queue.shift() ?? null;
    if (!activeRequest) return;

    popupState.title = activeRequest.title;
    popupState.message = activeRequest.message;
    popupState.tone = activeRequest.tone;
    popupState.confirmLabel = activeRequest.confirmLabel;
    popupState.cancelLabel = activeRequest.cancelLabel;
    popupState.showCancel = activeRequest.showCancel;
    popupState.dismissible = activeRequest.dismissible;
    popupState.open = true;
}

function openPopup(options: AppPopupOptions): Promise<boolean> {
    return new Promise((resolve) => {
        queue.push({ ...normalizeOptions(options), resolve });
        presentNext();
    });
}

export function settleAppPopup(confirmed: boolean): void {
    if (!activeRequest) return;

    popupState.open = false;
    const completedRequest = activeRequest;
    activeRequest = null;
    completedRequest.resolve(confirmed);

    window.setTimeout(presentNext, 120);
}

export function dismissAppPopup(): void {
    if (!popupState.dismissible) return;
    settleAppPopup(false);
}

export function useAppPopup() {
    return {
        state: readonly(popupState),
        confirm: (options: Omit<AppPopupOptions, 'showCancel'>) =>
            openPopup({ ...options, showCancel: true }),
        show: openPopup,
        info: (title: string, message?: string) => openPopup({ title, message, tone: 'info' }),
        success: (title: string, message?: string) => openPopup({ title, message, tone: 'success' }),
        warning: (title: string, message?: string) => openPopup({ title, message, tone: 'warning' }),
        error: (title: string, message?: string) => openPopup({ title, message, tone: 'danger' }),
    };
}
