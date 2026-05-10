<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminAccountManagementPanel from '@/components/admin/AdminAccountManagementPanel.vue';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AdminAccountRow } from '@/types/admin';
import type { BreadcrumbItem } from '@/types';
import BranchManagement from '@/components/admin/BranchManagement.vue';
import GroupManagement from '@/components/admin/GroupManagement.vue';
import type { Branch } from '@/types/branch';
import type { Group } from '@/types/group';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import PageSection from '@/components/shared/PageSection.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';

const props = defineProps<{
    users: AdminAccountRow[];
    branches?: Branch[];
    groups?: Group[];
    debugbar?: {
        enabled: boolean;
    };
    importResult?: {
        entity: string;
        imported: number;
        failed: number;
        errors: string[];
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Admin Panel', href: '/admin' },
];

function createBranch(payload: { name: string; location: string }) {
    router.post('/admin/branches', payload, { preserveScroll: true });
}

function updateBranch(payload: { id: string; name: string; location: string }) {
    router.put(`/admin/branches/${payload.id}`, payload, { preserveScroll: true });
}

function deleteBranch(id: string) {
    router.delete(`/admin/branches/${id}`, { preserveScroll: true });
}

function createGroup(payload: { name: string; description: string | null }) {
    router.post('/admin/groups', payload, { preserveScroll: true });
}

function updateGroup(payload: { id: string; name: string; description: string | null }) {
    router.put(`/admin/groups/${payload.id}`, payload, { preserveScroll: true });
}

function deleteGroup(id: string) {
    router.delete(`/admin/groups/${id}`, { preserveScroll: true });
}

const transferOptions = [
    { value: 'athletes', label: 'Athletes' },
    { value: 'payments', label: 'Payments' },
    { value: 'sessions', label: 'Sessions' },
    { value: 'attendance', label: 'Attendance' },
    { value: 'events', label: 'Events' },
    { value: 'event_registrations', label: 'Event Registrations' },
];

const transferForm = useForm({
    entity: 'athletes',
    file: null as File | null,
});

function onTransferFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    transferForm.file = target.files?.[0] ?? null;
}

function importCsv() {
    transferForm.post('/admin/data-transfer/import', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function exportCsv() {
    const entity = encodeURIComponent(transferForm.entity);
    window.location.href = `/admin/data-transfer/export?entity=${entity}`;
}

function downloadTemplate() {
    const entity = encodeURIComponent(transferForm.entity);
    window.location.href = `/admin/data-transfer/template?entity=${entity}`;
}
</script>

<template>
    <Head title="Admin Panel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex justify-end">
                <div class="flex gap-2">
                    <Button as-child variant="outline">
                        <Link :href="managementRoutes.coachParentManagement">Manage Coaches & Parents</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="managementRoutes.activityLogs">Open User Activity Log</Link>
                    </Button>
                </div>
            </div>
            <Alert v-if="!props.debugbar?.enabled" variant="default">
                <AlertTitle>Debugbar is not installed yet</AlertTitle>
                <AlertDescription>
                    The package is referenced in `composer.json`, but Laravel does not currently detect it. Run `composer install` or `composer require --dev barryvdh/laravel-debugbar` in the PHP environment you actually use to serve the app.
                </AlertDescription>
            </Alert>

            <PageSection title="Legacy Data Transfer" description="Import/export CSV files (Excel-compatible) to migrate data in bulk. Use template first, then import the filled file.">
                <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto_auto] md:items-end">
                    <FormSelectField id="transfer-entity" v-model="transferForm.entity" label="Dataset" :options="transferOptions" />
                    <div class="grid gap-2">
                        <label for="transfer-file" class="text-sm font-medium">CSV file</label>
                        <input id="transfer-file" type="file" accept=".csv,text/csv" class="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm" @change="onTransferFileChange">
                    </div>
                    <Button type="button" variant="outline" @click="downloadTemplate">Download template</Button>
                    <Button type="button" variant="outline" @click="exportCsv">Export current data</Button>
                    <Button type="button" :disabled="transferForm.processing || !transferForm.file" @click="importCsv">Import CSV</Button>
                </div>

                <div v-if="props.importResult" class="mt-4 rounded-lg border border-border/70 p-4 text-sm">
                    <p class="font-medium">Import result for {{ props.importResult.entity }}</p>
                    <p>Imported: {{ props.importResult.imported }} | Failed: {{ props.importResult.failed }}</p>
                    <ul v-if="props.importResult.errors?.length" class="mt-2 list-disc pl-5 text-destructive">
                        <li v-for="(error, index) in props.importResult.errors.slice(0, 10)" :key="index">{{ error }}</li>
                    </ul>
                </div>
            </PageSection>

            <AdminAccountManagementPanel :initial-users="users" />
            <BranchManagement
                :branches="branches ?? []"
                @create="createBranch"
                @update="updateBranch"
                @delete="deleteBranch"
            />
            <GroupManagement
                :groups="groups ?? []"
                @create="createGroup"
                @update="updateGroup"
                @delete="deleteGroup"
            />
        </div>
    </AppLayout>
</template>
