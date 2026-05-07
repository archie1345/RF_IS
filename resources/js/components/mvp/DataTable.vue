<script setup lang="ts">
import { computed, ref, useSlots } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import StatusBadge from '@/components/mvp/StatusBadge.vue';
import type { TableBadgeCell, TableCell, TableColumn, TableRow } from '@/types/mvp';

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
    <Card class="rounded-3xl border shadow-sm">
        <CardHeader class="space-y-1">
            <CardTitle class="text-xl">{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
            <div v-if="props.searchable" class="pt-2">
                <input
                    v-model="search"
                    type="text"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    :placeholder="props.searchPlaceholder ?? 'Search table...'"
                >
            </div>
        </CardHeader>
        <CardContent>
            <div class="overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-[0.16em] text-muted-foreground">
                            <th
                                v-for="column in props.columns"
                                :key="column.key"
                                class="px-3 py-2 font-semibold"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                {{ column.label }}
                            </th>
                            <th v-if="hasRowActions" class="px-3 py-2 text-right font-semibold">
                                {{ props.actionLabel ?? 'Action' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody v-if="filteredRows.length > 0">
                        <tr
                            v-for="row in filteredRows"
                            :key="row.id"
                            class="rounded-2xl bg-muted/40 text-sm text-foreground"
                        >
                            <td
                                v-for="column in props.columns"
                                :key="`${row.id}-${column.key}`"
                                class="px-3 py-3 first:rounded-l-2xl last:rounded-r-2xl"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
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
                            <td v-if="hasRowActions" class="px-3 py-3 text-right">
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
