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
    neutral: 'border-slate-200 bg-white/80 dark:border-slate-800 dark:bg-slate-950/60',
    success: 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900 dark:bg-emerald-950/30',
    warning: 'border-amber-200 bg-amber-50/70 dark:border-amber-900 dark:bg-amber-950/30',
    danger: 'border-rose-200 bg-rose-50/70 dark:border-rose-900 dark:bg-rose-950/30',
    info: 'border-sky-200 bg-sky-50/70 dark:border-sky-900 dark:bg-sky-950/30',
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

