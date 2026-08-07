import { onBeforeUnmount, onMounted } from 'vue';

type MaybeGetter<T> = T | (() => T);

type KeyboardShortcutOptions = {
    enabled?: MaybeGetter<boolean>;
    preventDefault?: boolean;
    stopPropagation?: boolean;
    target?: MaybeGetter<Window | Document | null | undefined>;
};

type ShortcutState = {
    key: string;
    mod: boolean;
    ctrl: boolean;
    meta: boolean;
    alt: boolean;
    shift: boolean;
};

const modifierKeys = new Set(['ctrl', 'control', 'meta', 'cmd', 'command', 'super', 'win', 'windows', 'alt', 'option', 'shift', 'mod']);

function resolveValue<T>(value: MaybeGetter<T> | undefined): T | undefined {
    if (typeof value === 'function') {
        return (value as () => T)();
    }

    return value;
}

function isMacPlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const platform = navigator.platform?.toLowerCase() ?? '';
    const userAgent = navigator.userAgent?.toLowerCase() ?? '';

    return platform.includes('mac') || platform.includes('iphone') || platform.includes('ipad') || userAgent.includes('mac os');
}

function normalizeKey(key: string): string {
    const lower = key.toLowerCase();

    return (
        {
            esc: 'escape',
            return: 'enter',
            cmd: 'meta',
            command: 'meta',
            option: 'alt',
            ctrl: 'control',
            control: 'control',
            win: 'meta',
            windows: 'meta',
            super: 'meta',
            plus: '+',
            space: ' ',
        }[lower] ?? lower
    );
}

function parseShortcut(shortcut: string): ShortcutState {
    const parts = shortcut
        .split('+')
        .map((part) => part.trim())
        .filter(Boolean);

    const state: ShortcutState = {
        key: '',
        mod: false,
        ctrl: false,
        meta: false,
        alt: false,
        shift: false,
    };

    for (const part of parts) {
        const key = normalizeKey(part);
        if (modifierKeys.has(key)) {
            if (key === 'mod') {
                state.mod = true;
            } else if (key === 'ctrl' || key === 'control') {
                state.ctrl = true;
            } else if (key === 'meta') {
                state.meta = true;
            } else if (key === 'alt' || key === 'option') {
                state.alt = true;
            } else if (key === 'shift') {
                state.shift = true;
            }
            continue;
        }

        state.key = key;
    }

    return state;
}

function matchesShortcut(event: KeyboardEvent, shortcut: ShortcutState): boolean {
    if (shortcut.mod) {
        const modPressed = isMacPlatform() ? event.metaKey : event.ctrlKey;
        if (!modPressed) return false;
    }

    if (shortcut.ctrl && !event.ctrlKey) return false;
    if (shortcut.meta && !event.metaKey) return false;
    if (shortcut.alt && !event.altKey) return false;
    if (shortcut.shift && !event.shiftKey) return false;

    const key = normalizeKey(event.key);
    if (shortcut.key && shortcut.key !== key) {
        return false;
    }

    return true;
}

export function useKeyboardShortcut(
    shortcut: string,
    handler: (event: KeyboardEvent) => void,
    options: KeyboardShortcutOptions = {},
) {
    const parsed = parseShortcut(shortcut);
    let target: Window | Document | null | undefined;

    const listener = (event: Event): void => {
        if (resolveValue(options.enabled) === false) return;

        const keyboardEvent = event as KeyboardEvent;
        if (!matchesShortcut(keyboardEvent, parsed)) return;

        if (options.preventDefault ?? true) {
            keyboardEvent.preventDefault();
        }
        if (options.stopPropagation ?? false) {
            keyboardEvent.stopPropagation();
        }

        handler(keyboardEvent);
    };

    onMounted(() => {
        target = resolveValue(options.target) ?? window;
        target.addEventListener('keydown', listener as EventListener);
    });

    onBeforeUnmount(() => {
        target?.removeEventListener('keydown', listener as EventListener);
    });

    return {
        shortcut,
    };
}
