<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckSquare2, Download, FileSpreadsheet, Square } from 'lucide-vue-next';
import { computed, ref, watch, reactive } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface ExportOption {
    value: string;
    label: string;
}

interface ExportField {
    key: string;
    label: string;
}

interface ExportDataset {
    key: string;
    label: string;
    description: string;
    fields: ExportField[];
    statusOptions: ExportOption[];
    roleOptions: ExportOption[];
    supportsDateRange: boolean;
    supportsDeleted: boolean;
    supportsBranch?: boolean;
    supportsGroup?: boolean;
}

const props = defineProps<{
    datasets: ExportDataset[];
    branches?: ExportOption[];
    groups?: ExportOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Akun Pengguna', href: '/admin' },
    { title: 'Export Data Excel', href: '/admin/data-export' },
];

const datasetKeys = ref<string[]>([props.datasets[0]?.key ?? '']);
const selectedFields = reactive<Record<string, string[]>>({});
const status = ref<string[]>([]);
const role = ref<string[]>([]);
const dateFrom = ref('');
const dateTo = ref('');
const singleSheet = ref(false);
const includeDeleted = ref(false);
const validationMessage = ref('');
const group = ref<string[]>([]);
const branch = ref<string[]>([]);

const activeDatasets = computed(() => props.datasets.filter((dataset) => datasetKeys.value.includes(dataset.key)));
const datasetOptions = computed(() => props.datasets.map((dataset) => ({ value: dataset.key, label: dataset.label })));

const activeStatusOptions = computed<ExportOption[]>(() => {
    if (activeDatasets.value.length === 1) {
        const datasetKey = activeDatasets.value[0].key;
        if (datasetKey === 'training_sessions') {
            return [
                { value: 'DRAFT', label: 'Draft' },
                { value: 'CONFIRMED', label: 'Confirmed' },
                { value: 'NEEDS_ASSISTANT', label: 'Needs assistant' },
                { value: 'CANCELED', label: 'Canceled' },
            ];
        }

        if (datasetKey === 'events') {
            return [
                { value: 'SCHEDULED', label: 'Scheduled' },
                { value: 'ONGOING', label: 'Ongoing' },
                { value: 'COMPLETED', label: 'Completed' },
                { value: 'CANCELED', label: 'Canceled' },
            ];
        }

        return activeDatasets.value[0].statusOptions ?? [];
    }
    
    return [];
});

const activeRoleOptions = computed<ExportOption[]>(() => {
    if (activeDatasets.value.length === 1) {
        return activeDatasets.value[0].roleOptions ?? [];
    }
    return [];
});

const supportsDateRange = computed(() => activeDatasets.value.some(d => d.supportsDateRange));
const supportsDeleted = computed(() => activeDatasets.value.some(d => d.supportsDeleted));
const supportsBranch = computed(() => activeDatasets.value.some(d => d.supportsBranch));
const supportsGroup = computed(() => activeDatasets.value.some(d => d.supportsGroup));

const totalSelectedFields = computed(() => Object.values(selectedFields).flat().length);
const totalAvailableFields = computed(() => activeDatasets.value.reduce((acc, curr) => acc + curr.fields.length, 0));

function resetDatasetSelection(): void {
    const activeKeys = new Set(activeDatasets.value.map(d => d.key));
    
    for (const key of Object.keys(selectedFields)) {
        if (!activeKeys.has(key)) {
            delete selectedFields[key];
        }
    }
    
    activeDatasets.value.forEach(dataset => {
        if (!selectedFields[dataset.key]) {
            selectedFields[dataset.key] = dataset.fields.map((field) => field.key);
        }
    });

    // Only clear status and role if they are no longer valid for the current selection
    if (activeStatusOptions.value.length === 0) {
        status.value = [];
    }
    if (activeRoleOptions.value.length === 0) {
        role.value = [];
    }
    
    if (!supportsBranch.value) branch.value = [];
    if (!supportsGroup.value) group.value = [];
    if (!supportsDateRange.value) {
        dateFrom.value = '';
        dateTo.value = '';
    }
    if (!supportsDeleted.value) {
        includeDeleted.value = false;
    }
    
    validationMessage.value = '';
}

function toggleField(datasetKey: string, field: string): void {
    if (!selectedFields[datasetKey]) {
        selectedFields[datasetKey] = [];
    }
    const index = selectedFields[datasetKey].indexOf(field);
    if (index >= 0) {
        selectedFields[datasetKey].splice(index, 1);
    } else {
        selectedFields[datasetKey].push(field);
    }
    validationMessage.value = '';
}

function selectAllFields(): void {
    activeDatasets.value.forEach(dataset => {
        selectedFields[dataset.key] = dataset.fields.map((field) => field.key);
    });
    validationMessage.value = '';
}

function clearFields(): void {
    activeDatasets.value.forEach(dataset => {
        selectedFields[dataset.key] = [];
    });
}

function downloadExport(): void {
    if (datasetKeys.value.length === 0 || totalSelectedFields.value === 0) {
        validationMessage.value = 'Select at least one dataset and one column before creating the Excel file.';
        return;
    }

    const params = new URLSearchParams();
    datasetKeys.value.forEach(key => params.append('datasets[]', key));
    
    Object.entries(selectedFields).forEach(([key, fields]) => {
        fields.forEach(field => params.append(`fields[${key}][]`, field));
    });

    status.value.forEach(val => params.append('status[]', val));
    role.value.forEach(val => params.append('role[]', val));
    branch.value.forEach(val => params.append('branch[]', val));
    group.value.forEach(val => params.append('group[]', val));

    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (includeDeleted.value) params.set('include_deleted', '1');
    if (singleSheet.value) params.set('single_sheet', '1');

    window.location.href = `/admin/data-export/download?${params.toString()}`;
}

