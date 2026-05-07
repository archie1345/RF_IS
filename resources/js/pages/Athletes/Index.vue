<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import SearchableSelect from '@/components/mvp/SearchableSelect.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/mvp';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    branches: SelectOption[];
    groups: SelectOption[];
    athletes: SelectOption[];
    parents: SelectOption[];
    canViewSensitiveIdentifiers: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Athletes', href: managementRoutes.athletes },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'parent', label: 'Parent' },
    { key: 'branch', label: 'Branch' },
    { key: 'group', label: 'Group' },
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

const parentLinkForm = useForm({
    athlete_id: '',
    parent_id: '',
});

function submit() {
    form.post('/athletes', {
        onSuccess: () => form.reset(),
    });
}

function linkParent() {
    if (!parentLinkForm.athlete_id) {
        parentLinkForm.setError('athlete_id', 'Please select an athlete.');

        return;
    }

    parentLinkForm.post(`/athletes/${parentLinkForm.athlete_id}/parent-link`, {
        onSuccess: () => parentLinkForm.reset(),
    });
}
</script>

<template>
    <Head title="Athletes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection eyebrow="Admin module" title="Athlete CRUD workspace" description="Manage athlete records with a live roster and a form that writes directly to the database.">
                <template #actions>
                    <Button type="button">New athlete</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <DataTable title="Current athlete roster" description="Live athlete data backed by the application database." :columns="columns" :rows="props.rows" />

                <PageSection title="Athlete intake form" description="Create a new athlete user and athlete profile in one step.">
                    <form class="grid gap-4" @submit.prevent="submit">
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
                                <select id="athlete-gender" v-model="form.gender" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                    <option value="MALE">Male</option>
                                    <option value="FEMALE">Female</option>
                                </select>
                                <InputError :message="form.errors.gender" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="athlete-bday">Birth date</Label>
                                <Input id="athlete-bday" v-model="form.bday" type="date" />
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
                                <select id="athlete-geup" v-model="form.geup" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                    <option v-for="option in geupOptions" :key="option" :value="option">
                                        {{ option.replace('_', ' ') }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.geup" />
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="athlete-height">Height (cm)</Label>
                                <Input id="athlete-height" v-model="form.height_cm" type="number" min="0" step="0.1" />
                                <InputError :message="form.errors.height_cm" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="athlete-weight">Weight (kg)</Label>
                                <Input id="athlete-weight" v-model="form.weight_kg" type="number" min="0" step="0.1" />
                                <InputError :message="form.errors.weight_kg" />
                            </div>
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
                            <Button type="submit" :disabled="form.processing">Save athlete</Button>
                            <Button variant="outline" as-child>
                                <Link :href="managementRoutes.dashboard">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </PageSection>
            </div>

            <PageSection title="Parent-child linking" description="Connect an existing parent account to an athlete profile so the parent can switch into that child account.">
                <form class="grid gap-4 md:grid-cols-[1fr_1fr_auto]" @submit.prevent="linkParent">
                    <div class="grid gap-2">
                        <Label for="link-athlete">Athlete</Label>
                        <SearchableSelect
                            v-model="parentLinkForm.athlete_id"
                            :options="props.athletes"
                            placeholder="Select athlete"
                            title="Select athlete"
                            description="Search the athlete roster and choose the child profile to link."
                            search-placeholder="Search athlete"
                            empty-text="No athlete matches that search."
                        />
                        <InputError :message="parentLinkForm.errors.athlete_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="link-parent">Parent</Label>
                        <SearchableSelect
                            v-model="parentLinkForm.parent_id"
                            :options="props.parents"
                            placeholder="Select parent"
                            title="Select parent"
                            description="Search the parent list and choose the account to connect."
                            search-placeholder="Search parent"
                            empty-text="No parent matches that search."
                        />
                        <InputError :message="parentLinkForm.errors.parent_id" />
                    </div>

                    <div class="flex items-end">
                        <Button type="submit" :disabled="parentLinkForm.processing">Link parent</Button>
                    </div>
                </form>
            </PageSection>
        </div>
    </AppLayout>
</template>
