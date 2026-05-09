<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import FormNumberStepperField from '@/components/forms/FormNumberStepperField.vue';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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
const birthDateInput = ref<HTMLInputElement | null>(null);

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
        form.put(`/athletes/${editingAthleteId.value}`, options);
        return;
    }

    form.post('/athletes', options);
}

function openCreateAthleteForm() {
    editingAthleteId.value = null;
    form.reset();
    form.clearErrors();
    showNewAthleteForm.value = true;
}

function closeAthleteForm() {
    showNewAthleteForm.value = false;
    editingAthleteId.value = null;
    isLoadingAthlete.value = false;
    form.reset();
    form.clearErrors();
}

function openBirthDatePicker() {
    const input = birthDateInput.value as (HTMLInputElement & { showPicker?: () => void }) | null;
    if (!input) return;
    if (typeof input.showPicker === 'function') {
        input.showPicker();
        return;
    }
    input.focus();
    input.click();
}

function getAthleteId(row: TableRow) {
    const raw = row.athlete_id ?? row.id;
    const normalized = String(raw ?? '').replace(/^ATH-/, '');
    return Number(normalized);
}

function toNumericString(value: unknown) {
    const numeric = String(value ?? '').match(/-?\d+(\.\d+)?/)?.[0] ?? '';
    return numeric;
}

async function editAthlete(row: TableRow) {
    const id = getAthleteId(row);
    if (!id) return;
    isLoadingAthlete.value = true;
    showNewAthleteForm.value = true;
    form.clearErrors();
    form.height_cm = toNumericString(row.height_cm);
    form.weight_kg = toNumericString(row.weight_kg);
    try {
        const response = await fetch(`/athletes/${id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Failed to load athlete (${response.status})`);
        }

        const data = await response.json();
        editingAthleteId.value = id;
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

function deleteAthlete(row: TableRow) {
    const id = getAthleteId(row);
    if (!id) return;
    if (!confirm('Delete this athlete?')) return;
    router.delete(`/athletes/${id}`);
}
</script>

<template>
    <Head title="Athletes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <ManagementTablePanel
                eyebrow="Athlete Management"
                title="Athlete Management workspace"
                description="Manage athlete records."
                create-label="New athlete"
                table-title="Current athlete roster"
                table-description="Live athlete data backed by the application database."
                :columns="columns"
                :rows="props.rows"
                action-label="Actions"
                @create="openCreateAthleteForm"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button size="sm" variant="outline" @click="editAthlete(row)">Edit</Button>
                        <Button size="sm" variant="destructive" @click="deleteAthlete(row)">Delete</Button>
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
                            <div class="grid gap-2">
                                <Label for="athlete-gender">Gender</Label>
                                <select
                                    id="athlete-gender"
                                    v-model="form.gender"
                                    class="flex h-9 w-full appearance-none rounded-md border border-input bg-background px-3 py-1 pr-8 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                >
                                    <option value="MALE">Male</option>
                                    <option value="FEMALE">Female</option>
                                </select>
                                <InputError :message="form.errors.gender" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="athlete-bday">Birth date</Label>
                                <div class="flex gap-2">
                                    <input
                                        id="athlete-bday"
                                        ref="birthDateInput"
                                        v-model="form.bday"
                                        type="date"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 aria-invalid:border-destructive h-9 w-full min-w-0 rounded-md border bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px]"
                                    >
                                    <Button type="button" variant="outline" @click="openBirthDatePicker">Pick</Button>
                                </div>
                                <InputError :message="form.errors.bday" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="athlete-phone">Phone</Label>
                                <Input id="athlete-phone" v-model="form.phone" placeholder="0812..." />
                                <InputError :message="form.errors.phone" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="athlete-geup">Geup</Label>
                                <select
                                    id="athlete-geup"
                                    v-model="form.geup"
                                    class="flex h-9 w-full appearance-none rounded-md border border-input bg-background px-3 py-1 pr-8 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                >
                                    <option v-for="option in geupOptions" :key="option" :value="option">
                                        {{ option.replace('_', ' ') }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.geup" />
                            </div>
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

