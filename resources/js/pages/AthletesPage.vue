<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormNumberStepperField from '@/components/forms/FormNumberStepperField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import FormModal from '@/components/shared/FormModal.vue';
import ManagementTablePanel from '@/components/shared/ManagementTablePanel.vue';
import PageSection from '@/components/shared/PageSection.vue';
import SearchableSelect from '@/components/shared/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { computed, ref, } from 'vue';

const props = withDefaults(defineProps<{
    metrics?: Metric[];
    rows?: TableRow[];
    branches?: SelectOption[];
    groups?: SelectOption[];
    athletes?: SelectOption[];
    parents?: SelectOption[];
    coachRows?: TableRow[];
    parentRows?: TableRow[];
    canViewSensitiveIdentifiers?: boolean;
}>(), {
    metrics: () => [],
    rows: () => [],
    branches: () => [],
    groups: () => [],
    athletes: () => [],
    parents: () => [],
    coachRows: () => [],
    parentRows: () => [],
    canViewSensitiveIdentifiers: false,
});

const showNewAthleteForm = ref(false);
const showCoachModal = ref(false);
const showParentModal = ref(false);
const editingAthleteId = ref<number | null>(null);
const isLoadingAthlete = ref(false);
const editingCoachId = ref<string | null>(null);
const editingParentId = ref<string | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Users', href: managementRoutes.athletes },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'account_email', label: 'Account Email' },
    { key: 'parent', label: 'Parent' },
    { key: 'branch', label: 'Branch' },
    { key: 'group', label: 'Group' },
    { key: 'height_cm', label: 'Height' },
    { key: 'weight_kg', label: 'Weight' },
    ...(props.canViewSensitiveIdentifiers ? [{ key: 'nik', label: 'NIK' }, { key: 'bpjs', label: 'BPJS' }] : []),
    { key: 'geup', label: 'Geup' },
    { key: 'status', label: 'Status' },
];

const coachColumns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Account role' },
    { key: 'status', label: 'Status' },
    { key: 'specialization', label: 'Specialization' },
];

const parentColumns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Account role' },
    { key: 'relation', label: 'Relation' },
    { key: 'occupation', label: 'Occupation' },
];

const geupOptions = ['GEUP_10', 'GEUP_9', 'GEUP_8', 'GEUP_7', 'GEUP_6', 'GEUP_5', 'GEUP_4', 'GEUP_3', 'GEUP_2', 'GEUP_1', 'DAN'];
const genderOptions = [
    { value: 'MALE', label: 'Male' },
    { value: 'FEMALE', label: 'Female' },
];
const geupSelectOptions = computed(() => geupOptions.map((option) => ({
    value: option,
    label: option.replace('_', ' '),
})));

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

