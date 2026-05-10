<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormNumberStepperField from '@/components/forms/FormNumberStepperField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import FormModal from '@/components/shared/FormModal.vue';
import ManagementTablePanel from '@/components/shared/ManagementTablePanel.vue';
import SearchableSelect from '@/components/shared/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
    athletes: SelectOption[];
    parents: SelectOption[];
    canViewSensitiveIdentifiers: boolean;
}>();

const showNewAthleteForm = ref(false);
const editingAthleteId = ref<number | null>(null);
const isLoadingAthlete = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Athletes', href: managementRoutes.athletes },
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

function submit() {
    const options = {
        onSuccess: () => {
            form.reset();
            editingAthleteId.value = null;
            showNewAthleteForm.value = false;
        },
    };

    if (editingAthleteId.value) {
        form.put(`/athletes/user/${editingAthleteId.value}`, options);
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

function getUserId(row: TableRow) {
    return Number(row.user_id ?? 0);
}

function toNumericString(value: unknown) {
    const numeric = String(value ?? '').match(/-?\d+(\.\d+)?/)?.[0] ?? '';
    return numeric;
}

async function editAthlete(row: TableRow) {
    const userId = getUserId(row);
    if (!userId) return;
    isLoadingAthlete.value = true;
    showNewAthleteForm.value = true;
    form.clearErrors();
    form.height_cm = toNumericString(row.height_cm);
    form.weight_kg = toNumericString(row.weight_kg);
    try {
        const response = await fetch(`/athletes/user/${userId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to load athlete (${response.status})`);
        }

        const data = await response.json();
        editingAthleteId.value = userId;
        form.name = String(data.name ?? '');
        form.email = String(data.email ?? '');
        form.gender = String(data.gender ?? 'MALE');
        form.bday = String(data.bday ?? '');
        form.phone = String(data.phone ?? '');
        form.height_cm = String(data.height_cm ?? form.height_cm ?? '');
        form.weight_kg = String(data.weight_kg ?? form.weight_kg ?? '');
        form.alamat = String(data.alamat ?? '');
        form.branch_id = String(data.branch_id ?? '');
        form.group_id = String(data.group_id ?? '');
        form.geup = String(data.geup ?? 'GEUP_10');
        form.parent_id = String(data.parent_id ?? '');
        form.nik = String(data.nik ?? '');
        form.bpjs = String(data.bpjs ?? '');
    } catch {
        closeAthleteForm();
    } finally {
        isLoadingAthlete.value = false;
    }
}
</script>

<template>
    <Head title="Athletes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ManagementTablePanel
                eyebrow="Athlete Management"
                title="Athlete Management workspace"
                description="View and edit athlete profiles. Create/delete accounts from Admin Panel only."
                table-title="Current athlete roster"
                table-description="Live athlete data backed by the application database."
                :columns="columns"
                :rows="props.rows"
                action-label="Actions"
                :show-create="false"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="editAthlete(row)">Edit</Button>
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
    </AppLayout>
</template>