watch(datasetKeys, resetDatasetSelection, { immediate: true });
</script>

<template>
    <Head title="Export Data Excel" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Admin-only reporting"
                title="Build a custom Excel export"
                description="Choose system datasets, select only the columns you need, apply optional filters, then download a formatted Excel workbook."
            >
            </PageSection>

            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.28fr)]">
                <section class="grid content-start gap-5 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-300">
                            <FileSpreadsheet class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Dataset and filters</h2>
                            <p class="text-sm leading-5 text-muted-foreground">
                                Filters apply before the workbook is generated.
                            </p>
                        </div>
                    </div>

                    <FormSelectField
                        id="export-dataset"
                        v-model="datasetKeys"
                        label="Data source"
                        :options="datasetOptions"
                        required
                        multiple
                    />

                    <div v-if="activeDatasets.length > 0" class="flex flex-col gap-2">
                        <p v-for="dataset in activeDatasets" :key="dataset.key" class="rounded-lg bg-muted/40 p-3 text-sm leading-6 text-muted-foreground">
                            <span class="font-semibold">{{ dataset.label }}:</span> {{ dataset.description }}
                        </p>
                    </div>

                    <FormSelectField
                        v-if="activeStatusOptions.length"
                        id="export-status"
                        v-model="status"
                        label="Status filter"
                        :options="activeStatusOptions"
                        placeholder="All statuses"
                        multiple
                    />

                    <FormSelectField
                        v-if="activeRoleOptions.length"
                        id="export-role"
                        v-model="role"
                        label="Role filter"
                        :options="activeRoleOptions"
                        placeholder="All roles"
                        multiple
                    />

                    <FormSelectField
                        v-if="supportsBranch && props.branches?.length"
                        id="export-branch"
                        v-model="branch"
                        label="Branch filter"
                        :options="props.branches"
                        placeholder="All branches"
                        multiple
                    />

                    <FormSelectField
                        v-if="supportsGroup && props.groups?.length"
                        id="export-group"
                        v-model="group"
                        label="Group filter"
                        :options="props.groups"
                        placeholder="All groups"
                        multiple
                    />

                    <div v-if="supportsDateRange" class="grid gap-4 sm:grid-cols-2">
                        <FormInputField id="export-date-from" v-model="dateFrom" label="Date from" type="date" />
                        <FormInputField
                            id="export-date-to"
                            v-model="dateTo"
                            label="Date to"
                            type="date"
                            :min="dateFrom"
                        />
                    </div>

                    <label
                        v-if="supportsDeleted"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border bg-background p-4 text-sm"
                    >
                        <input v-model="includeDeleted" type="checkbox" class="mt-0.5 size-4 rounded border-input" />
                        <span>
                            <span class="block font-semibold">Include deleted records</span>
                            <span class="mt-1 block leading-5 text-muted-foreground"
                                >Adds soft-deleted records when the selected dataset supports them.</span
                            >
                        </span>
                    </label>
                    <label
                        v-if="activeDatasets.length > 0"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border bg-background p-4 text-sm"
                    >
                        <input v-model="singleSheet" type="checkbox" class="mt-0.5 size-4 rounded border-input" />
                        <span>
                            <span class="block font-semibold">Combine into single sheet</span>
                            <span class="mt-1 block leading-5 text-muted-foreground">Places all selected columns from multiple sources into a single sheet instead of multiple tabs.</span>
                        </span>
                    </label>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <p class="text-xs leading-5 text-muted-foreground">
                            The export is generated from a server-side query and downloaded as .xlsx. Each selected dataset will be placed in its own sheet. Large exports are processed in chunks.
                        </p>
                        <Button
                            type="button"
                            class="gap-2"
                            :disabled="totalSelectedFields === 0"
                            @click="downloadExport"
                        >
                            <Download class="size-4" /> Download selected data
                        </Button>
                    </div>
                </section>

                <section class="min-w-0 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                        <div>
                            <h2 class="font-semibold">Columns</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ totalSelectedFields }} of {{ totalAvailableFields }} columns selected.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" size="sm" variant="outline" class="gap-2" @click="selectAllFields">
                                <CheckSquare2 class="size-4" /> Select all
                            </Button>
                            <Button type="button" size="sm" variant="outline" class="gap-2" @click="clearFields">
                                <Square class="size-4" /> Clear
                            </Button>
                        </div>
                    </div>

                    <div v-if="activeDatasets.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        Select at least one data source to choose columns.
                    </div>

                    <div v-for="dataset in activeDatasets" :key="dataset.key" class="mt-6">
                        <h3 class="mb-3 font-medium text-sm text-muted-foreground uppercase tracking-wider">{{ dataset.label }} Columns</h3>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="field in dataset.fields"
                                :key="field.key"
                                type="button"
                                class="flex min-w-0 items-center gap-3 rounded-lg border p-3 text-left text-sm transition hover:bg-muted/30"
                                :class="
                                    (selectedFields[dataset.key] || []).includes(field.key)
                                        ? 'border-primary/50 bg-primary/5'
                                        : 'border-border bg-background'
                                "
                                @click="toggleField(dataset.key, field.key)"
                            >
                                <CheckSquare2
                                    v-if="(selectedFields[dataset.key] || []).includes(field.key)"
                                    class="size-4 shrink-0 text-primary"
                                />
                                <Square v-else class="size-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 font-medium break-words">{{ field.label }}</span>
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="validationMessage"
                        class="mt-4 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ validationMessage }}
                    </p>

                    
                </section>
            </div>
        </div>
    </AppLayout>
</template>
