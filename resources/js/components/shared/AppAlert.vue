<script setup lang="ts">
import { AlertCircle, AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

type AlertTone = 'info' | 'success' | 'warning' | 'danger' | 'neutral';

type AlertAction = {
    label: string;
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    disabled?: boolean;
};

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
        info: 'border-blue-200 bg-blue-50 text-blue-950 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100',
        warning: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100',
        danger: 'border-red-200 bg-red-50 text-red-950 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-100',
        neutral: 'border-border bg-card text-card-foreground',
    }[props.tone];
});

const iconClass = computed(() => {
    return {
        info: 'text-blue-600 dark:text-blue-300',
        success: 'text-emerald-600 dark:text-emerald-300',
        warning: 'text-amber-600 dark:text-amber-300',
        danger: 'text-red-600 dark:text-red-300',
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
