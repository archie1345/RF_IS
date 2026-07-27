<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, type PropType } from 'vue';
import DashboardAnnouncementWidget from '@/components/dashboard/DashboardAnnouncementWidget.vue';
import DashboardHeroSection from '@/components/dashboard/DashboardHeroSection.vue';
import DashboardOverviewSections from '@/components/dashboard/DashboardOverviewSections.vue';
import { useLiveReload } from '@/composables/useLiveReload';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';
import type { AppRole, AttendanceRow, Metric, TableRow, TrainingDay } from '@/types/resource-table';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: dashboard.url() }];
const page = usePage<{ auth: Auth }>();
const props = defineProps({
    roles: { type: Array as PropType<AppRole[]>, default: () => [] },
    metrics: { type: Array as PropType<Metric[]>, required: true },
    activityPreviewRows: { type: Array as PropType<TableRow[]>, required: true },
    announcements: { type: Array as PropType<TableRow[]>, required: true },
    upcomingEvents: { type: Array as PropType<TableRow[]>, required: true },
    attendanceRows: { type: Array as PropType<AttendanceRow[]>, required: true },
    trainingDays: { type: Array as PropType<TrainingDay[]>, required: true },
    paymentRows: { type: Array as PropType<TableRow[]>, required: true },
    medalRows: { type: Array as PropType<TableRow[]>, required: true },
    profileSummary: { type: Object as PropType<Record<string, string>>, required: true },
});

const activeRole = computed<AppRole>(() => {
    const value = page.props.auth?.user?.activeRole ?? page.props.auth?.user?.role;
    return value === 'admin' || value === 'coach' || value === 'parent' || value === 'athlete' ? value : 'athlete';
});
const dashboardRoles = computed<AppRole[]>(() => props.roles.length > 0 ? props.roles : [activeRole.value]);
const roleLabels: Record<AppRole, string> = {
    admin: 'Operasional admin',
    coach: 'Kepelatihan',
    parent: 'Keluarga',
    athlete: 'Aktivitas atlet',
};

useLiveReload(
    () => dashboardRoles.value.includes('admin'),
    () => router.reload({ only: ['activityPreviewRows', 'metrics', 'announcements'], preserveUrl: true }),
    10000,
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <DashboardHeroSection :role="activeRole" :roles="dashboardRoles" :metrics="props.metrics" />
            <DashboardAnnouncementWidget :announcements="props.announcements" />

            <section v-for="dashboardRole in dashboardRoles" :key="dashboardRole" class="space-y-3">
                <div v-if="dashboardRoles.length > 1" class="flex items-center gap-3">
                    <h2 class="text-sm font-bold tracking-wide uppercase">{{ roleLabels[dashboardRole] }}</h2>
                    <div class="h-px flex-1 bg-border" />
                </div>
                <DashboardOverviewSections
                    :role="dashboardRole"
                    :announcements="props.announcements"
                    :upcoming-events="props.upcomingEvents"
                    :profile-summary="props.profileSummary"
                    :medal-rows="props.medalRows"
                    :activity-preview-rows="props.activityPreviewRows"
                    :attendance-rows="props.attendanceRows"
                    :training-days="props.trainingDays"
                    :payment-rows="props.paymentRows"
                />
            </section>
        </div>
    </AppLayout>
</template>
