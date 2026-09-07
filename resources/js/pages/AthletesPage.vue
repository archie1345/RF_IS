<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import InputError from '@/components/InputError.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import ResourceTablePanel from '@/components/shared/ResourceTablePanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import {
    athleteRosterBaseColumns,
    athleteRosterTrailingColumns,
    coachRosterColumns,
    parentRosterColumns,
} from '@/pages/profiles/profileRosterConfig';
import { dashboard } from '@/routes';
import { sync as parentChildrenSync } from '@/routes/parents/children';
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
        parents?: SelectOption[];
        coachRows?: TableRow[];
        parentRows?: TableRow[];
        canViewSensitiveIdentifiers?: boolean;
    }>(),
    {
        metrics: () => [],
        rows: () => [],
        branches: () => [],
        groups: () => [],
        athletes: () => [],
        parents: () => [],
        coachRows: () => [],
        parentRows: () => [],
        canViewSensitiveIdentifiers: false,
    },
);

const showParentChildrenModal = ref(false);
const editingParentChildrenId = ref<number | string | null>(null);
const editingParentChildrenName = ref('');
const childSearch = ref('');
const athleteBranchFilter = ref('');
const athleteGroupFilter = ref('');
const athleteStatusFilter = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Data Atlet', href: usersIndex.url() },
];

const athleteColumns: TableColumn[] = [...athleteRosterBaseColumns, ...athleteRosterTrailingColumns];

const coachColumns: TableColumn[] = coachRosterColumns;

const parentColumns: TableColumn[] = parentRosterColumns;

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

const parentChildrenForm = useForm({
    athlete_ids: [] as string[],
});

const filteredChildOptions = computed(() => {
    const query = childSearch.value.trim().toLowerCase();

    if (!query) {
        return props.athletes;
    }

    return props.athletes.filter((option) => option.label.toLowerCase().includes(query));
});

function getUserId(row: TableRow): number | string | null {
    return routeId(row.user_id) ?? routeId(row.id);
}

function viewProfile(row: TableRow) {
    const userId = getUserId(row);

    if (userId === null) {
        console.error('Invalid user ID', row);
        return;
    }

    router.visit(userShow.url(userId));
}

function openLinkChildren(row: TableRow) {
    const parentId = getUserId(row);

    if (parentId === null) {
        console.error('Invalid parent ID', row);
        return;
    }

    editingParentChildrenId.value = parentId;
    editingParentChildrenName.value = String(row.name ?? 'Parent');
    parentChildrenForm.athlete_ids = String(row.child_ids ?? '')
        .split(',')
        .map((id) => id.trim())
        .filter(Boolean);
    parentChildrenForm.clearErrors();
    childSearch.value = '';
    showParentChildrenModal.value = true;
}

function closeParentChildrenModal() {
    showParentChildrenModal.value = false;
    editingParentChildrenId.value = null;
    editingParentChildrenName.value = '';
    parentChildrenForm.athlete_ids = [];
    parentChildrenForm.clearErrors();
    childSearch.value = '';
}

function isChildSelected(value: string | number) {
    return parentChildrenForm.athlete_ids.includes(String(value));
}

function toggleChild(value: string | number) {
    const id = String(value);

    if (isChildSelected(id)) {
        parentChildrenForm.athlete_ids = parentChildrenForm.athlete_ids.filter((childId) => childId !== id);
        return;
    }

    parentChildrenForm.athlete_ids = [...parentChildrenForm.athlete_ids, id];
}

function saveParentChildren() {
    if (!editingParentChildrenId.value) return;

    parentChildrenForm.put(parentChildrenSync.url(String(editingParentChildrenId.value)), {
        preserveScroll: true,
        onSuccess: closeParentChildrenModal,
    });
}
</script>

<template>
    <Head title="Data Atlet" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ResourceTablePanel
                eyebrow="User Directory"
                title="User directory workspace"
                description="View and edit athlete, Coach, Parent profiles."
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
            <ResourceTablePanel
                :columns="parentColumns"
                :rows="props.parentRows"
                table-title="Parent profiles"
                table-description="Live parent profile data backed by the application database."
                action-label="Actions"
                searchable
                search-placeholder="Search parents by name, email, relation, occupation, or children"
                :show-create="false"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="viewProfile(row)">View Profile</Button>
                        <Button size="sm" variant="outline" :disabled="!getUserId(row)" @click="openLinkChildren(row)"
                            >Link Children</Button
                        >
                    </ActionButtonsRow>
                </template>
            </ResourceTablePanel>
        </div>

        <FormModal :open="showParentChildrenModal" max-width-class="max-w-2xl" @close="closeParentChildrenModal">
            <PageSection
                title="Link children"
                :description="`Select every athlete child linked to ${editingParentChildrenName}. Leaving all unchecked is allowed.`"
            >
                <form class="grid gap-4" @submit.prevent="saveParentChildren">
                    <div class="grid gap-2">
                        <Label for="child-search">Search athletes</Label>
                        <Input id="child-search" v-model="childSearch" placeholder="Search by athlete name" />
                    </div>

                    <div class="max-h-80 overflow-y-auto rounded-xl border border-border/70 bg-card p-2">
                        <label
                            v-for="athlete in filteredChildOptions"
                            :key="String(athlete.value)"
                            class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-muted"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-input"
                                :checked="isChildSelected(athlete.value)"
                                @change="toggleChild(athlete.value)"
                            />
                            <span class="min-w-0 flex-1 truncate">{{ athlete.label }}</span>
                        </label>

                        <div
                            v-if="filteredChildOptions.length === 0"
                            class="px-3 py-8 text-center text-sm text-muted-foreground"
                        >
                            No athletes match that search.
                        </div>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        {{ parentChildrenForm.athlete_ids.length }} child{{
                            parentChildrenForm.athlete_ids.length === 1 ? '' : 'ren'
                        }}
                        selected.
                    </p>
                    <InputError :message="parentChildrenForm.errors.athlete_ids" />

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="parentChildrenForm.processing"
                            >Save children</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full sm:w-auto"
                            @click="closeParentChildrenModal"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
