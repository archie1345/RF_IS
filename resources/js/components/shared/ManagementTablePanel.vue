<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import PageSection from '@/components/shared/PageSection.vue';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import type { TableColumn, TableRow } from '@/types/management';

const props = withDefaults(defineProps<{
    eyebrow?: string;
    title: string;
    description: string;
    createLabel?: string;
    tableTitle: string;
    tableDescription: string;
    columns: TableColumn[];
    rows: TableRow[];
    emptyText?: string;
    actionLabel?: string;
    showCreate?: boolean;
}>(), {
    showCreate: true,
});

defineEmits<{
    create: [];
}>();
</script>

<template>
    <div class="space-y-6 min-w-0">
        <PageSection
            :eyebrow="props.eyebrow"
            :title="props.title"
            :description="props.description"
        >
            <template #actions>
                <Button v-if="props.showCreate" class="gap-2" @click="$emit('create')">
                    <Plus class="size-4" />
                    {{ props.createLabel ?? 'Create' }}
                </Button>
                <slot v-else name="actions" />
            </template>

            <slot name="stats" />
        </PageSection>

        <DataTable
            :title="props.tableTitle"
            :description="props.tableDescription"
            :columns="props.columns"
            :rows="props.rows"
            :empty-text="props.emptyText"
            :action-label="props.actionLabel"
        >
            <template #row-actions="{ row }">
                <slot name="row-actions" :row="row" />
            </template>
        </DataTable>

        <slot name="after-table" />
    </div>
</template>

