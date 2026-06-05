<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption } from '@/types/management';
import type { TableColumn, TableRow } from '@/types/management';

const props = defineProps<{
    rows: TableRow[];
    total: number;
    currentPage: number;
    lastPage: number;
    perPage: number;
    actions: string[];
    contexts: string[];
    filters: {
        q: string;
        action: string;
        context: string;
        actor_email: string;
        per_page: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'User Activity Log', href: managementRoutes.activityLogs },
];

const columns: TableColumn[] = [
    { key: 'time', label: 'Time' },
    { key: 'actor', label: 'Actor' },
    { key: 'email', label: 'Email' },
    { key: 'action', label: 'Action' },
    { key: 'context', label: 'Context' },
    { key: 'description', label: 'Description' },
    { key: 'ip', label: 'IP Address' },
];

const actionOptions: SelectOption[] = [
    { value: '', label: 'All actions' },
    ...props.actions.map((action) => ({ value: action, label: action })),
];

const contextOptions: SelectOption[] = [
    { value: '', label: 'All contexts' },
    ...props.contexts.map((context) => ({ value: context, label: context })),
];

const perPageOptions: SelectOption[] = [
    { value: '25', label: '25' },
    { value: '50', label: '50' },
    { value: '100', label: '100' },
    { value: '200', label: '200' },
];

const filterForm = useForm({
    q: props.filters.q,
    action: props.filters.action,
    context: props.filters.context,
    actor_email: props.filters.actor_email,
    per_page: props.filters.per_page,
});

function applyFilters(page = 1) {
    router.get('/admin/activity-logs', {
        ...filterForm.data(),
        page,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function resetFilters() {
    filterForm.q = '';
    filterForm.action = '';
    filterForm.context = '';
    filterForm.actor_email = '';
    filterForm.per_page = '50';
    applyFilters(1);
}
</script>

<template>
    <Head title="User Activity Log" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Admin"
                title="Users activity log"
                description="Search, filter, and paginate account, attendance, payment, session, and event activities."
            />

            <div class="grid gap-4 md:grid-cols-3">
                <FormInputField id="log-search" v-model="filterForm.q" label="Search" placeholder="Action, context, description, actor, IP" />
                <FormSelectField id="log-action" v-model="filterForm.action" label="Action" :options="actionOptions" />
                <FormSelectField id="log-context" v-model="filterForm.context" label="Context" :options="contextOptions" />
                <FormInputField id="log-actor-email" v-model="filterForm.actor_email" label="Actor email" placeholder="email@domain.com" />
                <FormSelectField id="log-per-page" v-model="filterForm.per_page" label="Rows per page" :options="perPageOptions" />
                <div class="flex items-end gap-2">
                    <Button type="button" @click="applyFilters(1)">Apply</Button>
                    <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
                </div>
            </div>

            <DataTable
                title="Log entries"
                :description="`Total entries: ${props.total} | Page ${props.currentPage} of ${props.lastPage}`"
                :columns="columns"
                :rows="props.rows"
            />

            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" :disabled="props.currentPage <= 1" @click="applyFilters(props.currentPage - 1)">Previous</Button>
                <Button type="button" variant="outline" :disabled="props.currentPage >= props.lastPage" @click="applyFilters(props.currentPage + 1)">Next</Button>
            </div>
        </div>
    </AppLayout>
</template>

