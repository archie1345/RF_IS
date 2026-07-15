<script setup lang="ts">
import { computed } from 'vue';
import { AlertCircle, CheckCircle2, Clock3, MonitorX, ShieldCheck } from 'lucide-vue-next';
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
            className: 'border-blue-200 bg-blue-50 text-blue-950 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100',
        };
    }

    if (props.state === 'already_present') {
        return {
            icon: CheckCircle2,
            title: 'Attendance recorded',
            className: 'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100',
        };
    }

    if (props.state === 'not_open' || props.state === 'closed') {
        return {
            icon: Clock3,
            title: props.state === 'not_open' ? 'QR is not open yet' : 'QR is closed',
            className: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100',
        };
    }

    if (props.state === 'desktop_blocked') {
        return {
            icon: MonitorX,
            title: 'Phone only',
            className: 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-100',
        };
    }

    return {
        icon: AlertCircle,
        title: 'Cannot check in',
        className: 'border-slate-200 bg-slate-50 text-slate-950 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-100',
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
