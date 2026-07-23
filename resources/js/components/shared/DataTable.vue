<script setup lang="ts">
import { ArrowDownUp, Search } from 'lucide-vue-next';
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
        paginate: false,
        initialLimit: 10,
        pageSize: 10,
        showRowsPerPage: false,
        rowsPerPageOptions: () => [10, 25, 50],
        filters: () => [],
        filterable: false,
        filterColumns: 'auto',
        rowClickable: false,
        rowClickLabel: 'Tap a record to open details.',
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
const visibleLimit = ref(props.initialLimit);
const filterValues = reactive<Record<string, DataTableFilterValue>>({});

const rowsPerPageSelectId = computed(() => `rows-per-page-${safeId(props.title)}`);
const normalizedFilters = computed<TableFilter[]>(() => props.filters ?? []);
const orderedFilters = computed(() => [
    ...normalizedFilters.value.filter((filter) => filterType(filter) === 'text'),
    ...normalizedFilters.value.filter((filter) => filterType(filter) === 'select'),
]);
const hasTableFilters = computed(() => props.filterable && normalizedFilters.value.length > 0);
const activeFilterSignature = computed(() =>
    normalizedFilters.value
        .map((filter) => `${filter.key}:${JSON.stringify(filterValues[filter.key] ?? '')}`)
        .join('|'),
);
const hasActiveFilters = computed(() => normalizedFilters.value.some((filter) => filterSelections(filter).length > 0));
const clickableHint = computed(() => props.rowClickLabel.trim() || 'Tap a record to open details.');
const filterGridClass = computed(() => filterColumnsClass(props.filterColumns));

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
    if (selectedRowsPerPage.value === 'all') return filteredRows.value.length;

    const parsed = Number(selectedRowsPerPage.value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : props.pageSize;
});

function safeId(value: string): string {
    return (
        value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '') || 'table'
    );
}

function filterInputId(filter: TableFilter): string {
    return `table-filter-${safeId(props.title)}-${safeId(filter.key)}`;
}

function getCellValue(row: TableRow, key: string): TableCell | undefined {
    return row[key];
}

function isBadgeCell(value: unknown): value is TableBadgeCell {
    return typeof value === 'object' && value !== null && 'kind' in value && value.kind === 'badge';
}

