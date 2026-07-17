<script setup lang="ts">
import { AlertCircle, CheckCircle2, Clock3, MonitorX, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    state: string;
    message: string | null;
}>();

const config = computed(() => {
    if (props.state === 'ready') {
        return {
            icon: ShieldCheck,
            title: 'Ready for phone check-in',
            className:
                'border-brand-blue/30 bg-brand-blue/10 text-brand-blue dark:border-brand-blue/40 dark:bg-brand-blue/15 dark:text-brand-blue/80',
        };
    }

    if (props.state === 'already_present') {
        return {
            icon: CheckCircle2,
            title: 'Attendance recorded',
            className:
                'border-brand-lime/30 bg-brand-lime/10 text-brand-lime dark:border-brand-lime/40 dark:bg-brand-lime/15 dark:text-brand-lime/80',
        };
    }

    if (props.state === 'not_open' || props.state === 'closed') {
        return {
            icon: Clock3,
            title: props.state === 'not_open' ? 'QR is not open yet' : 'QR is closed',
            className:
                'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100',
        };
    }

    if (props.state === 'desktop_blocked') {
        return {
            icon: MonitorX,
            title: 'Phone only',
            className:
                'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-100',
        };
    }

    return {
        icon: AlertCircle,
        title: 'Cannot check in',
        className:
            'border-brand-slate/30 bg-brand-slate/10 text-brand-slate dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-100',
    };
});
</script>

<template>
    <div :class="cn('flex gap-3 rounded-2xl border p-4 shadow-sm', config.className)">
        <component :is="config.icon" class="mt-0.5 size-5 shrink-0" />
        <div class="grid gap-1">
            <p class="font-semibold">{{ config.title }}</p>
            <p class="text-sm opacity-80">{{ props.message }}</p>
        </div>
    </div>
</template>
