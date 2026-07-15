<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import AppAlert from '@/components/shared/AppAlert.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { appRoutes } from '@/data/routes';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: appRoutes.dashboard },
    { title: 'Sessions', href: appRoutes.sessions },
];

const columns: TableColumn[] = [
    { key: 'session', label: 'Session' },
    { key: 'branch', label: 'Branch' },
    { key: 'group', label: 'Group' },
    { key: 'coach', label: 'Coach' },
    { key: 'schedule', label: 'Schedule' },
    { key: 'status', label: 'Status' },
];

const form = useForm({
    title: '',
    branch_id: '',
    group_id: '',
    location: '',
    session_date: '',
    start_time: '',
    end_time: '',
    status: 'DRAFT',
});

const showSessionForm = ref(false);
const pendingDeleteSessionId = ref<number | null>(null);

function submit() {
    form.post('/sessions', {
        onSuccess: () => {
            form.reset();
            showSessionForm.value = false;
        },
    });
}

function cancelForm() {
    form.reset();
    form.clearErrors();
    showSessionForm.value = false;
}

function openCreateSessionForm() {
    form.reset();
    form.clearErrors();
    showSessionForm.value = true;
}

function removeSession(row: TableRow) {
    const id = Number(row.session_id);
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
    router.delete(`/sessions/${id}`, { preserveScroll: true });
}

function joinSession(row: TableRow) {
    const id = Number(row.session_id);
    if (!id) return;
    router.post(`/sessions/${id}/join`);
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
                description="This session will be removed from the training schedule."
                :primary-action="{ label: 'Delete session', variant: 'destructive' }"
                :secondary-action="{ label: 'Cancel', variant: 'outline' }"
                @primary="confirmDeleteSession"
                @secondary="cancelDeleteSession"
            />

            <PageSection title="Session" description="Schedule training sessions and keep the live coaching calendar synced.">
                <template #actions>
                    <Button type="button" @click="openCreateSessionForm">Schedule session</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6">
                <DataTable
                    title="Session lineup"
                    description="Use Edit to manage schedule details, QR attendance, athlete attendance, and coach attendance in one place."
                    :columns="columns"
                    :rows="props.rows"
                    action-label="Actions"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/sessions/${String(row.id).replace('SES-', '')}/attendance`">Edit</Link>
                            </Button>
                            <Button v-if="row.can_join" size="sm" variant="outline" @click="joinSession(row)">Join</Button>
                            <Button size="sm" variant="destructive" @click="removeSession(row)">Delete</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </div>
        </div>

        <FormModal :open="showSessionForm" max-width-class="max-w-2xl" @close="cancelForm">
            <PageSection title="Session draft" description="Create a new training session. Existing sessions are edited from their session attendance page.">
                <form class="grid gap-4" @submit.prevent="submit">
                    <FormInputField id="session-name" v-model="form.title" label="Session name" placeholder="Junior sparring block" :error="form.errors.title" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormSelectField id="session-group" v-model="form.group_id" label="Group" :options="props.groups" placeholder="All groups in branch" :error="form.errors.group_id" />
                        <FormSelectField id="session-branch" v-model="form.branch_id" label="Branch" :options="props.branches" :error="form.errors.branch_id" />
                    </div>
                    <FormInputField id="session-location" v-model="form.location" label="Location" placeholder="Hall A" :error="form.errors.location" />
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField id="session-date" v-model="form.session_date" label="Date" type="date" :error="form.errors.session_date" />
                        <FormInputField id="session-start" v-model="form.start_time" label="Start time" type="time" :error="form.errors.start_time" />
                        <FormInputField id="session-end" v-model="form.end_time" label="End time" type="time" :error="form.errors.end_time" />
                    </div>
                    <FormSelectField
                        id="session-status"
                        v-model="form.status"
                        label="Status"
                        :options="[
                            { value: 'DRAFT', label: 'Draft' },
                            { value: 'CONFIRMED', label: 'Confirmed' },
                            { value: 'NEEDS_ASSISTANT', label: 'Needs assistant' },
                            { value: 'CANCELED', label: 'Canceled' },
                        ]"
                        :error="form.errors.status"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">Save schedule</Button>
                        <Button type="button" class="w-full sm:w-auto" variant="outline" @click="cancelForm">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
