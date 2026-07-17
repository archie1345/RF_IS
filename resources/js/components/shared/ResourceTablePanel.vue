<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import { useSlots } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import type { TableColumn, TableRow } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
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
        searchable?: boolean;
        searchPlaceholder?: string;
        paginate?: boolean;
        initialLimit?: number;
        pageSize?: number;
    }>(),
    {
        showCreate: true,
        paginate: true,
        initialLimit: 10,
        pageSize: 10,
    },
);

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
                <slot name="actions">
                    <Button v-if="props.showCreate" class="gap-2" @click="$emit('create')">
                        <Plus class="size-4" />
                        {{ props.createLabel ?? 'Create' }}
                    </Button>
                </slot>
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
            :searchable="props.searchable"
            :search-placeholder="props.searchPlaceholder"
            :paginate="props.paginate"
            :initial-limit="props.initialLimit"
            :page-size="props.pageSize"
        >
            <template #row-actions="{ row }">
                <slot name="row-actions" :row="row" />
            </template>
        </DataTable>

        <slot name="after-table" />
    </div>
</template>
