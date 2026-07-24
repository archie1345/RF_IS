<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableFilter, TableRow } from '@/types/resource-table';
import type { SessionVisibility, SessionFilters } from './SessionsPage.types';
import { dashboard } from '@/routes';
import {
    attendance as sessionAttendance,
    destroy as sessionDestroy,
    index as sessionsIndex,
    join as sessionJoin,
} from '@/routes/sessions';

const props = defineProps<{
    metrics: Metric[];
    filters?: SessionFilters;
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
}>();
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Sessions', href: sessionsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'session', label: 'Session' },
    { key: 'branch', label: 'Branch' },
    { key: 'group', label: 'Group' },
    { key: 'coach', label: 'Coach' },
    { key: 'schedule', label: 'Schedule' },
    { key: 'status', label: 'Status' },
];

const sessionTableFilters: TableFilter[] = [
    {
        key: 'branch',
        label: 'Branch',
        type: 'select',
        columnKey: 'branch',
        placeholder: 'All branches',
        searchPlaceholder: 'Search branch...',
    },
    {
        key: 'group',
        label: 'Group',
        type: 'select',
        columnKey: 'group',
        placeholder: 'All groups',
        searchPlaceholder: 'Search group...',
    },
    {
        key: 'coach',
        label: 'Coach',
        type: 'select',
        columnKey: 'coach',
        placeholder: 'All coaches',
        searchPlaceholder: 'Search coach...',
    },
    {
        key: 'status',
        label: 'Status',
        type: 'select',
        columnKey: 'status',
        placeholder: 'All statuses',
        searchPlaceholder: 'Search status...',
    },
];

const effectiveFilters = computed<{
    visibility: SessionVisibility;
    archived_count: number;
    upcoming_count: number;
    all_count: number;
}>(() => {
    const fallbackCount = props.rows.length;
    const visibility = props.filters?.visibility === 'past' ? 'archived' : (props.filters?.visibility ?? 'upcoming');

    return {
        visibility,
        archived_count: props.filters?.archived_count ?? props.filters?.past_count ?? 0,
        upcoming_count: props.filters?.upcoming_count ?? fallbackCount,
        all_count: props.filters?.all_count ?? fallbackCount,
    };
});

const visibilityOptions: Array<{
    value: SessionVisibility;
    label: string;
    countKey: 'upcoming_count' | 'archived_count' | 'all_count';
}> = [
    { value: 'upcoming', label: 'Upcoming', countKey: 'upcoming_count' },
    { value: 'archived', label: 'Archived', countKey: 'archived_count' },
    { value: 'all', label: 'All', countKey: 'all_count' },
];

function sessionIdFromRow(row: TableRow): number | null {
    return routeId(row.session_id ?? row.id);
}

function setVisibility(visibility: SessionVisibility) {
    router.get(
        sessionsIndex.url(),
        { visibility },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

async function removeSession(row: TableRow): Promise<void> {
    const id = sessionIdFromRow(row);
    if (!id) return;

    const confirmed = await popup.confirm({
        title: 'Hapus sesi latihan?',
        message: 'Sesi yang dihasilkan ini akan dihapus. Riwayat terkait dapat memblokir penghapusan dan harus ditangani melalui halaman attendance sesi.',
        tone: 'danger',
        confirmLabel: 'Hapus sesi',
    });
    if (!confirmed) return;

    router.delete(sessionDestroy.url(id), { preserveScroll: true });
}

function joinSession(row: TableRow) {
    const id = sessionIdFromRow(row);
    if (!id) return;
    router.post(sessionJoin.url(id));
}
</script>

<template>
    <Head title="Sessions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                title="Session"
                description="Sessions are generated from Admin → Kelas Latihan. Archived sessions are hidden by default. Finished one-day classes move into Archived automatically."
            >
                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6">
                <section class="rounded-2xl border bg-card p-4 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-black">Session visibility</h2>
                            <p class="text-sm text-muted-foreground">
                                Default view shows today and future sessions. Archived stores finished sessions and
                                completed one-day classes.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="option in visibilityOptions"
                                :key="option.value"
                                type="button"
                                size="sm"
                                :variant="effectiveFilters.visibility === option.value ? 'default' : 'outline'"
                                @click="setVisibility(option.value)"
                            >
                                {{ option.label }} ({{ effectiveFilters[option.countKey] }})
                            </Button>
                        </div>
                    </div>
                </section>

                <DataTable
                    title="Session lineup"
                    description="Use Edit to manage QR attendance, athlete attendance, and coach attendance. Create new sessions by creating weekly or one-day classes."
                    :columns="columns"
                    :rows="props.rows"
                    :filters="sessionTableFilters"
                    filterable
                    searchable
                    search-placeholder="Search all session columns"
                    action-label="Actions"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button as-child size="sm" variant="outline">
                                <Link v-if="sessionIdFromRow(row)" :href="sessionAttendance.url(sessionIdFromRow(row)!)"
                                    >Edit</Link
                                >
                            </Button>
                            <Button v-if="row.can_join" size="sm" variant="outline" @click="joinSession(row)"
                                >Join</Button
                            >
                            <Button size="sm" variant="destructive" @click="removeSession(row)">Delete</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
