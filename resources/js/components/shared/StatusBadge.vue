<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusTone } from '@/types/resource-table';

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
    neutral:
        'border-brand-slate/30 bg-brand-slate/10 text-brand-slate dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200',
    success:
        'border-brand-lime/30 bg-brand-lime/20 text-brand-lime dark:border-brand-lime/40 dark:bg-brand-lime/15 dark:text-brand-lime/80',
    warning: 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    danger: 'border-rose-200 bg-rose-100 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
    info: 'border-brand-blue/30 bg-brand-blue/15 text-brand-blue dark:border-brand-blue/40 dark:bg-brand-blue/15 dark:text-brand-blue/80',
} as const;
</script>

<template>
    <Badge variant="outline" :class="cn('rounded-full px-2.5 py-1 font-medium', toneClasses[props.tone])">
        {{ props.label }}
    </Badge>
</template>
