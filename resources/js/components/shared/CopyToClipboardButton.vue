<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useCopyToClipboard } from '@/composables/useCopyToClipboard';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<{
        text: string;
        label?: string;
        copiedLabel?: string;
        successResetMs?: number;
    }>(),
    {
        label: 'Salin / Copy',
        copiedLabel: 'Tersalin / Copied',
        successResetMs: 1800,
    },
);

const clipboard = useCopyToClipboard({ successResetMs: props.successResetMs });

const icon = computed(() => (clipboard.copied.value ? Check : Copy));
const statusLabel = computed(() => (clipboard.copied.value ? props.copiedLabel : props.label));

async function handleCopy(): Promise<void> {
    await clipboard.copy(props.text);
}
</script>

<template>
    <Button v-bind="$attrs" type="button" :disabled="clipboard.copying" @click="handleCopy">
        <slot
            :copied="clipboard.copied.value"
            :copying="clipboard.copying.value"
            :copy="handleCopy"
            :status-label="statusLabel"
        >
            <component :is="icon" class="size-4 shrink-0" />
            <span>{{ statusLabel }}</span>
        </slot>
    </Button>
</template>
