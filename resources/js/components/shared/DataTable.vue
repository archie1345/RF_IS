<script setup lang="ts">
import { ArrowDownUp, Search } from 'lucide-vue-next';
import { computed, ref, useSlots, watch } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { SelectOption, TableBadgeCell, TableCell, TableColumn, TableRow } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
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
    }>(),
    {
        paginate: true,
        initialLimit: 10,
        pageSize: 10,
        showRowsPerPage: true,
        rowsPerPageOptions: () => [10, 25, 50],
    },
);

const slots = useSlots();
const hasRowActions = Boolean(slots['row-actions']);
const search = ref('');
const sortKey = ref('');
const sortDirection = ref<'asc' | 'desc'>('asc');
const selectedRowsPerPage = ref(String(props.initialLimit));
const visibleLimit = ref(props.initialLimit);

const rowsPerPageSelectId = computed(() => `rows-per-page-${props.title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`);

const rowsPerPageOptions = computed<SelectOption[]>(() => {
    const numericOptions = new Set(
        [...props.rowsPerPageOptions, props.initialLimit, props.pageSize]
            .map((value) => Number(value))
            .filter((value) => Number.isFinite(value) && value > 0),
    );

    return [
        ...Array.from(numericOptions)
            .sort((left, right) => left - right)
            .map((value) => ({ value: String(value), label: `${value} rows` })),
        { value: 'all', label: 'All rows' },
    ];
});

const activePageSize = computed(() => {
    if (selectedRowsPerPage.value === 'all') return filteredRows.value.length;

    const parsed = Number(selectedRowsPerPage.value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : props.pageSize;
});

function getCellValue(row: TableRow, key: string): TableCell | undefined {
    return row[key];
}

function isBadgeCell(value: TableCell | undefined): value is TableBadgeCell {
    return typeof value === 'object' && value !== null && 'kind' in value && value.kind === 'badge';
}

function badgeCell(value: TableCell | undefined): TableBadgeCell | null {
    return isBadgeCell(value) ? value : null;
}

function getCellText(value: TableCell | undefined): string | number | boolean {
    if (typeof value === 'object' && value !== null && 'text' in value) return String(value.text);
    if (Array.isArray(value)) return value.join(', ');
    if (value === null || value === undefined) return '-';
    if (typeof value === 'object') return JSON.stringify(value);
    return value;
}

function isExternalUrl(value: TableCell | undefined): value is string {
    return typeof value === 'string' && /^https?:\/\//i.test(value);
}

function linkText(value: string): string {
    if (value.includes('wa.me')) return 'Open WA';
    return 'Open';
}

function setSort(column: TableColumn) {
    if (sortKey.value === column.key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    sortKey.value = column.key;
    sortDirection.value = 'asc';
}

const filteredRows = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    const baseRows =
        !props.searchable || keyword === ''
            ? [...props.rows]
            : props.rows.filter((row) =>
                  props.columns.some((column) =>
                      String(getCellText(getCellValue(row, column.key)))
                          .toLowerCase()
                          .includes(keyword),
                  ),
              );

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
const canShowMore = computed(() => props.paginate && selectedRowsPerPage.value !== 'all' && visibleRows.value.length < filteredRows.value.length);

function resetVisibleLimit() {
    visibleLimit.value = activePageSize.value || props.initialLimit;
}

watch([search, sortKey, sortDirection, () => props.rows.length, selectedRowsPerPage], resetVisibleLimit);

function showMoreRows() {
    visibleLimit.value += activePageSize.value || props.pageSize;
}

function showAllRows() {
    selectedRowsPerPage.value = 'all';
    visibleLimit.value = filteredRows.value.length;
}
</script>

<template>
    <Card class="w-full max-w-full overflow-hidden rounded-2xl border-border/70 bg-card shadow-sm">
        <CardHeader class="space-y-3 px-4 pt-4 pb-3 sm:px-5 sm:pt-5 sm:pb-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle class="text-lg sm:text-xl">{{ title }}</CardTitle>
                    <CardDescription class="text-sm leading-6">{{ description }}</CardDescription>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <FormSelectField
                        v-if="props.paginate && props.showRowsPerPage"
                        :id="rowsPerPageSelectId"
                        v-model="selectedRowsPerPage"
                        label="Rows per page"
                        :options="rowsPerPageOptions"
                        placeholder="Rows per page"
                    />
                    <div class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground">
                        {{ visibleRows.length }} / {{ filteredRows.length }} shown<span v-if="filteredRows.length !== props.rows.length"> · {{ props.rows.length }} total</span>
                    </div>
                </div>
            </div>
            <div v-if="props.searchable" class="relative pt-1">
                <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    class="h-11 w-full rounded-xl border border-input bg-background pr-3 pl-10 text-sm shadow-sm focus:ring-2 focus:ring-ring/25 focus:outline-none"
                    :placeholder="props.searchPlaceholder ?? 'Search table...'"
                />
            </div>
        </CardHeader>
        <CardContent class="px-0 pb-3 sm:px-5 sm:pb-5">
            <div class="px-4 pb-2 text-xs text-muted-foreground sm:hidden">Swipe horizontally to view all columns</div>
            <div class="w-full max-w-full overflow-x-auto px-2 sm:px-0">
                <table class="w-max min-w-full border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.16em] text-muted-foreground uppercase">
                            <th
                                v-for="column in props.columns"
                                :key="column.key"
                                class="px-2 py-2 font-semibold sm:px-3"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-foreground"
                                    @click="setSort(column)"
                                >
                                    {{ column.label }}
                                    <ArrowDownUp
                                        class="size-3"
                                        :class="sortKey === column.key ? 'text-primary' : 'opacity-40'"
                                    />
                                </button>
                            </th>
                            <th v-if="hasRowActions" class="px-2 py-2 text-right font-semibold sm:px-3">
                                {{ props.actionLabel ?? 'Action' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="visibleRows.length > 0">
                        <tr
                            v-for="row in visibleRows"
                            :key="row.id"
                            class="rounded-xl bg-muted/35 text-sm text-foreground transition-all hover:-translate-y-0.5 hover:bg-muted/70 hover:shadow-sm"
                        >
                            <td
                                v-for="column in props.columns"
                                :key="`${row.id}-${column.key}`"
                                class="px-2 py-3 first:rounded-l-xl last:rounded-r-xl sm:px-3"
                                :class="[
                                    column.align === 'right' ? 'text-right' : 'text-left',
                                    hasRowActions ? 'last:rounded-r-none' : '',
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
                                    >
                                        {{ linkText(String(getCellValue(row, column.key))) }}
                                    </a>
                                    <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                                </slot>
                            </td>
                            <td v-if="hasRowActions" class="rounded-r-xl px-2 py-3 text-right sm:px-3">
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
                                {{ props.emptyText ?? 'No records available yet.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="canShowMore" class="flex flex-wrap items-center justify-center gap-2 px-4 pt-4 sm:px-0">
                <Button type="button" variant="outline" size="sm" @click="showMoreRows">Show {{ activePageSize }} more</Button>
                <Button type="button" variant="ghost" size="sm" @click="showAllRows">Show all {{ filteredRows.length }}</Button>
            </div>
        </CardContent>
    </Card>
</template>