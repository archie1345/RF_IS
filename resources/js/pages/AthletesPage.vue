<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormNumberStepperField from '@/components/forms/FormNumberStepperField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import InputError from '@/components/InputError.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import ResourceTablePanel from '@/components/shared/ResourceTablePanel.vue';
import SearchableSelect from '@/components/shared/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { appRoutes } from '@/data/routes';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    athleteRosterBaseColumns,
    athleteRosterTrailingColumns,
    coachRosterColumns,
    genderOptions,
    geupOptions,
    parentRosterColumns,
} from '@/pages/profiles/profileRosterConfig';
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

const showNewAthleteForm = ref(false);
const showCoachModal = ref(false);
const showParentModal = ref(false);
const showParentChildrenModal = ref(false);
const editingAthleteId = ref<number | null>(null);
const isLoadingAthlete = ref(false);
const editingCoachId = ref<string | null>(null);
const editingParentId = ref<string | null>(null);
const editingParentChildrenId = ref<string | null>(null);
const editingParentChildrenName = ref('');
const childSearch = ref('');
const athleteBranchFilter = ref('');
const athleteGroupFilter = ref('');
const athleteStatusFilter = ref('');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard },
    { title: 'Users', href: appRoutes.athletes },
];

const athleteColumns: TableColumn[] = [
    ...athleteRosterBaseColumns,
    ...athleteRosterTrailingColumns,
];

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
const geupSelectOptions = computed(() =>
    geupOptions.map((option) => ({
        value: option,
        label: option.replace('_', ' '),
    })),
);

const form = useForm({
    name: '',
    email: '',
    gender: 'MALE',
    bday: '',
    phone: '',
    height_cm: '',
    weight_kg: '',
    alamat: '',
    branch_id: '',
    group_id: '',
    geup: 'GEUP_10',
    parent_id: '',
    nik: '',
    bpjs: '',
});

const coachForm = useForm({
    status: 'active',
    specialization: '',
    bio: '',
});

const parentForm = useForm({
    relation: 'guardian',
    occupation: '',
    notes: '',
});

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

function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            editingAthleteId.value = null;
            showNewAthleteForm.value = false;
        },
    };

    if (editingAthleteId.value) {
        form.put(`/athlete/user/${editingAthleteId.value}`, options);
        return;
    }
}

function closeAthleteForm() {
    showNewAthleteForm.value = false;
    editingAthleteId.value = null;
    isLoadingAthlete.value = false;
    form.reset();
    form.clearErrors();
}

function getUserId(row: TableRow): string {
    return String(row.user_id ?? row.id ?? row.athlete_id ?? '');
}

function getParentId(row: TableRow): string {
    return String(row.parent_id ?? row.id ?? '');
}

function viewProfile(row: TableRow) {
    const userId = getUserId(row);

    if (!userId) {
        console.error('Invalid user ID', row);
        return;
    }

    router.visit(`/users/${userId}`);
}

function openEditCoach(row: TableRow) {
    editingCoachId.value = String(row.id);
    coachForm.status = String(row.status ?? 'active');
    coachForm.specialization = String(row.specialization ?? '').replace('-', '');
    coachForm.bio = '';
    showCoachModal.value = true;
}

function saveCoach() {
    if (editingCoachId.value) {
        coachForm.put(`/users/${editingCoachId.value}/coach-profile`, {
            preserveScroll: true,
            onSuccess: () => {
                showCoachModal.value = false;
            },
        });
        return;
    }
}

function openEditParent(row: TableRow) {
    editingParentId.value = String(row.id);
    parentForm.relation = String(row.relation ?? 'guardian');
    parentForm.occupation = String(row.occupation ?? '').replace('-', '');
    parentForm.notes = '';
    showParentModal.value = true;
}

function saveParent() {
    if (editingParentId.value) {
        parentForm.put(`/users/${editingParentId.value}/parent-profile`, {
            preserveScroll: true,
            onSuccess: () => {
                showParentModal.value = false;
            },
        });
        return;
    }
}