function badgeCell(value: TableCell | undefined): TableBadgeCell | null {
    return isBadgeCell(value) ? value : null;
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

function filterSelections(filter: TableFilter): string[] {
    const value = filterValues[filter.key];
    if (Array.isArray(value)) {
        return value
            .map(String)
            .map((entry) => entry.trim())
            .filter(Boolean);
    }

    const singleValue = String(value ?? '').trim();
    return singleValue ? [singleValue] : [];
}

function filterOptions(filter: TableFilter): SelectOption[] {
    if (filter.options) return filter.options;

    return Array.from(
        new Set(props.rows.map((row) => filterText(filter, row)).filter((value) => value !== '' && value !== '-')),
    )
        .sort((left, right) => left.localeCompare(right))
        .map((value) => ({ value, label: value }));
}

function filterColumnsClass(columns: DataTableFilterColumns): string {
    switch (columns) {
        case 1:
            return 'grid-cols-1';
        case 2:
            return 'grid-cols-1 md:grid-cols-2';
        case 3:
            return 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3';
        case 4:
            return 'grid-cols-1 md:grid-cols-2 xl:grid-cols-4';
        case 5:
            return 'grid-cols-1 md:grid-cols-2 xl:grid-cols-5';
        case 6:
            return 'grid-cols-1 md:grid-cols-2 xl:grid-cols-6';
        default:
            return 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3';
    }
}

function filterSpanClass(filter: TableFilter): string {
    switch (filter.span) {
        case 2:
            return 'md:col-span-2';
        case 3:
            return 'xl:col-span-3';
        case 4:
            return 'xl:col-span-4';
        case 5:
            return 'xl:col-span-5';
        case 6:
            return 'xl:col-span-6';
        case 'full':
            return 'md:col-span-full';
        default:
            return '';
    }
}

function rowMatchesFilters(row: TableRow): boolean {
    if (!hasTableFilters.value) return true;

    return normalizedFilters.value.every((filter) => {
        const values = filterSelections(filter);
        if (values.length === 0) return true;
        if (filter.match) return values.some((value) => filter.match?.(row, value));

        const candidate = filterText(filter, row).toLowerCase();
        if (filterType(filter) === 'select') {
            return values.some((value) => candidate === value.toLowerCase());
        }

        return candidate.includes(values[0].toLowerCase());
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

    return baseRows.sort((a, b) => {
        const left = String(getCellText(getCellValue(a, sortKey.value))).toLowerCase();
        const right = String(getCellText(getCellValue(b, sortKey.value))).toLowerCase();
        return sortDirection.value === 'asc' ? left.localeCompare(right) : right.localeCompare(left);
    });
});

const visibleRows = computed(() => {
    if (!props.paginate || selectedRowsPerPage.value === 'all') return filteredRows.value;
    return filteredRows.value.slice(0, visibleLimit.value);
});

const canShowMore = computed(
    () => props.paginate && selectedRowsPerPage.value !== 'all' && visibleRows.value.length < filteredRows.value.length,
);

function resetVisibleLimit(): void {
    visibleLimit.value = activePageSize.value || props.initialLimit;
}

watch(
    normalizedFilters,
    (filters) => {
        filters.forEach((filter) => {
            if (filterValues[filter.key] === undefined) {
                filterValues[filter.key] = filterMultiple(filter) ? [] : '';
            }

            if (filterMultiple(filter) && !Array.isArray(filterValues[filter.key])) {
                filterValues[filter.key] = filterValues[filter.key] ? [String(filterValues[filter.key])] : [];
            }

            if (!filterMultiple(filter) && Array.isArray(filterValues[filter.key])) {
                filterValues[filter.key] = '';
            }
        });

        Object.keys(filterValues).forEach((key) => {
            if (!filters.some((filter) => filter.key === key)) delete filterValues[key];
        });
    },
    { immediate: true },
);

watch(
    [search, sortKey, sortDirection, () => props.rows.length, selectedRowsPerPage, activeFilterSignature],
    resetVisibleLimit,
);

function showMoreRows(): void {
    visibleLimit.value += activePageSize.value || props.pageSize;
}

function showAllRows(): void {
    selectedRowsPerPage.value = 'all';
    visibleLimit.value = filteredRows.value.length;
}
</script>

<template>
    <Card class="w-full max-w-full overflow-hidden rounded-2xl border-border/70 bg-card shadow-sm">
        <CardHeader class="space-y-4 px-4 pt-4 pb-3 sm:px-5 sm:pt-5 sm:pb-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                        <CardTitle class="break-words text-xl font-black sm:text-2xl">{{ title }}</CardTitle>
                        <div v-if="hasHeaderActions" class="flex w-full flex-wrap gap-2 sm:w-auto">
                            <slot name="actions" />
                        </div>
                    </div>
                    <CardDescription v-if="description" class="text-sm leading-6">{{ description }}</CardDescription>
                    <p v-if="props.rowClickable" class="text-xs font-semibold text-muted-foreground">
                        {{ clickableHint }}
                    </p>
                </div>

                <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-end lg:w-auto lg:justify-end">
                    <FormSelectField
                        v-if="props.paginate && props.showRowsPerPage"
                        :id="rowsPerPageSelectId"
                        v-model="selectedRowsPerPage"
                        label="Baris per halaman"
                        :options="rowsPerPageOptions"
                        placeholder="Jumlah baris"
                    />
                    <div
                        v-if="props.paginate"
                        class="self-start rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground sm:self-end"
                    >
                        {{ visibleRows.length }} / {{ filteredRows.length }} ditampilkan<span
                            v-if="filteredRows.length !== props.rows.length"
                        >
                            · {{ props.rows.length }} total</span
                        >
                    </div>
                    <div v-if="props.searchable" class="relative w-full sm:w-80 lg:w-96">
                        <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="search"
                            class="h-11 w-full rounded-lg border border-input bg-background pr-3 pl-10 text-sm shadow-sm focus:ring-2 focus:ring-ring/25 focus:outline-none"
                            :placeholder="props.searchPlaceholder ?? 'Cari data...'"
                        />
                    </div>
                </div>
            </div>

            <div v-if="hasTableFilters">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <Button v-if="hasActiveFilters" type="button" variant="outline" size="sm" @click="clearFilters">
                        Hapus filter
                    </Button>
                </div>
                <div :class="['grid items-end gap-3', filterGridClass]">
                    <div
                        v-for="filter in orderedFilters"
                        :key="filter.key"
                        :class="['grid min-w-0 gap-2 text-sm font-semibold', filterSpanClass(filter)]"
                    >
                        <label v-if="filterType(filter) === 'text'" class="grid min-w-0 gap-2">
                            {{ filter.label }}
                            <input
                                v-model="filterValues[filter.key]"
                                type="text"
                                class="min-h-12 min-w-0 rounded-2xl border bg-background px-3 text-sm shadow-sm focus:ring-2 focus:ring-ring/30 focus:outline-none"
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
                            :search-placeholder="filter.searchPlaceholder ?? `Cari ${filter.label.toLowerCase()}...`"
                            :multiple="filterMultiple(filter)"
                        />
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent class="px-4 pb-4 sm:px-5 sm:pb-5">
            <div v-if="visibleRows.length > 0" class="grid gap-3 sm:hidden">
                <article
                    v-for="row in visibleRows"
                    :key="row.id"
                    class="min-w-0 rounded-xl border border-border/70 bg-background p-4 shadow-sm"
                    :class="props.rowClickable ? 'cursor-pointer active:bg-muted/40' : ''"
                    :role="props.rowClickable ? 'button' : undefined"
                    :tabindex="props.rowClickable ? 0 : undefined"
                    :aria-label="props.rowClickable ? clickableHint : undefined"
                    @click="handleRowClick(row)"
                    @keydown="handleRowKeydown($event, row)"
                >
                    <dl class="grid gap-3">
                        <div
                            v-for="column in props.columns"
                            :key="`${row.id}-${column.key}-mobile`"
                            class="grid min-w-0 grid-cols-[minmax(0,0.42fr)_minmax(0,0.58fr)] gap-3 border-b border-border/60 pb-3 last:border-0 last:pb-0"
                        >
                            <dt class="break-words text-xs font-semibold text-muted-foreground">{{ column.label }}</dt>
                            <dd class="min-w-0 break-words text-right text-sm" :class="column.key === props.columns[0]?.key ? 'font-semibold' : ''">
                                <slot name="cell" :row="row" :column="column" :value="getCellValue(row, column.key)">
                                    <StatusBadge
                                        v-if="badgeCell(getCellValue(row, column.key))"
                                        :label="badgeCell(getCellValue(row, column.key))?.text ?? ''"
                                        :tone="badgeCell(getCellValue(row, column.key))?.tone"
                                    />
                                    <a
                                        v-else-if="isExternalUrl(getCellValue(row, column.key))"
                                        :href="String(getCellValue(row, column.key))"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-primary underline-offset-4 hover:underline"
                                        @click.stop
                                    >
                                        {{ linkText(String(getCellValue(row, column.key))) }}
                                    </a>
                                    <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                                </slot>
                            </dd>
                        </div>
                    </dl>
                    <div v-if="hasRowActions" class="mt-4 border-t border-border/70 pt-4" @click.stop>
                        <p class="mb-2 text-xs font-semibold text-muted-foreground">
                            {{ props.actionLabel ?? 'Tindakan' }}
                        </p>
                        <slot name="row-actions" :row="row" />
                    </div>
                </article>
            </div>

            <div v-else class="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground sm:hidden">
                {{ props.emptyText ?? 'Belum ada data.' }}
            </div>

            <div class="hidden w-full max-w-full overflow-x-auto sm:block">
                <table class="w-max min-w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border/80 text-left text-sm text-foreground">
                            <th
                                v-for="column in props.columns"
                                :key="column.key"
                                class="px-3 py-4 font-bold first:pl-3"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                <button type="button" class="inline-flex items-center gap-1 hover:text-primary" @click="setSort(column)">
                                    {{ column.label }}
                                    <ArrowDownUp class="size-3" :class="sortKey === column.key ? 'text-primary' : 'opacity-30'" />
                                </button>
                            </th>
                            <th v-if="hasRowActions" class="px-3 py-4 text-right font-bold">
                                {{ props.actionLabel ?? 'Tindakan' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="visibleRows.length > 0">
                        <tr
                            v-for="row in visibleRows"
                            :key="row.id"
                            class="group border-b border-border/70 text-sm text-foreground transition-colors hover:bg-muted/25"
                            :class="props.rowClickable ? 'cursor-pointer focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none' : ''"
                            :role="props.rowClickable ? 'button' : undefined"
                            :tabindex="props.rowClickable ? 0 : undefined"
                            :title="props.rowClickable ? clickableHint : undefined"
                            @click="handleRowClick(row)"
                            @keydown="handleRowKeydown($event, row)"
                        >
                            <td
                                v-for="(column, columnIndex) in props.columns"
                                :key="`${row.id}-${column.key}`"
                                class="max-w-sm break-words px-3 py-4 align-middle"
                                :class="[
                                    column.align === 'right' ? 'text-right' : 'text-left',
                                    columnIndex === 0 ? 'font-semibold text-foreground' : '',
                                ]"
                            >
                                <slot name="cell" :row="row" :column="column" :value="getCellValue(row, column.key)">
                                    <StatusBadge
                                        v-if="badgeCell(getCellValue(row, column.key))"
                                        :label="badgeCell(getCellValue(row, column.key))?.text ?? ''"
                                        :tone="badgeCell(getCellValue(row, column.key))?.tone"
                                    />
                                    <a
                                        v-else-if="isExternalUrl(getCellValue(row, column.key))"
                                        :href="String(getCellValue(row, column.key))"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="font-semibold text-primary underline-offset-4 hover:underline"
                                        @click.stop
                                    >
                                        {{ linkText(String(getCellValue(row, column.key))) }}
                                    </a>
                                    <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                                </slot>
                            </td>
                            <td v-if="hasRowActions" class="px-3 py-4 text-right align-middle" @click.stop>
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

            <div v-if="canShowMore" class="flex flex-col items-stretch justify-center gap-2 pt-4 sm:flex-row sm:items-center">
                <Button type="button" variant="outline" size="sm" @click="showMoreRows">
                    Tampilkan {{ activePageSize }} lagi
                </Button>
                <Button type="button" variant="ghost" size="sm" @click="showAllRows">
                    Tampilkan semua {{ filteredRows.length }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
