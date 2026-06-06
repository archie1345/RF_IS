<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { dashboardColumns, mapProfileSummary } from '@/data/dashboard';
import { managementRoutes } from '@/data/management';
import type { AppRole, TableRow } from '@/types/management';

const props = defineProps<{
    role: AppRole;
    announcements: TableRow[];
    upcomingEvents: TableRow[];
    profileSummary: Record<string, string>;
    medalRows: TableRow[];
    activityPreviewRows: TableRow[];
    attendanceRows: TableRow[];
    paymentRows: TableRow[];
}>();
</script>

<template>
    <div class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Announcements" description="Latest notices that affect this account." :columns="dashboardColumns.announcement" :rows="props.announcements" empty-text="No announcements right now.">
            <template #row-actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="managementRoutes.announcements">Open</Link>
                </Button>
            </template>
        </DataTable>
        <DataTable title="Upcoming events" description="Nearest championships and club events." :columns="dashboardColumns.event" :rows="props.upcomingEvents" empty-text="No upcoming events." />
    </div>

    <div v-if="props.role === 'athlete'" class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Short profile" description="Athlete quick profile summary" :columns="dashboardColumns.profile" :rows="mapProfileSummary(props.profileSummary)" />
        <DataTable title="Medals" description="Current medal totals" :columns="dashboardColumns.medal" :rows="props.medalRows" />
    </div>

    <div v-if="props.role === 'admin'" class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Medal summary" description="Overall achievement count by medal." :columns="dashboardColumns.medal" :rows="props.medalRows" />
        <DataTable title="Recent account activity" description="Live preview of recent admin actions." :columns="dashboardColumns.log" :rows="props.activityPreviewRows">
            <template #row-actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="managementRoutes.activityLogs">Open full log</Link>
                </Button>
            </template>
        </DataTable>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <DataTable title="Attendance" description="Recent training attendance for the selected account scope." :columns="dashboardColumns.attendance" :rows="props.attendanceRows" empty-text="No attendance records yet." />
        <DataTable title="Bills" description="Unpaid and recently updated payment records." :columns="dashboardColumns.payment" :rows="props.paymentRows" empty-text="No bills found.">
            <template #row-actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="managementRoutes.payments">Open</Link>
                </Button>
            </template>
        </DataTable>
    </div>
</template>