function openLinkChildren(row: TableRow) {
    const parentId = getParentId(row);

    if (!parentId) {
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

    parentChildrenForm.put(`/parents/${editingParentChildrenId.value}/children`, {
        preserveScroll: true,
        onSuccess: closeParentChildrenModal,
    });
}
</script>

<template>
    <Head title="Athletes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ResourceTablePanel
                eyebrow="User Directory"
                title="User directory workspace"
                description="View and edit athlete, Coach, Parent profiles. Create/delete accounts from Admin Panel only."
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
                        <Button size="sm" variant="outline" @click="openEditCoach(row)">Edit</Button>
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
                        <Button size="sm" variant="outline" @click="openEditParent(row)">Edit</Button>
                        <Button size="sm" variant="outline" :disabled="!getParentId(row)" @click="openLinkChildren(row)"
                            >Link Children</Button
                        >
                    </ActionButtonsRow>
                </template>
            </ResourceTablePanel>
        </div>

        <FormModal :open="showNewAthleteForm" max-width-class="max-w-4xl" @close="closeAthleteForm">
            <form v-if="!isLoadingAthlete" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="athlete-name">Full name</Label>
                    <Input id="athlete-name" v-model="form.name" placeholder="Enter athlete name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="athlete-email">Email</Label>
                    <Input id="athlete-email" v-model="form.email" type="email" placeholder="athlete@example.com" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <FormSelectField
                        id="athlete-gender"
                        v-model="form.gender"
                        label="Gender"
                        :options="genderOptions"
                        :error="form.errors.gender"
                    />
                    <FormInputField
                        id="athlete-bday"
                        v-model="form.bday"
                        label="Birth date"
                        type="date"
                        :error="form.errors.bday"
                    />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="athlete-phone">Phone</Label>
                        <Input id="athlete-phone" v-model="form.phone" placeholder="0812..." />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <FormSelectField
                        id="athlete-geup"
                        v-model="form.geup"
                        label="Geup"
                        :options="geupSelectOptions"
                        :error="form.errors.geup"
                    />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <FormNumberStepperField
                        id="athlete-height"
                        v-model="form.height_cm"
                        label="Height (cm)"
                        :min="0"
                        :step="0.1"
                        :error="form.errors.height_cm"
                    />
                    <FormNumberStepperField
                        id="athlete-weight"
                        v-model="form.weight_kg"
                        label="Weight (kg)"
                        :min="0"
                        :step="0.1"
                        :error="form.errors.weight_kg"
                    />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="athlete-branch">Branch</Label>
                        <SearchableSelect
                            v-model="form.branch_id"
                            :options="props.branches"
                            placeholder="Select branch"
                            title="Select branch"
                            description="Search branch options and choose the correct branch."
                            search-placeholder="Search branch"
                            empty-text="No branch matches that search."
                        />
                        <InputError :message="form.errors.branch_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="athlete-group">Group</Label>
                        <SearchableSelect
                            v-model="form.group_id"
                            :options="props.groups"
                            placeholder="Select group"
                            title="Select group"
                            description="Search group options and choose the correct training group."
                            search-placeholder="Search group"
                            empty-text="No group matches that search."
                        />
                        <InputError :message="form.errors.group_id" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="athlete-parent">Parent</Label>
                    <SearchableSelect
                        v-model="form.parent_id"
                        :options="props.parents"
                        placeholder="No parent linked"
                        title="Select parent"
                        description="Search parent accounts if this athlete should be linked to one. You can leave this empty."
                        search-placeholder="Search parent"
                        empty-text="No parent matches that search."
                    />
                    <InputError :message="form.errors.parent_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="athlete-address">Address</Label>
                    <Input id="athlete-address" v-model="form.alamat" placeholder="Street address" />
                    <InputError :message="form.errors.alamat" />
                </div>
                <div v-if="props.canViewSensitiveIdentifiers" class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="athlete-nik">NIK</Label>
                        <Input id="athlete-nik" v-model="form.nik" placeholder="3174..." />
                        <InputError :message="form.errors.nik" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="athlete-bpjs">BPJS</Label>
                        <Input id="athlete-bpjs" v-model="form.bpjs" placeholder="BPJS number" />
                        <InputError :message="form.errors.bpjs" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">
                        {{ editingAthleteId ? 'Update athlete' : 'Save athlete' }}
                    </Button>
                    <Button type="button" class="w-full sm:w-auto" variant="outline" @click="closeAthleteForm">
                        Cancel
                    </Button>
                </div>
            </form>
            <div v-else class="py-10 text-center text-sm text-muted-foreground">Loading athlete details...</div>
        </FormModal>

        <FormModal :open="showCoachModal" max-width-class="max-w-2xl" @close="showCoachModal = false">
            <PageSection title="Edit coach profile" description="Update coach details only.">
                <form class="grid gap-4" @submit.prevent="saveCoach">
                    <FormSelectField
                        id="coach-status"
                        v-model="coachForm.status"
                        label="Status"
                        :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                        ]"
                        :error="coachForm.errors.status"
                    />
                    <FormInputField
                        id="coach-specialization"
                        v-model="coachForm.specialization"
                        label="Specialization"
                        placeholder="Sparring, Poomsae, Conditioning"
                        :error="coachForm.errors.specialization"
                    />
                    <div class="grid gap-2">
                        <label for="coach-bio" class="text-sm font-medium">Bio</label>
                        <textarea
                            id="coach-bio"
                            v-model="coachForm.bio"
                            rows="3"
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                        <p v-if="coachForm.errors.bio" class="text-sm text-destructive">{{ coachForm.errors.bio }}</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="coachForm.processing">Update</Button>
                        <Button type="button" variant="outline" class="w-full sm:w-auto" @click="showCoachModal = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showParentModal" max-width-class="max-w-2xl" @close="showParentModal = false">
            <PageSection title="Edit parent profile" description="Update parent details only.">
                <form class="grid gap-4" @submit.prevent="saveParent">
                    <FormSelectField
                        id="parent-relation"
                        v-model="parentForm.relation"
                        label="Relation"
                        :options="[
                            { value: 'father', label: 'Father' },
                            { value: 'mother', label: 'Mother' },
                            { value: 'guardian', label: 'Guardian' },
                        ]"
                        :error="parentForm.errors.relation"
                    />
                    <FormInputField
                        id="parent-occupation"
                        v-model="parentForm.occupation"
                        label="Occupation"
                        placeholder="Occupation"
                        :error="parentForm.errors.occupation"
                    />
                    <div class="grid gap-2">
                        <label for="parent-notes" class="text-sm font-medium">Notes</label>
                        <textarea
                            id="parent-notes"
                            v-model="parentForm.notes"
                            rows="3"
                            class="rounded-lg border border-input bg-background px-3 py-2 text-sm"
                        ></textarea>
                        <p v-if="parentForm.errors.notes" class="text-sm text-destructive">
                            {{ parentForm.errors.notes }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="parentForm.processing">Update</Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full sm:w-auto"
                            @click="showParentModal = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

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
