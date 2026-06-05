<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    Metric,
    SelectOption,
    TableColumn,
    TableRow,
} from '@/types/management';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Sessions', href: managementRoutes.sessions },
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

const editForm = useForm({
    title: '',
    branch_id: '',
    group_id: '',
    location: '',
    session_date: '',
    start_time: '',
    end_time: '',
    status: 'DRAFT',
});

const editingSessionId = ref<number | null>(null);
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

function startEdit(row: TableRow) {
    editingSessionId.value = Number(row.session_id);
    editForm.title = String(row.session ?? '');
    editForm.branch_id = String(row.branch_id ?? '');
    editForm.group_id = row.group_id ? String(row.group_id) : '';
    editForm.location = String(row.location ?? '');
    editForm.session_date = String(row.session_date ?? '');
    editForm.start_time = String(row.start_time ?? '');
    editForm.end_time = String(row.end_time ?? '');
    editForm.status = String(row.status_value ?? 'DRAFT');
    showSessionForm.value = true;
}

function submitEdit() {
    if (!editingSessionId.value) return;
    editForm.put(`/sessions/${editingSessionId.value}`, {
        onSuccess: () => {
            editingSessionId.value = null;
            editForm.reset();
            showSessionForm.value = false;
        },
    });
}

function cancelEdit() {
    editingSessionId.value = null;
    editForm.reset();
    showSessionForm.value = false;
}

