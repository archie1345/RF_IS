<script setup lang="ts">
import { computed, ref, useSlots } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import type { TableBadgeCell, TableCell, TableColumn, TableRow } from '@/types/management';

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

const filteredRows = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!props.searchable || keyword === '') {
        return props.rows;
    }

    return props.rows.filter((row) => {
        return props.columns.some((column) => {
            const value = getCellValue(row, column.key);
            const text = String(getCellText(value)).toLowerCase();
            return text.includes(keyword);
        });
    });
});
</script>

<template>
    <Card class="w-full max-w-full overflow-hidden rounded-xl border-border/70 bg-card shadow-sm">
        <CardHeader class="space-y-1 px-4 pb-3 pt-4 sm:px-5 sm:pb-4 sm:pt-5">
            <CardTitle class="text-lg sm:text-xl">{{ title }}</CardTitle>
            <CardDescription class="text-sm leading-6">{{ description }}</CardDescription>
            <div v-if="props.searchable" class="pt-2">
                <input
                    v-model="search"
                    type="text"
                    class="h-10 w-full rounded-lg border border-input bg-background px-3 text-sm"
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
                            <th
                                v-for="column in props.columns"
                                :key="column.key"
                                class="px-2 py-2 font-semibold sm:px-3"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                {{ column.label }}
                            </th>
                            <th v-if="hasRowActions" class="px-2 py-2 text-right font-semibold sm:px-3">
                                {{ props.actionLabel ?? 'Action' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="filteredRows.length > 0">
                        <tr
                            v-for="row in filteredRows"
                            :key="row.id"
                            class="rounded-lg bg-muted/35 text-sm text-foreground transition-colors hover:bg-muted/60"
                        >
                            <td
                                v-for="column in props.columns"
                                :key="`${row.id}-${column.key}`"
                                class="px-2 py-3 first:rounded-l-lg last:rounded-r-lg sm:px-3"
                                :class="[
                                    column.align === 'right' ? 'text-right' : 'text-left',
                                    hasRowActions ? 'last:rounded-r-none' : '',
                                ]"
                            >
                                <slot
                                    name="cell"
                                    :row="row"
                                    :column="column"
                                    :value="getCellValue(row, column.key)"
                                >
                                    <StatusBadge
                                        v-if="isBadgeCell(getCellValue(row, column.key))"
                                        :label="(getCellValue(row, column.key) as TableBadgeCell).text"
                                        :tone="(getCellValue(row, column.key) as TableBadgeCell).tone"
                                    />
                                    <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                                </slot>
                            </td>
                            <td v-if="hasRowActions" class="rounded-r-lg px-2 py-3 text-right sm:px-3">
                                <slot name="row-actions" :row="row" />
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                :colspan="props.columns.length + (hasRowActions ? 1 : 0)"
                                class="px-3 py-8 text-center text-sm text-muted-foreground"
                            >
                                {{ props.emptyText ?? 'No records available yet.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>

