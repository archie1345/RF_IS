<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/management';

const props = defineProps<{
    isAdmin: boolean;
    rows: TableRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Announcements', href: managementRoutes.announcements },
];

const columns: TableColumn[] = [
    { key: 'title', label: 'Title' },
    { key: 'message', label: 'Message' },
    { key: 'target', label: 'Audience' },
    { key: 'status', label: 'Status' },
    { key: 'author', label: 'Author' },
    { key: 'published', label: 'Published' },
];

const form = useForm({
    title: '',
    message: '',
    target_role: 'ALL',
    publish_at: '',
    expire_at: '',
});

function submit() {
    form.post('/announcements', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.target_role = 'ALL';
        },
    });
}
</script>

<template>
    <Head title="Announcements" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection title="Announcements" description="Important club updates, schedules, and reminders in one place." />

            <PageSection v-if="props.isAdmin" title="Post an announcement" description="Write the message once, choose who should see it, and publish. Scheduling is optional.">
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <FormInputField id="ann-title" v-model="form.title" label="Title" placeholder="Example: Training schedule update" required :error="form.errors.title" />
                    <FormSelectField id="ann-target" v-model="form.target_role" label="Who should see this?" :options="[{ value: 'ALL', label: 'Everyone' }, { value: 'ADMIN', label: 'Admins only' }, { value: 'COACH', label: 'Coaches only' }, { value: 'PARENT', label: 'Parents only' }, { value: 'ATHLETE', label: 'Athletes only' }]" required help="Leave this as Everyone unless the message is private to one role." :error="form.errors.target_role" />
                    <div class="grid gap-2 md:col-span-2">
                        <label for="ann-message" class="text-sm font-medium">Message</label>
                        <textarea id="ann-message" v-model="form.message" rows="4" required placeholder="Write the announcement exactly as members should read it." class="rounded-lg border border-input bg-background px-3 py-2 text-sm leading-6" />
                        <p v-if="form.errors.message" class="text-sm text-destructive">{{ form.errors.message }}</p>
                    </div>
                    <details class="md:col-span-2 rounded-lg border border-border p-3">
                        <summary class="cursor-pointer text-sm font-medium">Schedule or expiry date</summary>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <FormInputField id="ann-publish" v-model="form.publish_at" label="Publish later" type="datetime-local" help="Leave empty to publish now." :error="form.errors.publish_at" />
                            <FormInputField id="ann-expire" v-model="form.expire_at" label="Hide after" type="datetime-local" help="Leave empty to keep it visible." :error="form.errors.expire_at" />
                        </div>
                    </details>
                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Publishing...' : 'Publish announcement' }}</Button>
                    </div>
                </form>
            </PageSection>

            <DataTable title="Announcement list" :description="props.isAdmin ? 'Admins see every current, scheduled, and expired announcement.' : 'Only announcements meant for your account are shown here.'" :columns="columns" :rows="props.rows" empty-text="No announcements yet." searchable />
        </div>
    </AppLayout>
</template>
