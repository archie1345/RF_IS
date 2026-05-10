<script setup lang="ts">
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    coachRows: TableRow[];
    parentRows: TableRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Coach & Parent Management', href: managementRoutes.coachParentManagement },
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

const showCoachModal = ref(false);
const showParentModal = ref(false);
const editingCoachId = ref<string | null>(null);
const editingParentId = ref<string | null>(null);

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
    <Head title="Coach & Parent Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Admin panel"
                title="Coach & Parent Management"
                description="View and edit coach/parent profile details. Create/delete accounts from Admin Panel only."
            />

            <div class="grid gap-6">
                <PageSection title="Coach Profiles" description="Edit existing coach profile details." />
                <DataTable title="Coach list" description="Profiles mapped to existing account emails." :columns="coachColumns" :rows="props.coachRows" action-label="Actions">
                    <template #row-actions="{ row }">
                        <Button type="button" size="sm" variant="outline" @click="openEditCoach(row)">Edit</Button>
                    </template>
                </DataTable>
            </div>

            <div class="grid gap-6">
                <PageSection title="Parent Profiles" description="Edit existing parent profile details." />
                <DataTable title="Parent list" description="Profiles mapped to existing account emails." :columns="parentColumns" :rows="props.parentRows" action-label="Actions">
                    <template #row-actions="{ row }">
                        <Button type="button" size="sm" variant="outline" @click="openEditParent(row)">Edit</Button>
                    </template>
                </DataTable>
            </div>
        </div>

        <FormModal :open="showCoachModal" max-width-class="max-w-2xl" @close="showCoachModal = false">
            <PageSection title="Edit coach profile" description="Update coach details only.">
                <form class="grid gap-4" @submit.prevent="saveCoach">
                    <FormSelectField id="coach-status" v-model="coachForm.status" label="Status" :options="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]" :error="coachForm.errors.status" />
                    <FormInputField id="coach-specialization" v-model="coachForm.specialization" label="Specialization" placeholder="Sparring, Poomsae, Conditioning" :error="coachForm.errors.specialization" />
                    <div class="grid gap-2">
                        <label for="coach-bio" class="text-sm font-medium">Bio</label>
                        <textarea id="coach-bio" v-model="coachForm.bio" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
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
                        <textarea id="parent-notes" v-model="parentForm.notes" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
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
