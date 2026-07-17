<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as announcementsIndex, store as announcementsStore } from '@/routes/announcements';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{ isAdmin: boolean; rows: TableRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Announcements', href: announcementsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'title', label: 'Title' },
    { key: 'message', label: 'Message' },
    { key: 'target', label: 'Audience' },
    { key: 'status', label: 'Status' },
    { key: 'author', label: 'Author' },
    { key: 'published', label: 'Published' },
];

const targetRoleOptions = [
    { value: 'ALL', label: 'Everyone' },
    { value: 'ADMIN', label: 'Admins only' },
    { value: 'COACH', label: 'Coaches only' },
    { value: 'PARENT', label: 'Parents only' },
    { value: 'ATHLETE', label: 'Athletes only' },
];

const targetRoleLabels: Record<string, string> = {
    ALL: 'Everyone',
    ADMIN: 'Admins only',
    COACH: 'Coaches only',
    PARENT: 'Parents only',
    ATHLETE: 'Athletes only',
};

const form = useForm({ title: '', message: '', target_role: 'ALL', publish_at: '', expire_at: '' });

const audienceLabel = computed(() => targetRoleLabels[form.target_role] ?? 'Everyone');

function submit() {
    form.post(announcementsStore.url(), {
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
            <PageSection
                title="Announcements"
                description="Important club updates, schedules, and reminders in one place."
            />

            <div v-if="props.isAdmin" class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <PageSection
                    title="Post an announcement"
                    description="Write the message once, choose the audience, and publish it."
                >
                    <form class="grid gap-4" @submit.prevent="submit">
                        <FormInputField
                            id="ann-title"
                            v-model="form.title"
                            label="Title"
                            placeholder="Training schedule update"
                            required
                            :error="form.errors.title"
                        />
                        <FormSelectField
                            id="ann-target"
                            v-model="form.target_role"
                            label="Audience"
                            :options="targetRoleOptions"
                            required
                            :error="form.errors.target_role"
                        />
                        <div class="grid gap-2">
                            <label for="ann-message" class="text-sm font-medium">Message</label>
                            <textarea
                                id="ann-message"
                                v-model="form.message"
                                rows="6"
                                required
                                placeholder="Write the announcement here."
                                class="rounded-2xl border border-input bg-background px-4 py-3 text-sm leading-6 shadow-sm focus:ring-2 focus:ring-ring/25 focus:outline-none"
                            />
                            <p v-if="form.errors.message" class="text-sm text-destructive">{{ form.errors.message }}</p>
                        </div>
                        <details class="rounded-2xl border border-border p-4">
                            <summary class="cursor-pointer text-sm font-medium">Schedule or expiry date</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <FormInputField
                                    id="ann-publish"
                                    v-model="form.publish_at"
                                    label="Publish later"
                                    type="datetime-local"
                                    help="Leave empty to publish now."
                                    :error="form.errors.publish_at"
                                />
                                <FormInputField
                                    id="ann-expire"
                                    v-model="form.expire_at"
                                    label="Hide after"
                                    type="datetime-local"
                                    help="Leave empty to keep it visible."
                                    :error="form.errors.expire_at"
                                />
                            </div>
                        </details>
                        <div>
                            <Button type="submit" :disabled="form.processing">{{
                                form.processing ? 'Publishing...' : 'Publish announcement'
                            }}</Button>
                        </div>
                    </form>
                </PageSection>

                <section class="rounded-2xl border bg-gradient-to-br from-primary/10 via-card to-card p-6 shadow-sm">
                    <p class="text-xs font-black tracking-[0.2em] text-primary uppercase">Live preview</p>
                    <div class="mt-4 rounded-3xl border bg-background p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{
                                audienceLabel
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                form.publish_at ? 'Scheduled' : 'Publish now'
                            }}</span>
                        </div>
                        <h2 class="mt-4 text-2xl font-black">{{ form.title || 'Announcement title' }}</h2>
                        <p class="mt-3 text-sm leading-6 whitespace-pre-line text-muted-foreground">
                            {{ form.message || 'Your announcement preview appears here.' }}
                        </p>
                    </div>
                </section>
            </div>

            <DataTable
                title="Announcement list"
                :description="
                    props.isAdmin ? 'Admins see all announcements.' : 'Visible announcements for this account.'
                "
                :columns="columns"
                :rows="props.rows"
                empty-text="No announcements yet."
                searchable
            />
        </div>
    </AppLayout>
</template>
