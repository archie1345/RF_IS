<script setup lang="ts">
import { ArrowDownUp, ChevronLeft, ChevronRight, Search } from '@lucide/vue';
import { computed, reactive, ref, useSlots, watch } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    SelectOption,
    TableBadgeCell,
    TableCell,
    TableColumn,
    TableFilter,
    TableRow,
} from '@/types/resource-table';
import type { DataTableFilterColumns, DataTableFilterValue } from './DataTable.types';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        columns: TableColumn[];
        rows: TableRow[];
        emptyText?: string;
        actionLabel?: string;
        searchable?: boolean;
        searchPlaceholder?: string;
        paginate?: boolean;
        initialLimit?: number;
        pageSize?: number;
        showRowsPerPage?: boolean;
        rowsPerPageOptions?: number[];
        filters?: TableFilter[];
        filterable?: boolean;
        filterColumns?: DataTableFilterColumns;
        rowClickable?: boolean;
        rowClickLabel?: string;
    }>(),
    {
        description: '',
        searchable: false,
        paginate: true,
        initialLimit: 10,
        pageSize: 10,
        showRowsPerPage: true,
        rowsPerPageOptions: () => [10, 25, 50],
        filters: () => [],
        filterable: false,
        filterColumns: 'auto',
        rowClickable: false,
        rowClickLabel: 'Tekan data untuk membuka detail.',
    },
);

const emit = defineEmits<{
    rowClick: [row: TableRow];
}>();

const slots = useSlots();
const hasRowActions = Boolean(slots['row-actions']);
const hasHeaderActions = Boolean(slots.actions);
const search = ref('');
const sortKey = ref('');
const sortDirection = ref<'asc' | 'desc'>('asc');
const selectedRowsPerPage = ref(String(props.initialLimit));
const currentPage = ref(1);
const filterValues = reactive<Record<string, DataTableFilterValue>>({});

function safeId(value: string): string {
    return (
        value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '') || 'table'
    );
}

function getCellValue(row: TableRow, key: string): TableCell | undefined {
    return row[key];
}

function isBadgeCell(value: unknown): value is TableBadgeCell {
    return typeof value === 'object' && value !== null && 'kind' in value && value.kind === 'badge';
}

function statusTone(value: string): TableBadgeCell['tone'] {
    const status = value.trim().toUpperCase();

    if (['PRESENT', 'PAID', 'COMPLETED', 'CONFIRMED', 'APPROVED', 'ACTIVE', 'LUNAS', 'HADIR'].includes(status)) {
        return 'success';
    }
    if (['EXCUSED', 'LATE', 'ONGOING', 'PARTIAL', 'SCHEDULED', 'REGISTERED', 'TERLAMBAT', 'IZIN'].includes(status)) {
        return 'info';
    }
    if (['PENDING', 'WAITING', 'DRAFT', 'NEEDS_ASSISTANT', 'MENUNGGU'].includes(status)) {
        return 'warning';
    }
    if (
        [
            'ABSENT',
            'FAILED',
            'REJECTED',
            'CANCELED',
            'CANCELLED',
            'OVERDUE',
            'INACTIVE',
            'TIDAK HADIR',
            'ALPHA',
        ].includes(status)
    ) {
        return 'danger';
    }

    return 'neutral';
}

function badgeCell(value: TableCell | undefined, columnKey: string): TableBadgeCell | null {
    if (isBadgeCell(value)) return value;

    const statusColumn = ['status', 'proof_status', 'ledger_status', 'next_action'].some(
        (key) => columnKey === key || columnKey.endsWith(`_${key}`),
    );
    if (!statusColumn || value === null || value === undefined || typeof value === 'object') return null;

    return { kind: 'badge', text: String(value), tone: statusTone(String(value)) };
}

function getCellText(value: unknown): string | number | boolean {
    if (isBadgeCell(value)) return String(value.text);
    if (Array.isArray(value)) return value.join(', ');
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'object') return JSON.stringify(value);
    return value as string | number | boolean;
}

function isExternalUrl(value: TableCell | undefined): value is string {
    return typeof value === 'string' && /^https?:\/\//i.test(value);
}

