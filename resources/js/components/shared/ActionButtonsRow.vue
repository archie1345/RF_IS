<script setup lang="ts">
import {
    Archive,
    BadgeCheck,
    CircleDollarSign,
    ClipboardCheck,
    ClipboardPenLine,
    Eye,
    FileDown,
    Image as ImageIcon,
    Link2,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    QrCode,
    RotateCcw,
    Send,
    Settings2,
    Trash2,
    Trophy,
    Upload,
    UserCheck,
    UserPlus,
    UserRoundSearch,
    UserX,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { h, nextTick, onMounted, onUpdated, ref, render } from 'vue';

withDefaults(
    defineProps<{
        justify?: 'start' | 'end';
        wrap?: boolean;
    }>(),
    {
        justify: 'end',
        wrap: true,
    },
);

const container = ref<HTMLElement | null>(null);

type ActionIconRule = {
    pattern: RegExp;
    icon: Component;
};

const actionIconRules: ActionIconRule[] = [
    { pattern: /ubah pendaftaran|edit registration/, icon: ClipboardPenLine },
    { pattern: /catat pembayaran|record payment|settle payment/, icon: CircleDollarSign },
    { pattern: /review bukti|review proof|approve|setujui|verifikasi/, icon: BadgeCheck },
    { pattern: /upload bukti|upload proof|unggah/, icon: Upload },
    { pattern: /lihat bukti|view proof|preview image/, icon: ImageIcon },
    { pattern: /whatsapp/, icon: MessageCircle },
    { pattern: /download|unduh|export|invoice|slip/, icon: FileDown },
    { pattern: /qr/, icon: QrCode },
    { pattern: /presensi|attendance|kehadiran/, icon: ClipboardCheck },
    { pattern: /hasil|result|medal|prestasi/, icon: Trophy },
    { pattern: /pulihkan|restore|retry|coba lagi/, icon: RotateCcw },
    { pattern: /nonaktif|deactivate|suspend/, icon: UserX },
    { pattern: /aktifkan|activate/, icon: UserCheck },
    { pattern: /hubungkan|link parent|link anak|tautkan/, icon: Link2 },
    { pattern: /kirim|send|message/, icon: Send },
    { pattern: /hapus|delete|remove|force delete/, icon: Trash2 },
    { pattern: /kelola|manage|settings|pengaturan/, icon: Settings2 },
    { pattern: /ubah|edit|sunting/, icon: Pencil },
    { pattern: /detail|lihat|view|buka|open/, icon: Eye },
    { pattern: /daftar|register/, icon: UserPlus },
    { pattern: /tambah|add|create|buat/, icon: Plus },
    { pattern: /profil|profile|anggota|member|user/, icon: UserRoundSearch },
    { pattern: /arsip|archive/, icon: Archive },
];

function normalizedLabel(value: string): string {
    return value.replace(/\s+/g, ' ').trim();
}

function iconFor(label: string): Component {
    const normalized = label.toLocaleLowerCase('id-ID');

    return actionIconRules.find((rule) => rule.pattern.test(normalized))?.icon ?? MoreHorizontal;
}

function makeIcon(icon: Component): SVGElement | null {
    const host = document.createElement('span');
    render(
        h(icon, {
            class: 'size-4 shrink-0',
            'aria-hidden': 'true',
            focusable: 'false',
        }),
        host,
    );

    const svg = host.firstElementChild?.cloneNode(true) as SVGElement | undefined;
    render(null, host);

    return svg ?? null;
}

function removeVisibleText(node: Node): void {
    for (const child of Array.from(node.childNodes)) {
        if (child.nodeType === Node.TEXT_NODE) {
            if (child.textContent?.trim()) child.remove();
            continue;
        }

        if (!(child instanceof HTMLElement)) continue;
        if (child.matches('svg, .sr-only, [aria-hidden="true"]')) continue;

        removeVisibleText(child);
    }
}

function enhanceAction(target: HTMLElement): void {
    const previousLabel = target.querySelector(':scope > .table-action-label');
    const label = normalizedLabel(
        target.getAttribute('aria-label') ??
            target.getAttribute('title') ??
            previousLabel?.textContent ??
            target.textContent ??
            '',
    );

    if (!label) return;

    previousLabel?.remove();

    const replacementIcon = makeIcon(iconFor(label));
    const currentIcon = target.querySelector('svg');
    if (replacementIcon && currentIcon) {
        currentIcon.replaceWith(replacementIcon);
    } else if (replacementIcon) {
        target.prepend(replacementIcon);
    }

    removeVisibleText(target);

    const accessibleLabel = document.createElement('span');
    accessibleLabel.className = 'table-action-label sr-only';
    accessibleLabel.textContent = label;
    target.append(accessibleLabel);

    target.classList.add('table-action-icon-only');
    target.setAttribute('aria-label', label);
    target.setAttribute('title', label);
}

async function enhanceActions(): Promise<void> {
    await nextTick();

    for (const root of Array.from(container.value?.children ?? [])) {
        if (!(root instanceof HTMLElement)) continue;

        const target = root.matches('button, a')
            ? root
            : root.querySelector<HTMLElement>('button, a');

        if (target) enhanceAction(target);
    }
}

onMounted(() => void enhanceActions());
onUpdated(() => void enhanceActions());
</script>

<template>
    <div
        ref="container"
        :class="[
            'flex w-full items-center gap-2 sm:w-auto',
            justify === 'start' ? 'justify-start' : 'justify-end',
            wrap ? 'flex-wrap' : 'overflow-x-auto',
            '[&>*]:min-h-10 [&>*]:flex-1 sm:[&>*]:min-h-9 sm:[&>*]:flex-none',
        ]"
    >
        <slot />
    </div>
</template>

<style scoped>
:deep(.table-action-icon-only) {
    display: inline-flex !important;
    width: 2.25rem !important;
    min-width: 2.25rem !important;
    height: 2.25rem !important;
    min-height: 2.25rem !important;
    flex: 0 0 2.25rem !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0 !important;
    padding: 0 !important;
}

:deep(.table-action-icon-only svg) {
    width: 1rem;
    height: 1rem;
}
</style>
