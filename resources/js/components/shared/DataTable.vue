<script setup lang="ts">
import { computed, ref, useSlots } from 'vue';
import { ArrowDownUp, Search } from 'lucide-vue-next';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { TableBadgeCell, TableCell, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    title: string;
    description: string;
    columns: TableColumn[];
    rows: TableRow[];
    emptyText?: string;
    actionLabel?: string;
    searchable?: boolean;
    searchPlaceholder?: string;
}>();

const slots = useSlots();
const hasRowActions = Boolean(slots['row-actions']);
const search = ref('');
const sortKey = ref('');
const sortDirection = ref<'asc' | 'desc'>('asc');

function getCellValue(row: TableRow, key: string): TableCell | undefined {
    return row[key];
}

function isBadgeCell(value: TableCell | undefined): value is TableBadgeCell {
    return typeof value === 'object' && value !== null && 'kind' in value && value.kind === 'badge';
}

function getCellText(value: TableCell | undefined) {
    if (typeof value === 'object' && value !== null && 'text' in value) {
        return value.text;
    }

    return value ?? '-';
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
    const baseRows = !props.searchable || keyword === ''
        ? [...props.rows]
        : props.rows.filter((row) => props.columns.some((column) => String(getCellText(getCellValue(row, column.key))).toLowerCase().includes(keyword)));

    if (!sortKey.value) return baseRows;

    return baseRows.sort((a, b) => {
        const left = String(getCellText(getCellValue(a, sortKey.value))).toLowerCase();
        const right = String(getCellText(getCellValue(b, sortKey.value))).toLowerCase();
        return sortDirection.value === 'asc' ? left.localeCompare(right) : right.localeCompare(left);
    });
});
</script>

<template>
    <Card class="w-full max-w-full overflow-hidden rounded-2xl border-border/70 bg-card shadow-sm">
        <CardHeader class="space-y-3 px-4 pb-3 pt-4 sm:px-5 sm:pb-4 sm:pt-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle class="text-lg sm:text-xl">{{ title }}</CardTitle>
                    <CardDescription class="text-sm leading-6">{{ description }}</CardDescription>
                </div>
                <div class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground">
                    {{ filteredRows.length }} / {{ props.rows.length }} rows
                </div>
            </div>
            <div v-if="props.searchable" class="relative pt-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    class="h-11 w-full rounded-xl border border-input bg-background pl-10 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-ring/25"
                    :placeholder="props.searchPlaceholder ?? 'Search table...'"
                >
            </div>
        </CardHeader>
        <CardContent class="px-0 pb-3 sm:px-5 sm:pb-5">
            <div class="px-4 pb-2 text-xs text-muted-foreground sm:hidden">Swipe horizontally to view all columns</div>
            <div class="w-full max-w-full overflow-x-auto px-2 sm:px-0">
                <table class="w-max min-w-full border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-[0.16em] text-muted-foreground">
                            <th v-for="column in props.columns" :key="column.key" class="px-2 py-2 font-semibold sm:px-3" :class="column.align === 'right' ? 'text-right' : 'text-left'">
                                <button type="button" class="inline-flex items-center gap-1 hover:text-foreground" @click="setSort(column)">
                                    {{ column.label }}
                                    <ArrowDownUp class="size-3" :class="sortKey === column.key ? 'text-primary' : 'opacity-40'" />
                                </button>
                            </th>
                            <th v-if="hasRowActions" class="px-2 py-2 text-right font-semibold sm:px-3">
                                {{ props.actionLabel ?? 'Action' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="filteredRows.length > 0">
                        <tr v-for="row in filteredRows" :key="row.id" class="rounded-xl bg-muted/35 text-sm text-foreground transition-all hover:-translate-y-0.5 hover:bg-muted/70 hover:shadow-sm">
                            <td v-for="column in props.columns" :key="`${row.id}-${column.key}`" class="px-2 py-3 first:rounded-l-xl last:rounded-r-xl sm:px-3" :class="[column.align === 'right' ? 'text-right' : 'text-left', hasRowActions ? 'last:rounded-r-none' : '']">
                                <slot name="cell" :row="row" :column="column" :value="getCellValue(row, column.key)">
                                    <StatusBadge v-if="isBadgeCell(getCellValue(row, column.key))" :label="(getCellValue(row, column.key) as TableBadgeCell).text" :tone="(getCellValue(row, column.key) as TableBadgeCell).tone" />
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
                            <td :colspan="props.columns.length + (hasRowActions ? 1 : 0)" class="px-3 py-10 text-center text-sm text-muted-foreground">
                                {{ props.emptyText ?? 'No records available yet.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