function openCreateSessionForm() {
    editingSessionId.value = null;
    editForm.reset();
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
            <Alert
                v-if="pendingDeleteSessionId"
                variant="destructive"
                class="shadow-sm"
            >
                <AlertTriangle class="size-4" />
                <AlertTitle>Delete this session?</AlertTitle>
                <AlertDescription>
                    <p>
                        This session will be removed from the training schedule.
                    </p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            @click="confirmDeleteSession"
                        >
                            Delete session
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="cancelDeleteSession"
                        >
                            Cancel
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>

            <PageSection
                title="Session"
                description="Schedule training sessions and keep the live coaching calendar synced."
            >
                <template #actions>
                    <Button type="button" @click="openCreateSessionForm"
                        >Schedule session</Button
                    >
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard
                        v-for="metric in props.metrics"
                        :key="metric.label"
                        v-bind="metric"
                    />
                </div>
            </PageSection>

            <div class="grid gap-6">
                <DataTable
                    title="Session lineup"
                    description="Training sessions persisted from the coach scheduling workflow."
                    :columns="columns"
                    :rows="props.rows"
                    action-label="Actions"
                >
                    <template #row-actions="{ row }">
                        <ActionButtonsRow>
                            <Button as-child size="sm" variant="outline">
                                <Link
                                    :href="`/sessions/${String(row.id).replace('SES-', '')}/attendance`"
                                    >Open</Link
                                >
                            </Button>
                            <Button
                                v-if="row.can_join"
                                size="sm"
                                variant="outline"
                                @click="joinSession(row)"
                                >Join</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                @click="startEdit(row)"
                                >Edit</Button
                            >
                            <Button
                                size="sm"
                                variant="destructive"
                                @click="removeSession(row)"
                                >Delete</Button
                            >
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </div>
        </div>

        <FormModal
            :open="showSessionForm"
            max-width-class="max-w-2xl"
            @close="cancelEdit"
        >
            <PageSection
                :title="editingSessionId ? 'Edit session' : 'Session draft'"
                description="Create or update a session and assign coaches, branch, date, and operational status."
            >
                <form
                    v-if="editingSessionId"
                    class="grid gap-4"
                    @submit.prevent="submitEdit"
                >
                    <FormInputField
                        id="edit-session-name"
                        v-model="editForm.title"
                        label="Session name"
                        placeholder="Junior sparring block"
                        :error="editForm.errors.title"
                    />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormSelectField
                            id="edit-session-group"
                            v-model="editForm.group_id"
                            label="Group"
                            :options="props.groups"
                            placeholder="All groups in branch"
                            :error="editForm.errors.group_id"
                        />
                        <FormSelectField
                            id="edit-session-branch"
                            v-model="editForm.branch_id"
                            label="Branch"
                            :options="props.branches"
                            :error="editForm.errors.branch_id"
                        />
                    </div>
                    <FormInputField
                        id="edit-session-location"
                        v-model="editForm.location"
                        label="Location"
                        placeholder="Hall A"
                        :error="editForm.errors.location"
                    />
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField
                            id="edit-session-date"
                            v-model="editForm.session_date"
                            label="Date"
                            type="date"
                            :error="editForm.errors.session_date"
                        />
                        <FormInputField
                            id="edit-session-start"
                            v-model="editForm.start_time"
                            label="Start time"
                            type="time"
                            :error="editForm.errors.start_time"
                        />
                        <FormInputField
                            id="edit-session-end"
                            v-model="editForm.end_time"
                            label="End time"
                            type="time"
                            :error="editForm.errors.end_time"
                        />
                    </div>
                    <FormSelectField
                        id="edit-session-status"
                        v-model="editForm.status"
                        label="Status"
                        :options="[
                            { value: 'DRAFT', label: 'Draft' },
                            { value: 'CONFIRMED', label: 'Confirmed' },
                            {
                                value: 'NEEDS_ASSISTANT',
                                label: 'Needs assistant',
                            },
                            { value: 'CANCELED', label: 'Canceled' },
                        ]"
                        :error="editForm.errors.status"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            class="w-full sm:w-auto"
                            :disabled="editForm.processing"
                            >Update session</Button
                        >
                        <Button
                            type="button"
                            class="w-full sm:w-auto"
                            variant="outline"
                            @click="cancelEdit"
                            >Cancel</Button
                        >
                    </div>
                </form>

                <form v-else class="grid gap-4" @submit.prevent="submit">
                    <FormInputField
                        id="session-name"
                        v-model="form.title"
                        label="Session name"
                        placeholder="Junior sparring block"
                        :error="form.errors.title"
                    />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormSelectField
                            id="session-group"
                            v-model="form.group_id"
                            label="Group"
                            :options="props.groups"
                            placeholder="All groups in branch"
                            :error="form.errors.group_id"
                        />
                        <FormSelectField
                            id="session-branch"
                            v-model="form.branch_id"
                            label="Branch"
                            :options="props.branches"
                            :error="form.errors.branch_id"
                        />
                    </div>
                    <FormInputField
                        id="session-location"
                        v-model="form.location"
                        label="Location"
                        placeholder="Hall A"
                        :error="form.errors.location"
                    />
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField
                            id="session-date"
                            v-model="form.session_date"
                            label="Date"
                            type="date"
                            :error="form.errors.session_date"
                        />
                        <FormInputField
                            id="session-start"
                            v-model="form.start_time"
                            label="Start time"
                            type="time"
                            :error="form.errors.start_time"
                        />
                        <FormInputField
                            id="session-end"
                            v-model="form.end_time"
                            label="End time"
                            type="time"
                            :error="form.errors.end_time"
                        />
                    </div>
                    <FormSelectField
                        id="session-status"
                        v-model="form.status"
                        label="Status"
                        :options="[
                            { value: 'DRAFT', label: 'Draft' },
                            { value: 'CONFIRMED', label: 'Confirmed' },
                            {
                                value: 'NEEDS_ASSISTANT',
                                label: 'Needs assistant',
                            },
                            { value: 'CANCELED', label: 'Canceled' },
                        ]"
                        :error="form.errors.status"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            class="w-full sm:w-auto"
                            :disabled="form.processing"
                            >Save schedule</Button
                        >
                        <Button
                            type="button"
                            class="w-full sm:w-auto"
                            variant="outline"
                            @click="cancelEdit"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
