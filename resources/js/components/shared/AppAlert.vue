<script setup lang="ts">
import { AlertCircle, AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { AlertTone, AlertAction } from './AppAlert.types';

const props = withDefaults(
    defineProps<{
        tone?: AlertTone;
        title: string;
        description?: string;
        primaryAction?: AlertAction | null;
        secondaryAction?: AlertAction | null;
    }>(),
    {
        tone: 'info',
        description: '',
        primaryAction: null,
        secondaryAction: null,
    },
);

const emit = defineEmits<{
    (event: 'primary'): void;
    (event: 'secondary'): void;
}>();

const toneClass = computed(() => {
    return {
        info: 'border-brand-blue/30 bg-brand-blue/10 text-brand-blue dark:border-brand-blue/40 dark:bg-brand-blue/15 dark:text-brand-blue/80',
        success:
            'border-brand-lime/30 bg-brand-lime/10 text-brand-lime dark:border-brand-lime/40 dark:bg-brand-lime/15 dark:text-brand-lime/80',
        warning:
            'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100',
        danger: 'border-brand-coral/30 bg-brand-coral/10 text-brand-coral dark:border-brand-coral/40 dark:bg-brand-coral/15 dark:text-brand-coral/80',
        neutral: 'border-border bg-card text-card-foreground',
    }[props.tone];
});

const iconClass = computed(() => {
    return {
        info: 'text-brand-blue dark:text-brand-blue/80',
        success: 'text-brand-lime dark:text-brand-lime/80',
        warning: 'text-amber-600 dark:text-amber-300',
        danger: 'text-brand-coral dark:text-brand-coral/70',
        neutral: 'text-muted-foreground',
    }[props.tone];
});

const Icon = computed(() => {
    return {
        info: Info,
        success: CheckCircle2,
        warning: AlertTriangle,
        danger: XCircle,
        neutral: AlertCircle,
    }[props.tone];
});
</script>

<template>
    <div class="rounded-xl border p-4 shadow-sm" :class="toneClass" role="alert">
        <div class="flex items-start gap-3">
            <component :is="Icon" class="mt-0.5 size-5 shrink-0" :class="iconClass" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ props.title }}</p>
                <p v-if="props.description" class="mt-1 text-sm opacity-90">{{ props.description }}</p>
                <slot />
                <div v-if="props.primaryAction || props.secondaryAction" class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <Button
                        v-if="props.primaryAction"
                        type="button"
                        size="sm"
                        :variant="props.primaryAction.variant ?? (props.tone === 'danger' ? 'destructive' : 'default')"
                        :disabled="props.primaryAction.disabled"
                        @click="emit('primary')"
                    >
                        {{ props.primaryAction.label }}
                    </Button>
                    <Button
                        v-if="props.secondaryAction"
                        type="button"
                        size="sm"
                        :variant="props.secondaryAction.variant ?? 'outline'"
                        :disabled="props.secondaryAction.disabled"
                        @click="emit('secondary')"
                    >
                        {{ props.secondaryAction.label }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