function linkText(value: string): string {
    return value.includes('wa.me') ? 'Buka WhatsApp' : 'Buka';
}

function filterType(filter: TableFilter): 'text' | 'select' {
    return filter.type ?? 'text';
}

function filterMultiple(filter: TableFilter): boolean {
    return filterType(filter) === 'select' && filter.multiple !== false;
}

function filterText(filter: TableFilter, row: TableRow): string {
    const value = filter.accessor ? filter.accessor(row) : getCellValue(row, filter.columnKey ?? filter.key);
    return String(getCellText(value)).trim();
}

const automaticPersonFilter = computed<TableFilter | null>(() => {
    const preferredKey = ['child', 'athlete', 'coach'].find((key) =>
        props.rows.some((row) => String(getCellText(row[key])).trim() !== '-'),
    );
    if (!preferredKey) return null;
    if ((props.filters ?? []).some((filter) => (filter.columnKey ?? filter.key) === preferredKey)) return null;

    const values = new Set(
        props.rows
            .map((row) => String(getCellText(row[preferredKey])).trim())
            .filter((value) => value !== '' && value !== '-'),
    );
    if (values.size <= 1) return null;

    return {
        key: `auto_${preferredKey}`,
        columnKey: preferredKey,
        label: preferredKey === 'child' ? 'Anak' : preferredKey === 'coach' ? 'Pelatih' : 'Atlet / Anak',
        type: 'select',
        multiple: false,
        placeholder: 'Semua',
    };
});

const normalizedFilters = computed<TableFilter[]>(() => [
    ...(props.filters ?? []),
    ...(automaticPersonFilter.value ? [automaticPersonFilter.value] : []),
]);
const orderedFilters = computed(() => [
    ...normalizedFilters.value.filter((filter) => filterType(filter) === 'text'),
    ...normalizedFilters.value.filter((filter) => filterType(filter) === 'select'),
]);
const hasTableFilters = computed(
    () => (props.filterable && normalizedFilters.value.length > 0) || automaticPersonFilter.value !== null,
);
const activeFilterSignature = computed(() =>
    normalizedFilters.value
        .map((filter) => `${filter.key}:${JSON.stringify(filterValues[filter.key] ?? '')}`)
        .join('|'),
);
const hasActiveFilters = computed(() => normalizedFilters.value.some((filter) => filterSelections(filter).length > 0));
const clickableHint = computed(() => props.rowClickLabel.trim() || 'Tekan data untuk membuka detail.');
const filterGridClass = computed(() => filterColumnsClass(props.filterColumns));
const rowsPerPageSelectId = computed(() => `rows-per-page-${safeId(props.title)}`);

const rowsPerPageOptions = computed<SelectOption[]>(() => {
    const numericOptions = new Set(
        [...props.rowsPerPageOptions, props.initialLimit, props.pageSize]
            .map(Number)
            .filter((value) => Number.isFinite(value) && value > 0),
    );

    return [
        ...Array.from(numericOptions)
            .sort((left, right) => left - right)
            .map((value) => ({ value: String(value), label: `${value} baris` })),
        { value: 'all', label: 'Semua baris' },
    ];
});

