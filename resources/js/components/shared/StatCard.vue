<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const props = defineProps<{
    label: string;
    value: string;
    detail: string;
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
}>();

const accentClasses = {
    neutral: 'border-brand-slate/30 bg-white/80 dark:border-slate-800 dark:bg-slate-950/60',
    success: 'border-brand-lime/30 bg-brand-lime/10/70 dark:border-brand-lime/40 dark:bg-brand-lime/15',
    warning: 'border-amber-200 bg-amber-50/70 dark:border-amber-900 dark:bg-amber-950/30',
    danger: 'border-rose-200 bg-rose-50/70 dark:border-rose-900 dark:bg-rose-950/30',
    info: 'border-brand-blue/30 bg-brand-blue/10 dark:border-brand-blue/40 dark:bg-brand-blue/15/30',
} as const;
</script>

<template>
    <Card :class="cn('rounded-xl border shadow-sm', accentClasses[props.tone ?? 'neutral'])">
        <CardContent class="space-y-3 p-4 sm:p-5">
            <p class="text-sm font-medium text-muted-foreground">{{ props.label }}</p>
            <div class="space-y-1">
                <p class="text-2xl font-semibold sm:text-3xl">{{ props.value }}</p>
                <p class="text-sm text-muted-foreground">{{ props.detail }}</p>
            </div>
        </CardContent>
    </Card>
</template>
