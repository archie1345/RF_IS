<script setup lang="ts">
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import { dashboardColumns, mapAnnouncements, mapProfileSummary } from '@/data/dashboard';
import type { AppRole, TableRow } from '@/types/management';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    role: AppRole;
    announcements: string[];
    upcomingEvents: TableRow[];
    profileSummary: Record<string, string>;
    medalRows: TableRow[];
    activityPreviewRows: TableRow[];
    attendanceRows: TableRow[];
    paymentRows: TableRow[];
    snapshotRows: TableRow[];
}>();
</script>

<template>
    <div class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Announcements" description="Latest broadcast notes" :columns="dashboardColumns.announcement" :rows="mapAnnouncements(props.announcements)" />
        <DataTable title="Upcoming events" description="Nearest events on calendar" :columns="dashboardColumns.event" :rows="props.upcomingEvents" />
    </div>

    <div v-if="props.role === 'athlete'" class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Short profile" description="Athlete quick profile summary" :columns="dashboardColumns.profile" :rows="mapProfileSummary(props.profileSummary)" />
        <DataTable title="Medals" description="Current medal totals" :columns="dashboardColumns.medal" :rows="props.medalRows" />
    </div>

    <div v-if="props.role === 'admin'" class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Admin medals" description="Overall medal summary" :columns="dashboardColumns.medal" :rows="props.medalRows" />
        <DataTable title="Users activity log (live preview)" description="Auto-refresh every 10 seconds. Open full page for complete trace." :columns="dashboardColumns.log" :rows="props.activityPreviewRows">
            <template #row-actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="managementRoutes.activityLogs">Open full log</Link>
                </Button>
            </template>
        </DataTable>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Attendance" description="Training attendance records" :columns="dashboardColumns.attendance" :rows="props.attendanceRows" />
        <DataTable title="Payment" description="Supports partial, full, and unpaid status" :columns="dashboardColumns.payment" :rows="props.paymentRows" />
    </div>

    <DataTable title="Operational snapshot" description="Reusable dynamic table for any modules/columns" :columns="dashboardColumns.snapshot" :rows="props.snapshotRows" />
</template>

