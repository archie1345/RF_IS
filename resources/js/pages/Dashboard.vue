<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, type PropType } from 'vue';
import DashboardHeroSection from '@/components/dashboard/DashboardHeroSection.vue';
import DashboardOverviewSections from '@/components/dashboard/DashboardOverviewSections.vue';
import ParentSettingsCard from '@/components/dashboard/ParentSettingsCard.vue';
import { useLiveReload } from '@/composables/useLiveReload';
import { appRoutes } from '@/data/routes';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';
import type { AppRole, Metric, TableRow, AttendanceRow } from '@/types/resource-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: appRoutes.dashboard,
    },
];

const page = usePage<{ auth: Auth }>();
const props = defineProps({
    metrics: { type: Array as PropType<Metric[]>, required: true },
    activityPreviewRows: { type: Array as PropType<TableRow[]>, required: true },
    announcements: { type: Array as PropType<TableRow[]>, required: true },
    upcomingEvents: { type: Array as PropType<TableRow[]>, required: true },
    attendanceRows: { type: Array as PropType<AttendanceRow[]>, required: true },
    paymentRows: { type: Array as PropType<TableRow[]>, required: true },
    medalRows: { type: Array as PropType<TableRow[]>, required: true },
    profileSummary: { type: Object as PropType<Record<string, string>>, required: true },
});

const role = computed<AppRole>(() => {
    const userRole = page.props.auth?.user?.role;

    return userRole === 'admin' || userRole === 'coach' || userRole === 'parent' || userRole === 'athlete' ? userRole : 'athlete';
});

const children = computed(() => page.props.auth.children ?? []);
const activeChild = computed(() => page.props.auth.activeChild ?? null);

const switchChild = (athleteId: string) => {
    if (!athleteId) {
        router.delete(appRoutes.parentChildrenSwitch, { preserveState: true });

        return;
    }

    router.post(appRoutes.parentChildrenSwitch, { athlete_id: athleteId }, { preserveState: true });
};

useLiveReload(
    () => role.value === 'admin',
    () => router.reload({ only: ['activityPreviewRows', 'metrics'], preserveUrl: true }),
    10000,
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <DashboardHeroSection
                :role="role"
                :metrics="props.metrics"
                :children="children"
                :active-child="activeChild"
                @switch-child="switchChild"
            />

            <DashboardOverviewSections
                :role="role"
                :announcements="props.announcements"
                :upcoming-events="props.upcomingEvents"
                :profile-summary="props.profileSummary"
                :medal-rows="props.medalRows"
                :activity-preview-rows="props.activityPreviewRows"
                :attendance-rows="props.attendanceRows"
                :payment-rows="props.paymentRows"
            />

            <ParentSettingsCard v-if="role === 'parent'" />
        </div>
    </AppLayout>
</template>

