<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusTone } from '@/types/management';

const props = withDefaults(
    defineProps<{
        label: string;
        tone?: StatusTone;
    }>(),
    {
        tone: 'neutral',
    },
);

const toneClasses = {
    neutral: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200',
    success: 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    warning: 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    danger: 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
    info: 'border-sky-200 bg-sky-100 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
} as const;
</script>

<template>
    <Badge
        variant="outline"
        :class="cn('rounded-full px-2.5 py-1 font-medium', toneClasses[props.tone])"
    >
        {{ props.label }}
    </Badge>
</template>