const activePageSize = computed(() => {
    if (selectedRowsPerPage.value === 'all') return Math.max(filteredRows.value.length, 1);
    const parsed = Number(selectedRowsPerPage.value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : props.pageSize;
});

function filterInputId(filter: TableFilter): string {
    return `table-filter-${safeId(props.title)}-${safeId(filter.key)}`;
}

function filterSelections(filter: TableFilter): string[] {
    const value = filterValues[filter.key];
    if (Array.isArray(value))
        return value
            .map(String)
            .map((entry) => entry.trim())
            .filter(Boolean);
    const singleValue = String(value ?? '').trim();
    return singleValue ? [singleValue] : [];
}

function filterOptions(filter: TableFilter): SelectOption[] {
    if (filter.options) return filter.options;

    return Array.from(
        new Set(props.rows.map((row) => filterText(filter, row)).filter((value) => value && value !== '-')),
    )
        .sort((left, right) => left.localeCompare(right))
        .map((value) => ({ value, label: value }));
}

function filterColumnsClass(columns: DataTableFilterColumns): string {
    const classes: Record<string, string> = {
        '1': 'grid-cols-1',
        '2': 'grid-cols-1 md:grid-cols-2',
        '3': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
        '4': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4',
        '5': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-5',
        '6': 'grid-cols-1 md:grid-cols-2 xl:grid-cols-6',
        auto: 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3',
    };
    return classes[String(columns)] ?? classes.auto;
}

function filterSpanClass(filter: TableFilter): string {
    if (filter.span === 'full') return 'md:col-span-full';
    if (filter.span === 2) return 'md:col-span-2';
    if (typeof filter.span === 'number' && filter.span >= 3) return `xl:col-span-${filter.span}`;
    return '';
}

function rowMatchesFilters(row: TableRow): boolean {
    if (!hasTableFilters.value) return true;

    return normalizedFilters.value.every((filter) => {
        const values = filterSelections(filter);
        if (values.length === 0) return true;
        if (filter.match) return values.some((value) => filter.match?.(row, value));

        const candidate = filterText(filter, row).toLowerCase();
        return filterType(filter) === 'select'
            ? values.some((value) => candidate === value.toLowerCase())
            : candidate.includes(values[0].toLowerCase());
    });
}

function clearFilters(): void {
    normalizedFilters.value.forEach((filter) => {
        filterValues[filter.key] = filterMultiple(filter) ? [] : '';
    });
}

function setSort(column: TableColumn): void {
    if (sortKey.value === column.key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }
    sortKey.value = column.key;
    sortDirection.value = 'asc';
}

function handleRowClick(row: TableRow): void {
    if (props.rowClickable) emit('rowClick', row);
}

function handleRowKeydown(event: KeyboardEvent, row: TableRow): void {
    if (!props.rowClickable || !['Enter', ' '].includes(event.key)) return;
    event.preventDefault();
    handleRowClick(row);
}

const filteredRows = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    const baseRows = props.rows.filter((row) => {
        const matchesSearch =
            !props.searchable ||
            keyword === '' ||
            props.columns.some((column) =>
                String(getCellText(getCellValue(row, column.key)))
                    .toLowerCase()
                    .includes(keyword),
            );
        return matchesSearch && rowMatchesFilters(row);
    });

    if (!sortKey.value) return baseRows;
    return [...baseRows].sort((a, b) => {
        const left = String(getCellText(getCellValue(a, sortKey.value))).toLowerCase();
        const right = String(getCellText(getCellValue(b, sortKey.value))).toLowerCase();
        return sortDirection.value === 'asc' ? left.localeCompare(right) : right.localeCompare(left);
    });
});

const totalPages = computed(() => {
    if (!props.paginate || selectedRowsPerPage.value === 'all') return 1;
    return Math.max(Math.ceil(filteredRows.value.length / activePageSize.value), 1);
});
const visibleRows = computed(() => {
    if (!props.paginate || selectedRowsPerPage.value === 'all') return filteredRows.value;
    const start = (currentPage.value - 1) * activePageSize.value;
    return filteredRows.value.slice(start, start + activePageSize.value);
});
const displayStart = computed(() =>
    visibleRows.value.length === 0 ? 0 : (currentPage.value - 1) * activePageSize.value + 1,
);
const displayEnd = computed(() => displayStart.value + visibleRows.value.length - 1);

watch(
    normalizedFilters,
    (filters) => {
        filters.forEach((filter) => {
            if (filterValues[filter.key] === undefined) filterValues[filter.key] = filterMultiple(filter) ? [] : '';
        });
        Object.keys(filterValues).forEach((key) => {
            if (!filters.some((filter) => filter.key === key)) delete filterValues[key];
        });
    },
    { immediate: true },
);
watch([search, sortKey, sortDirection, () => props.rows.length, selectedRowsPerPage, activeFilterSignature], () => {
    currentPage.value = 1;
});
watch(totalPages, (pages) => {
    if (currentPage.value > pages) currentPage.value = pages;
});
</script>

