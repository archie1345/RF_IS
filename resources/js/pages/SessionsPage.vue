<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

type SessionVisibility = 'upcoming' | 'past' | 'all';
type SessionFilters = {
    visibility: SessionVisibility;
    past_count: number;
    upcoming_count: number;
    all_count: number;
};

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
    { value: 'upcoming', label: 'Current & future', countKey: 'upcoming_count' },
    { value: 'past', label: 'Past', countKey: 'past_count' },
    { value: 'all', label: 'All', countKey: 'all_count' },
];

function sessionIdFromRow(row: TableRow): number | null {
    const id = Number(row.session_id ?? String(row.id).replace('SES-', ''));

    return Number.isFinite(id) && id > 0 ? id : null;
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

                <DataTable
                    title="Session lineup"
                    description="Use Edit to manage QR attendance, athlete attendance, and coach attendance. Create new sessions by creating weekly or one-day classes."
                    :columns="columns"
                    :rows="props.rows"
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
