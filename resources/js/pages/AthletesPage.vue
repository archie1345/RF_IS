<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import ResourceTablePanel from '@/components/shared/ResourceTablePanel.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import {
    athleteRosterBaseColumns,
    athleteRosterTrailingColumns,
    coachRosterColumns,
} from '@/pages/profiles/profileRosterConfig';
import { dashboard } from '@/routes';
import { index as usersIndex, show as userShow } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        metrics?: Metric[];
        rows?: TableRow[];
        branches?: SelectOption[];
        groups?: SelectOption[];
        athletes?: SelectOption[];
        coachRows?: TableRow[];
        canViewSensitiveIdentifiers?: boolean;
    }>(),
    {
        metrics: () => [],
        rows: () => [],
        branches: () => [],
        groups: () => [],
        athletes: () => [],
        coachRows: () => [],
        canViewSensitiveIdentifiers: false,
    },
);

const athleteBranchFilter = ref('');
const athleteGroupFilter = ref('');
const athleteStatusFilter = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Data Atlet', href: usersIndex.url() },
];

const athleteColumns: TableColumn[] = [...athleteRosterBaseColumns, ...athleteRosterTrailingColumns];

const coachColumns: TableColumn[] = coachRosterColumns;

const athleteRows = computed(() =>
    props.rows.filter((row) => {
        const branchMatches = !athleteBranchFilter.value || String(row.branch_id ?? '') === athleteBranchFilter.value;
        const groupMatches = !athleteGroupFilter.value || String(row.group_id ?? '') === athleteGroupFilter.value;
        const statusText =
            typeof row.status === 'object' && row.status !== null && 'text' in row.status
                ? String(row.status.text)
                : String(row.status ?? '');
        const statusMatches = !athleteStatusFilter.value || statusText.toLowerCase() === athleteStatusFilter.value;

        return branchMatches && groupMatches && statusMatches;
    }),
);

function getUserId(row: TableRow): number | string | null {
    return routeId(row.user_id) ?? routeId(row.id);
}

function viewProfile(row: TableRow) {
    const userId = getUserId(row);

    if (userId === null) {
        console.error('Invalid user ID', row);
        return;
    }

    router.visit(userShow.url(String(userId)));
}
</script>

<template>
    <Head title="Data Atlet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ResourceTablePanel
                eyebrow="User Directory"
                title="User directory workspace"
                description="View and edit athlete and coach profiles."
                table-title="Current athlete roster"
                table-description="Live athlete data backed by the application database."
                :columns="athleteColumns"
                :rows="athleteRows"
                action-label="Actions"
                searchable
                search-placeholder="Search athletes by name, email, branch, group, or status"
                :show-create="false"
            >
                <template #stats>
                    <div class="grid gap-3 md:grid-cols-3">
                        <FormSelectField
                            id="athlete-branch-filter"
                            v-model="athleteBranchFilter"
                            label="Filter by branch"
                            :options="[{ value: '', label: 'All branches' }, ...props.branches]"
                        />
                        <FormSelectField
                            id="athlete-group-filter"
                            v-model="athleteGroupFilter"
                            label="Filter by group"
                            :options="[{ value: '', label: 'All groups' }, ...props.groups]"
                        />
                        <FormSelectField
                            id="athlete-status-filter"
                            v-model="athleteStatusFilter"
                            label="Filter by status"
                            :options="[
                                { value: '', label: 'All statuses' },
                                { value: 'active', label: 'Active' },
                                { value: 'profile incomplete', label: 'Profile incomplete' },
                            ]"
                        />
                    </div>
                </template>
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="viewProfile(row)">View Profile</Button>
                    </ActionButtonsRow>
                </template>
            </ResourceTablePanel>
            <ResourceTablePanel
                :columns="coachColumns"
                :rows="props.coachRows"
                table-title="Coach profiles"
                table-description="Live coach profile data backed by the application database."
                action-label="Actions"
                searchable
                search-placeholder="Search coaches by name, email, status, or specialization"
                :show-create="false"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="viewProfile(row)">View Profile</Button>
                    </ActionButtonsRow>
                </template>
            </ResourceTablePanel>
        </div>
    </AppLayout>
</template>
