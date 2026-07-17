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
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    attendance as sessionAttendance,
    destroy as sessionDestroy,
    index as sessionsIndex,
    join as sessionJoin,
    store as sessionsStore,
} from '@/routes/sessions';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

type SessionVisibility = 'upcoming' | 'past' | 'all';

const props = defineProps<{
    metrics: Metric[];
    filters: {
        visibility: SessionVisibility;
        past_count: number;
        upcoming_count: number;
        all_count: number;
    };
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Sessions', href: sessionsIndex.url() },
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

const visibilityOptions: Array<{ value: SessionVisibility; label: string; countKey: keyof typeof props.filters }> = [
    { value: 'upcoming', label: 'Current & future', countKey: 'upcoming_count' },
    { value: 'past', label: 'Past', countKey: 'past_count' },
    { value: 'all', label: 'All', countKey: 'all_count' },
];

function setVisibility(visibility: SessionVisibility) {
    router.get(
        sessionsIndex.url(),
        { visibility },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function submit() {
    form.post(sessionsStore.url(), {
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
    router.delete(sessionDestroy.url(id), { preserveScroll: true });
}

function joinSession(row: TableRow) {
    const id = Number(row.session_id);
    if (!id) return;
    router.post(sessionJoin.url(id));
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

            <PageSection
                title="Session"
                description="Schedule training sessions and keep the live coaching calendar synced. Past sessions are hidden by default."
            >
                <template #actions>
                    <Button type="button" @click="openCreateSessionForm">Schedule session</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6">
                <section class="rounded-2xl border bg-card p-4 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-black">Session visibility</h2>
                            <p class="text-sm text-muted-foreground">
                                Default view shows today and future sessions. Use this to review history.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="option in visibilityOptions"
                                :key="option.value"
                                type="button"
                                size="sm"
                                :variant="props.filters.visibility === option.value ? 'default' : 'outline'"
                                @click="setVisibility(option.value)"
                            >
                                {{ option.label }} ({{ props.filters[option.countKey] }})
                            </Button>
                        </div>
                    </div>
                </section>

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
                                <Link :href="sessionAttendance.url(String(row.id).replace('SES-', ''))">Edit</Link>
                            </Button>
                            <Button v-if="row.can_join" size="sm" variant="outline" @click="joinSession(row)"
                                >Join</Button
                            >
                            <Button size="sm" variant="destructive" @click="removeSession(row)">Delete</Button>
                        </ActionButtonsRow>
                    </template>
                </DataTable>
            </div>
        </div>

        <FormModal :open="showSessionForm" max-width-class="max-w-2xl" @close="cancelForm">
            <PageSection
                title="Session draft"
                description="Create a new training session. Existing sessions are edited from their session attendance page."
            >
                <form class="grid gap-4" @submit.prevent="submit">
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
                            { value: 'NEEDS_ASSISTANT', label: 'Needs assistant' },
                            { value: 'CANCELED', label: 'Canceled' },
                        ]"
                        :error="form.errors.status"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing"
                            >Save schedule</Button
                        >
                        <Button type="button" class="w-full sm:w-auto" variant="outline" @click="cancelForm"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
