<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormNumberStepperField from '@/components/forms/FormNumberStepperField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import SearchableSelect from '@/components/shared/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { appRoutes } from '@/data/routes';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Components Playground', href: appRoutes.componentsPlayground },
];

const columns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'status', label: 'Status' },
];

const rows: TableRow[] = [
    { id: '1', name: 'Reusable modal', status: { kind: 'badge', text: 'Ready', tone: 'success' } },
    { id: '2', name: 'Reusable select', status: { kind: 'badge', text: 'Ready', tone: 'success' } },
];

const showModal = ref(false);
const form = ref({
    name: '',
    branch: '',
    weight: '',
    coach: '',
});
const options = [
    { value: 'jaksel', label: 'Jakarta Selatan' },
    { value: 'depok', label: 'Depok' },
];
</script>

<template>
    <Head title="Components Playground" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                title="Components Playground"
                description="Dedicated page to test reusable UI components in one place."
            >
                <template #actions>
                    <Button type="button" @click="showModal = true">Open test modal</Button>
                </template>
            </PageSection>

            <DataTable
                title="Shared table sample"
                description="Simple table sample for regression testing."
                :columns="columns"
                :rows="rows"
            />
        </div>

        <FormModal :open="showModal" max-width-class="max-w-xl" @close="showModal = false">
            <PageSection title="Form components" description="Reusable form controls test bed.">
                <div class="grid gap-4">
                    <FormInputField id="play-name" v-model="form.name" label="Name" placeholder="Type here" />
                    <FormSelectField
                        id="play-branch"
                        v-model="form.branch"
                        label="Branch"
                        :options="options"
                        placeholder="Select branch"
                    />
                    <SearchableSelect v-model="form.coach" :options="options" placeholder="Search select sample" />
                    <FormNumberStepperField
                        id="play-weight"
                        v-model="form.weight"
                        label="Weight (kg)"
                        :min="0"
                        :step="0.1"
                    />
                    <Button type="button" variant="outline" @click="showModal = false">Close</Button>
                </div>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
