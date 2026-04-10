<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import StatusBadge from '@/components/mvp/StatusBadge.vue';
import type { TableBadgeCell, TableCell, TableColumn, TableRow } from '@/types/mvp';

const props = defineProps<{
    title: string;
    description: string;
    columns: TableColumn[];
    rows: TableRow[];
    emptyText?: string;
}>();

function getCellValue(row: TableRow, key: string): TableCell | undefined {
    return row[key];
}

function isBadgeCell(value: TableCell | undefined): value is TableBadgeCell {
    return (
        typeof value === 'object' &&
        value !== null &&
        'kind' in value &&
        value.kind === 'badge'
    );
}

function getCellText(value: TableCell | undefined) {
    if (typeof value === 'object' && value !== null && 'text' in value) {
        return value.text;
    }

    return value ?? '—';
}
</script>

<template>
    <Card class="rounded-3xl border shadow-sm">
        <CardHeader class="space-y-1">
            <CardTitle class="text-xl">{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
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
                        </tr>
                    </thead>
                    <tbody v-if="props.rows.length > 0">
                        <tr
                            v-for="row in props.rows"
                            :key="row.id"
                            class="rounded-2xl bg-muted/40 text-sm text-foreground"
                        >
                            <td
                                v-for="column in props.columns"
                                :key="`${row.id}-${column.key}`"
                                class="px-3 py-3 first:rounded-l-2xl last:rounded-r-2xl"
                                :class="column.align === 'right' ? 'text-right' : 'text-left'"
                            >
                                <StatusBadge
                                    v-if="isBadgeCell(getCellValue(row, column.key))"
                                    :label="getCellValue(row, column.key).text"
                                    :tone="getCellValue(row, column.key).tone"
                                />
                                <span v-else>{{ getCellText(getCellValue(row, column.key)) }}</span>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                :colspan="props.columns.length"
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
