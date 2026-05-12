<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import PageSection from '@/components/shared/PageSection.vue';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import type { TableColumn, TableRow } from '@/types/management';
import { useSlots } from 'vue';

const props = withDefaults(defineProps<{
    eyebrow?: string | null;
    title?: string | null;
    description?: string | null;
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

const slots = useSlots();
</script>

<template>
    <div class="min-w-0 space-y-6">
        <PageSection
            v-if="props.title || props.description || props.eyebrow || props.showCreate || slots.actions || slots.stats"
            :eyebrow="props.eyebrow ?? ''"
            :title="props.title ?? ''"
            :description="props.description ?? ''"
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