function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            editingAthleteId.value = null;
            showNewAthleteForm.value = false;
        },
    };

    if (editingAthleteId.value) {
        form.put(`/users/user/${editingAthleteId.value}`, options);
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

function toNumericString(value: unknown) {
    const numeric = String(value ?? '').match(/-?\d+(\.\d+)?/)?.[0] ?? '';
    return numeric;
}

function getUserId(row: TableRow) {
    const rawValue =
        row.user_id ??
        row.id ??
        row.athlete_id;

    return Number(toNumericString(rawValue));
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
        coachForm.put(`/coach-parent-management/coaches/${editingCoachId.value}`, {
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
        parentForm.put(`/coach-parent-management/parents/${editingParentId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showParentModal.value = false;
            },
        });
        return;
    }
}
</script>

<template>
    <Head title="Athletes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ManagementTablePanel
                eyebrow="Users Management"
                title="Users Management workspace"
                description="View and edit athlete, Coach, Parent profiles. Create/delete accounts from Admin Panel only."
                table-title="Current athlete roster"
                table-description="Live athlete data backed by the application database."
                :columns="columns"
                :rows="props.rows"
                action-label="Actions"
                :show-create="false"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="viewProfile(row)">View Profile</Button>
                    </ActionButtonsRow>
                </template>
            </ManagementTablePanel>
            <ManagementTablePanel
                :columns="coachColumns"
                :rows="props.coachRows"
                table-title="Coach profiles"
                table-description="Live coach profile data backed by the application database."
                action-label="Actions"
                :show-create="false"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="openEditCoach(row)">Edit Coach</Button>
                    </ActionButtonsRow>
                </template>
            </ManagementTablePanel>
            <ManagementTablePanel
                :columns="parentColumns"
                :rows="props.parentRows"
                table-title="Parent profiles"
                table-description="Live parent profile data backed by the application database."
                action-label="Actions"
                :show-create="false"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="openEditParent(row)">Edit Parent</Button>
                    </ActionButtonsRow>
                </template>
            </ManagementTablePanel> 
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
                    <FormSelectField id="athlete-gender" v-model="form.gender" label="Gender" :options="genderOptions" :error="form.errors.gender" />
                    <FormInputField id="athlete-bday" v-model="form.bday" label="Birth date" type="date" :error="form.errors.bday" />
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="athlete-phone">Phone</Label>
                        <Input id="athlete-phone" v-model="form.phone" placeholder="0812..." />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <FormSelectField id="athlete-geup" v-model="form.geup" label="Geup" :options="geupSelectOptions" :error="form.errors.geup" />
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
                        placeholder="Link later"
                        title="Select parent"
                        description="Search parent accounts and link one to this athlete."
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
                    <Button
                        type="button"
                        class="w-full sm:w-auto"
                        variant="outline"
                        @click="closeAthleteForm"
                    >
                        Cancel
                    </Button>
                </div>
            </form>
            <div v-else class="py-10 text-center text-sm text-muted-foreground">Loading athlete details...</div>
        </FormModal>

        <FormModal :open="showCoachModal" max-width-class="max-w-2xl" @close="showCoachModal = false">
            <PageSection title="Edit coach profile" description="Update coach details only.">
                <form class="grid gap-4" @submit.prevent="saveCoach">
                    <FormSelectField id="coach-status" v-model="coachForm.status" label="Status" :options="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]" :error="coachForm.errors.status" />
                    <FormInputField id="coach-specialization" v-model="coachForm.specialization" label="Specialization" placeholder="Sparring, Poomsae, Conditioning" :error="coachForm.errors.specialization" />
                    <div class="grid gap-2">
                        <label for="coach-bio" class="text-sm font-medium">Bio</label>
                        <textarea id="coach-bio" v-model="coachForm.bio" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm"></textarea>
                        <p v-if="coachForm.errors.bio" class="text-sm text-destructive">{{ coachForm.errors.bio }}</p>
                    </div>
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="coachForm.processing">Update</Button>
                        <Button type="button" variant="outline" @click="showCoachModal = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showParentModal" max-width-class="max-w-2xl" @close="showParentModal = false">
            <PageSection title="Edit parent profile" description="Update parent details only.">
                <form class="grid gap-4" @submit.prevent="saveParent">
                    <FormSelectField id="parent-relation" v-model="parentForm.relation" label="Relation" :options="[{ value: 'father', label: 'Father' }, { value: 'mother', label: 'Mother' }, { value: 'guardian', label: 'Guardian' }]" :error="parentForm.errors.relation" />
                    <FormInputField id="parent-occupation" v-model="parentForm.occupation" label="Occupation" placeholder="Occupation" :error="parentForm.errors.occupation" />
                    <div class="grid gap-2">
                        <label for="parent-notes" class="text-sm font-medium">Notes</label>
                        <textarea id="parent-notes" v-model="parentForm.notes" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm"></textarea>
                        <p v-if="parentForm.errors.notes" class="text-sm text-destructive">{{ parentForm.errors.notes }}</p>
                    </div>
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="parentForm.processing">Update</Button>
                        <Button type="button" variant="outline" @click="showParentModal = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>