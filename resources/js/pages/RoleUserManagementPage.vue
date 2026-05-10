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
import { computed, ref } from 'vue';

type UserItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    certifications: Array<Record<string, unknown>>;
    achievements: Array<Record<string, unknown>>;
};

const props = defineProps<{ users: UserItem[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Role Users', href: managementRoutes.roleUsers },
];

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'roles', label: 'Roles' },
    { key: 'certifications', label: 'Certifications' },
    { key: 'achievements', label: 'Achievements' },
];

const rows = computed<TableRow[]>(() => props.users.map((user) => ({
    id: String(user.id),
    name: user.name,
    email: user.email,
    roles: user.roles.join(', '),
    certifications: String(user.certifications.length),
    achievements: String(user.achievements.length),
})));

const roleView = ref<'all' | 'admin' | 'coach' | 'parent' | 'athlete'>('all');

const filteredRows = computed<TableRow[]>(() => {
    if (roleView.value === 'all') return rows.value;
    const target = roleView.value;
    return rows.value.filter((row) => String(row.roles).toLowerCase().split(', ').includes(target));
});

const selectedUserId = ref('');
const selectedUserLabel = ref('');
const showCertModal = ref(false);
const showAchievementModal = ref(false);

const certForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
    notes: '',
});

const achievementForm = useForm({
    championship_name: '',
    medal: 'NONE',
    location: '',
    event_date: '',
    class_name: '',
    division: '',
    category: '',
    notes: '',
});

function addCertification() {
    if (!selectedUserId.value) return;
    certForm.post(`/role-users/${selectedUserId.value}/certifications`, {
        preserveScroll: true,
        onSuccess: () => {
            showCertModal.value = false;
            certForm.reset();
            certForm.cert_type = 'BELT';
        },
    });
}

function addAchievement() {
    if (!selectedUserId.value) return;
    achievementForm.post(`/role-users/${selectedUserId.value}/achievements`, {
        preserveScroll: true,
        onSuccess: () => {
            showAchievementModal.value = false;
            achievementForm.reset();
            achievementForm.medal = 'NONE';
        },
    });
}

function pickUser(row: TableRow) {
    selectedUserId.value = String(row.id);
    selectedUserLabel.value = `${row.name} (${row.email})`;
}

function openCert(row: TableRow) {
    pickUser(row);
    showCertModal.value = true;
}

function openAchievement(row: TableRow) {
    pickUser(row);
    showAchievementModal.value = true;
}
</script>

<template>
    <Head title="Role Users" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection title="Role User Management" description="One account row per user, even with multiple roles. Use row actions to add certifications and achievements directly to that user." />

            <div class="grid gap-4 md:grid-cols-4">
                <FormSelectField
                    id="role-view"
                    v-model="roleView"
                    label="Role page"
                    :options="[
                        { value: 'all', label: 'All users' },
                        { value: 'admin', label: 'Admin users' },
                        { value: 'coach', label: 'Coach users' },
                        { value: 'parent', label: 'Parent users' },
                        { value: 'athlete', label: 'Athlete users' },
                    ]"
                />
            </div>

            <DataTable title="Users" :description="`Role view: ${roleView.toUpperCase()} | Normalized users with no duplication.`" :columns="columns" :rows="filteredRows" action-label="Actions">
                <template #row-actions="{ row }">
                    <div class="flex gap-2 justify-end">
                        <Button size="sm" variant="outline" @click="openCert(row)">Add certification</Button>
                        <Button size="sm" variant="outline" @click="openAchievement(row)">Add achievement</Button>
                    </div>
                </template>
            </DataTable>
        </div>
        <FormModal :open="showCertModal" max-width-class="max-w-xl" @close="showCertModal = false">
            <PageSection title="Add Certification" :description="selectedUserLabel">
                <form class="grid gap-3" @submit.prevent="addCertification">
                    <FormSelectField id="cert-type" v-model="certForm.cert_type" label="Type" :options="[{ value: 'BELT', label: 'Belt' }, { value: 'REFEREE', label: 'Referee' }, { value: 'TRAINER', label: 'Trainer' }]" />
                    <FormInputField id="cert-title" v-model="certForm.title" label="Title" />
                    <FormInputField id="cert-issuer" v-model="certForm.issuer" label="Issuer" />
                    <FormInputField id="cert-date" v-model="certForm.certified_at" label="Certified at" type="date" />
                    <FormInputField id="cert-expires" v-model="certForm.expires_at" label="Expires at" type="date" />
                    <FormInputField id="cert-notes" v-model="certForm.notes" label="Notes" />
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="certForm.processing">Save</Button>
                        <Button type="button" variant="outline" @click="showCertModal = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
        <FormModal :open="showAchievementModal" max-width-class="max-w-xl" @close="showAchievementModal = false">
            <PageSection title="Add Achievement" :description="selectedUserLabel">
                <form class="grid gap-3" @submit.prevent="addAchievement">
                    <FormInputField id="ach-name" v-model="achievementForm.championship_name" label="Championship name" />
                    <FormSelectField id="ach-medal" v-model="achievementForm.medal" label="Medal" :options="[{ value: 'GOLD', label: 'Gold' }, { value: 'SILVER', label: 'Silver' }, { value: 'BRONZE', label: 'Bronze' }, { value: 'NONE', label: 'None' }]" />
                    <FormInputField id="ach-location" v-model="achievementForm.location" label="Location" />
                    <FormInputField id="ach-date" v-model="achievementForm.event_date" label="Date" type="date" />
                    <FormInputField id="ach-class" v-model="achievementForm.class_name" label="Class name" />
                    <FormInputField id="ach-division" v-model="achievementForm.division" label="Division" />
                    <FormInputField id="ach-category" v-model="achievementForm.category" label="Category" />
                    <FormInputField id="ach-notes" v-model="achievementForm.notes" label="Notes" />
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="achievementForm.processing">Save</Button>
                        <Button type="button" variant="outline" @click="showAchievementModal = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
