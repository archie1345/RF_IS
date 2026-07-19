<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import AppAlert from '@/components/shared/AppAlert.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    attendance as sessionAttendance,
    destroy as sessionDestroy,
    index as sessionsIndex,
    join as sessionJoin,
} from '@/routes/sessions';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableBadgeCell, TableCell, TableColumn, TableRow } from '@/types/resource-table';

type SessionVisibility = 'upcoming' | 'past' | 'all';
type SessionFilters = {
    visibility: SessionVisibility;
    past_count: number;
    upcoming_count: number;
    all_count: number;
};

type SessionColumnFilters = Record<'session' | 'branch' | 'group' | 'coach' | 'schedule' | 'status', string>;

const props = defineProps<{
    metrics: Metric[];
    filters?: SessionFilters;
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
}>();

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

const columnFilters = reactive<SessionColumnFilters>({
    session: '',
    branch: '',
    group: '',
    coach: '',
    schedule: '',
    status: '',
});
const pendingDeleteSessionId = ref<number | null>(null);

const effectiveFilters = computed<SessionFilters>(() => {
    const fallbackCount = props.rows.length;

    return {
        visibility: props.filters?.visibility ?? 'upcoming',
        past_count: props.filters?.past_count ?? 0,
        upcoming_count: props.filters?.upcoming_count ?? fallbackCount,
        all_count: props.filters?.all_count ?? fallbackCount,
    };
});

const visibilityOptions: Array<{ value: SessionVisibility; label: string; countKey: keyof SessionFilters }> = [
    { value: 'upcoming', label: 'Upcoming', countKey: 'upcoming_count' },
    { value: 'past', label: 'Past', countKey: 'past_count' },
    { value: 'all', label: 'All', countKey: 'all_count' },
];

const branchFilterOptions = computed(() => optionsFromColumn('branch'));
const groupFilterOptions = computed(() => optionsFromColumn('group'));
const coachFilterOptions = computed(() => optionsFromColumn('coach'));
const statusFilterOptions = computed(() => optionsFromColumn('status'));
const hasColumnFilters = computed(() => Object.values(columnFilters).some((value) => value.trim() !== ''));
const filteredRows = computed(() => props.rows.filter((row) => rowMatchesColumnFilters(row)));

function sessionIdFromRow(row: TableRow): number | null {
    const id = Number(row.session_id ?? String(row.id).replace('SES-', ''));

    return Number.isFinite(id) && id > 0 ? id : null;
}

function getCellValue(row: TableRow, key: string): TableCell | undefined {
    return row[key];
}

function isBadgeCell(value: TableCell | undefined): value is TableBadgeCell {
    return typeof value === 'object' && value !== null && 'kind' in value && value.kind === 'badge';
}

function getCellText(value: TableCell | undefined): string {
    if (isBadgeCell(value)) return value.text;
    if (Array.isArray(value)) return value.join(', ');
    if (value === null || value === undefined) return '';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

function optionsFromColumn(key: keyof SessionColumnFilters): SelectOption[] {
    return Array.from(
        new Set(
            props.rows
                .map((row) => getCellText(getCellValue(row, key)).trim())
                .filter((value) => value !== '' && value !== '-'),
        ),
    )
        .sort((left, right) => left.localeCompare(right))
        .map((value) => ({ value, label: value }));
}

function rowMatchesColumnFilters(row: TableRow): boolean {
    return (Object.keys(columnFilters) as Array<keyof SessionColumnFilters>).every((key) => {
        const filter = columnFilters[key].trim().toLowerCase();
        if (!filter) return true;

        return getCellText(getCellValue(row, key)).toLowerCase().includes(filter);
    });
}

function clearColumnFilters() {
    columnFilters.session = '';
    columnFilters.branch = '';
    columnFilters.group = '';
    columnFilters.coach = '';
    columnFilters.schedule = '';
    columnFilters.status = '';
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

function removeSession(row: TableRow) {
    const id = sessionIdFromRow(row);
    if (!id) return;
    pendingDeleteSessionId.value = id;
}

function cancelDeleteSession() {
    pendingDeleteSessionId.value = null;
}

function confirmDeleteSession() {
    if (!pendingDeleteSessionId.value) return;
    const id = pendingDeleteSessionId.value;
    pendingDeleteSessionId.value = null;
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
            <AppAlert
                v-if="pendingDeleteSessionId"
                tone="danger"
                title="Delete this session?"
                description="This generated session will be removed. Create or edit classes from Admin → Kelas Latihan to generate sessions."
                :primary-action="{ label: 'Delete session', variant: 'destructive' }"
                :secondary-action="{ label: 'Cancel', variant: 'outline' }"
                @primary="confirmDeleteSession"
                @secondary="cancelDeleteSession"
            />

            <PageSection
                title="Session"
                description="Sessions are generated from Admin → Kelas Latihan. Past sessions are hidden by default."
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
                                Default view shows today and future sessions. Use this to review history.
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

                <section class="rounded-2xl border bg-card p-4 shadow-sm">
                    <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-base font-black">Column filters</h2>
                            <p class="text-sm text-muted-foreground">Filter the session table by any column.</p>
                        </div>
                        <Button v-if="hasColumnFilters" type="button" variant="outline" size="sm" @click="clearColumnFilters">
                            Clear filters
                        </Button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <label class="grid gap-1 text-sm font-semibold">
                            Session
                            <input
                                v-model="columnFilters.session"
                                type="text"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Filter by session"
                            />
                        </label>

                        <FormSelectField
                            id="session-branch-filter"
                            v-model="columnFilters.branch"
                            label="Branch"
                            :options="branchFilterOptions"
                            placeholder="All branches"
                            search-placeholder="Search branch..."
                        />

                        <FormSelectField
                            id="session-group-filter"
                            v-model="columnFilters.group"
                            label="Group"
                            :options="groupFilterOptions"
                            placeholder="All groups"
                            search-placeholder="Search group..."
                        />

                        <FormSelectField
                            id="session-coach-filter"
                            v-model="columnFilters.coach"
                            label="Coach"
                            :options="coachFilterOptions"
                            placeholder="All coaches"
                            search-placeholder="Search coach..."
                        />

                        <label class="grid gap-1 text-sm font-semibold">
                            Schedule
                            <input
                                v-model="columnFilters.schedule"
                                type="text"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Filter by date or time"
                            />
                        </label>

                        <FormSelectField
                            id="session-status-filter"
                            v-model="columnFilters.status"
                            label="Status"
                            :options="statusFilterOptions"
                            placeholder="All statuses"
                            search-placeholder="Search status..."
                        />
                    </div>
                </section>

                <DataTable
                    title="Session lineup"
                    description="Use Edit to manage QR attendance, athlete attendance, and coach attendance. Create new sessions by creating weekly or one-day classes."
                    :columns="columns"
                    :rows="filteredRows"
                    action-label="Actions"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button as-child size="sm" variant="outline">
                                <Link v-if="sessionIdFromRow(row)" :href="sessionAttendance.url(sessionIdFromRow(row)!)">Edit</Link>
                            </Button>
                            <Button v-if="row.can_join" size="sm" variant="outline" @click="joinSession(row)">Join</Button>
                            <Button size="sm" variant="destructive" @click="removeSession(row)">Delete</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>