<template>
    <Card class="w-full max-w-full overflow-hidden rounded-xl border-border/70 bg-card shadow-sm">
        <CardHeader class="space-y-3 px-4 pt-4 pb-3 sm:px-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle class="text-lg font-bold break-words sm:text-xl">{{ title }}</CardTitle>
                        <slot v-if="hasHeaderActions" name="actions" />
                    </div>
                    <CardDescription v-if="description" class="text-sm leading-5">{{ description }}</CardDescription>
                    <p v-if="props.rowClickable" class="text-xs text-muted-foreground">{{ clickableHint }}</p>
                </div>

                <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-end lg:w-auto">
                    <FormSelectField
                        v-if="props.paginate && props.showRowsPerPage"
                        :id="rowsPerPageSelectId"
                        v-model="selectedRowsPerPage"
                        label="Jumlah baris"
                        :options="rowsPerPageOptions"
                        :show-placeholder="false"
                    />
                    <div v-if="props.searchable" class="relative w-full sm:w-72">
                        <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="search"
                            class="h-10 w-full rounded-lg border border-input bg-background pr-3 pl-10 text-sm focus:ring-2 focus:ring-ring/25 focus:outline-none"
                            :placeholder="props.searchPlaceholder ?? 'Cari data...'"
                        />
                    </div>
                </div>
            </div>

            <div v-if="hasTableFilters" class="space-y-2 border-t pt-3">
                <div :class="['grid items-end gap-3', filterGridClass]">
                    <div
                        v-for="filter in orderedFilters"
                        :key="filter.key"
                        :class="['grid min-w-0 gap-1.5 text-sm font-semibold', filterSpanClass(filter)]"
                    >
                        <label v-if="filterType(filter) === 'text'" class="grid gap-1.5">
                            {{ filter.label }}
                            <input
                                v-model="filterValues[filter.key]"
                                type="text"
                                class="h-10 rounded-lg border bg-background px-3 text-sm focus:ring-2 focus:ring-ring/25 focus:outline-none"
                                :placeholder="filter.placeholder ?? `Filter ${filter.label.toLowerCase()}`"
                            />
                        </label>
                        <FormSelectField
                            v-else
                            :id="filterInputId(filter)"
                            v-model="filterValues[filter.key]"
                            :label="filter.label"
                            :options="filterOptions(filter)"
                            :placeholder="filter.placeholder ?? `Semua ${filter.label.toLowerCase()}`"
                            :multiple="filterMultiple(filter)"
                        />
                    </div>
                </div>
                <Button v-if="hasActiveFilters" type="button" variant="ghost" size="sm" @click="clearFilters">
                    Hapus filter
                </Button>
            </div>
        </CardHeader>

        <CardContent class="px-4 pb-4 sm:px-5">
            <div v-if="visibleRows.length > 0" class="grid gap-3 sm:hidden">
                <article
                    v-for="row in visibleRows"
                    :key="row.id"
                    class="min-w-0 rounded-xl border border-border/70 bg-background p-4"
                    :class="props.rowClickable ? 'cursor-pointer active:bg-muted/40' : ''"
                    :role="props.rowClickable ? 'button' : undefined"
                    :tabindex="props.rowClickable ? 0 : undefined"
                    @click="handleRowClick(row)"
                    @keydown="handleRowKeydown($event, row)"
                >
                    <dl class="grid gap-2.5">
                        <div
                            v-for="column in props.columns"
                            :key="`${row.id}-${column.key}-mobile`"
                            class="grid grid-cols-[minmax(0,0.42fr)_minmax(0,0.58fr)] gap-3 border-b pb-2.5 last:border-0 last:pb-0"
                        >
                            <dt class="text-xs font-semibold text-muted-foreground">{{ column.label }}</dt>
                            <dd class="min-w-0 text-right text-sm break-words">
                                <slot name="cell" :row="row" :column="column" :value="getCellValue(row, column.key)">
                                    <StatusBadge
                                        v-if="badgeCell(getCellValue(row, column.key), column.key)"
                                        :label="badgeCell(getCellValue(row, column.key), column.key)?.text ?? ''"
                                        :tone="badgeCell(getCellValue(row, column.key), column.key)?.tone"
                                    />
                                    <a
                                        v-else-if="isExternalUrl(getCellValue(row, column.key))"
                                        :href="String(getCellValue(row, column.key))"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-primary hover:underline"
                                        @click.stop
                                        >{{ linkText(String(getCellValue(row, column.key))) }}</a
                                    >
                                    <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                                </slot>
                            </dd>
                        </div>
                    </dl>
                    <div v-if="hasRowActions" class="mt-3 border-t pt-3" @click.stop>
                        <slot name="row-actions" :row="row" />
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground sm:hidden"
            >
                {{ props.emptyText ?? 'Belum ada data.' }}
            </div>

            <div class="hidden w-full overflow-x-auto sm:block">
                <table class="w-max min-w-full border-collapse">
                    <thead>
                        <tr class="border-b text-left text-sm">
                            <th
                                v-for="column in props.columns"
                                :key="column.key"
                                class="px-3 py-3 font-bold"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-primary"
                                    @click="setSort(column)"
                                >
                                    {{ column.label }}
                                    <ArrowDownUp
                                        class="size-3"
                                        :class="sortKey === column.key ? 'text-primary' : 'opacity-30'"
                                    />
                                </button>
                            </th>
                            <th v-if="hasRowActions" class="px-3 py-3 text-right font-bold">
                                {{ props.actionLabel ?? 'Tindakan' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="visibleRows.length > 0">
                        <tr
                            v-for="row in visibleRows"
                            :key="row.id"
                            class="border-b text-sm transition-colors hover:bg-muted/25"
                            :class="props.rowClickable ? 'cursor-pointer' : ''"
                            :tabindex="props.rowClickable ? 0 : undefined"
                            @click="handleRowClick(row)"
                            @keydown="handleRowKeydown($event, row)"
                        >
                            <td
                                v-for="(column, columnIndex) in props.columns"
                                :key="`${row.id}-${column.key}`"
                                class="max-w-sm px-3 py-3 align-middle break-words"
                                :class="[
                                    column.align === 'right' ? 'text-right' : 'text-left',
                                    columnIndex === 0 ? 'font-semibold' : '',
                                ]"
                            >
                                <slot name="cell" :row="row" :column="column" :value="getCellValue(row, column.key)">
                                    <StatusBadge
                                        v-if="badgeCell(getCellValue(row, column.key), column.key)"
                                        :label="badgeCell(getCellValue(row, column.key), column.key)?.text ?? ''"
                                        :tone="badgeCell(getCellValue(row, column.key), column.key)?.tone"
                                    />
                                    <a
                                        v-else-if="isExternalUrl(getCellValue(row, column.key))"
                                        :href="String(getCellValue(row, column.key))"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-primary hover:underline"
                                        @click.stop
                                        >{{ linkText(String(getCellValue(row, column.key))) }}</a
                                    >
                                    <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                                </slot>
                            </td>
                            <td v-if="hasRowActions" class="px-3 py-3 text-right" @click.stop>
                                <slot name="row-actions" :row="row" />
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                :colspan="props.columns.length + (hasRowActions ? 1 : 0)"
                                class="px-3 py-10 text-center text-sm text-muted-foreground"
                            >
                                {{ props.emptyText ?? 'Belum ada data.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="props.paginate"
                class="mt-4 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-xs font-medium text-muted-foreground">
                    Menampilkan {{ displayStart }}–{{ displayEnd }} dari {{ filteredRows.length }} baris
                    <span v-if="filteredRows.length !== props.rows.length">({{ props.rows.length }} total)</span>
                </p>
                <div class="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="currentPage <= 1"
                        @click="currentPage -= 1"
                    >
                        <ChevronLeft class="size-4" /> Sebelumnya
                    </Button>
                    <span class="min-w-20 text-center text-xs font-semibold"
                        >Halaman {{ currentPage }} / {{ totalPages }}</span
                    >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="currentPage >= totalPages"
                        @click="currentPage += 1"
                    >
                        Berikutnya <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
