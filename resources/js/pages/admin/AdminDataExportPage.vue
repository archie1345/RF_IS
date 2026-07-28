<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckSquare2, Download, FileSpreadsheet, Square } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
}

const props = defineProps<{
    datasets: ExportDataset[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Admin Panel', href: '/admin' },
    { title: 'Data Export', href: '/admin/data-export' },
];

const datasetKey = ref(props.datasets[0]?.key ?? '');
const selectedFields = ref<string[]>([]);
const status = ref('');
const role = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const includeDeleted = ref(false);
const validationMessage = ref('');

const activeDataset = computed(() => props.datasets.find((dataset) => dataset.key === datasetKey.value) ?? null);
const datasetOptions = computed(() => props.datasets.map((dataset) => ({ value: dataset.key, label: dataset.label })));
const activeStatusOptions = computed<ExportOption[]>(() => {
    if (datasetKey.value === 'training_sessions') {
        return [
            { value: 'DRAFT', label: 'Draft' },
            { value: 'CONFIRMED', label: 'Confirmed' },
            { value: 'NEEDS_ASSISTANT', label: 'Needs assistant' },
            { value: 'CANCELED', label: 'Canceled' },
        ];
    }

    if (datasetKey.value === 'events') {
        return [
            { value: 'SCHEDULED', label: 'Scheduled' },
            { value: 'ONGOING', label: 'Ongoing' },
            { value: 'COMPLETED', label: 'Completed' },
            { value: 'CANCELED', label: 'Canceled' },
        ];
    }

    return activeDataset.value?.statusOptions ?? [];
});
const allFieldsSelected = computed(
    () => Boolean(activeDataset.value?.fields.length) && selectedFields.value.length === activeDataset.value?.fields.length,
);

function resetDatasetSelection(): void {
    selectedFields.value = activeDataset.value?.fields.map((field) => field.key) ?? [];
    status.value = '';
    role.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    includeDeleted.value = false;
    validationMessage.value = '';
}

function toggleField(field: string): void {
    selectedFields.value = selectedFields.value.includes(field)
        ? selectedFields.value.filter((selected) => selected !== field)
        : [...selectedFields.value, field];
    validationMessage.value = '';
}

function selectAllFields(): void {
    selectedFields.value = activeDataset.value?.fields.map((field) => field.key) ?? [];
    validationMessage.value = '';
}

function clearFields(): void {
    selectedFields.value = [];
}

function downloadExport(): void {
    if (!activeDataset.value || selectedFields.value.length === 0) {
        validationMessage.value = 'Select at least one column before creating the Excel file.';
        return;
    }

    const params = new URLSearchParams({ dataset: activeDataset.value.key });
    selectedFields.value.forEach((field) => params.append('fields[]', field));

    if (status.value) params.set('status', status.value);
    if (role.value) params.set('role', role.value);
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (includeDeleted.value) params.set('include_deleted', '1');

    window.location.href = `/admin/data-export/download?${params.toString()}`;
}

watch(datasetKey, resetDatasetSelection, { immediate: true });
</script>

<template>
    <Head title="Data Export" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Admin-only reporting"
                title="Build a custom Excel export"
                description="Choose one system dataset, select only the columns you need, apply optional filters, then download a formatted Excel workbook. Export definitions are whitelisted, so this tool never accepts raw database columns or SQL."
            >
                <template #actions>
                    <Button type="button" class="gap-2" :disabled="selectedFields.length === 0" @click="downloadExport">
                        <Download class="size-4" />
                        Create Excel
                    </Button>
                </template>
            </PageSection>

            <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.28fr)]">
                <section class="grid content-start gap-5 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-300">
                            <FileSpreadsheet class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold">Dataset and filters</h2>
                            <p class="text-sm leading-5 text-muted-foreground">Filters apply before the workbook is generated.</p>
                        </div>
                    </div>

                    <FormSelectField
                        id="export-dataset"
                        v-model="datasetKey"
                        label="Data source"
                        :options="datasetOptions"
                        required
                    />

                    <p v-if="activeDataset" class="rounded-lg bg-muted/40 p-3 text-sm leading-6 text-muted-foreground">
                        {{ activeDataset.description }}
                    </p>

                    <FormSelectField
                        v-if="activeStatusOptions.length"
                        id="export-status"
                        v-model="status"
                        label="Status filter"
                        :options="activeStatusOptions"
                        placeholder="All statuses"
                    />

                    <FormSelectField
                        v-if="activeDataset?.roleOptions.length"
                        id="export-role"
                        v-model="role"
                        label="Role filter"
                        :options="activeDataset.roleOptions"
                        placeholder="All roles"
                    />

                    <div v-if="activeDataset?.supportsDateRange" class="grid gap-4 sm:grid-cols-2">
                        <FormInputField id="export-date-from" v-model="dateFrom" label="Date from" type="date" />
                        <FormInputField id="export-date-to" v-model="dateTo" label="Date to" type="date" :min="dateFrom" />
                    </div>

                    <label
                        v-if="activeDataset?.supportsDeleted"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border bg-background p-4 text-sm"
                    >
                        <input v-model="includeDeleted" type="checkbox" class="mt-0.5 size-4 rounded border-input" />
                        <span>
                            <span class="block font-semibold">Include deleted records</span>
                            <span class="mt-1 block leading-5 text-muted-foreground">Adds soft-deleted records when the selected dataset supports them.</span>
                        </span>
                    </label>
                </section>

                <section class="min-w-0 rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b pb-4">
                        <div>
                            <h2 class="font-semibold">Columns</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ selectedFields.length }} of {{ activeDataset?.fields.length ?? 0 }} columns selected.
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

                    <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <button
                            v-for="field in activeDataset?.fields ?? []"
                            :key="field.key"
                            type="button"
                            class="flex min-w-0 items-center gap-3 rounded-lg border p-3 text-left text-sm transition hover:bg-muted/30"
                            :class="selectedFields.includes(field.key) ? 'border-primary/50 bg-primary/5' : 'border-border bg-background'"
                            @click="toggleField(field.key)"
                        >
                            <CheckSquare2 v-if="selectedFields.includes(field.key)" class="size-4 shrink-0 text-primary" />
                            <Square v-else class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 break-words font-medium">{{ field.label }}</span>
                        </button>
                    </div>

                    <p v-if="validationMessage" class="mt-4 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {{ validationMessage }}
                    </p>

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <p class="text-xs leading-5 text-muted-foreground">
                            The export is generated from a server-side query and downloaded as .xlsx. Large exports are processed in chunks.
                        </p>
                        <Button type="button" class="gap-2" :disabled="selectedFields.length === 0" @click="downloadExport">
                            <Download class="size-4" /> Download selected data
                        </Button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
