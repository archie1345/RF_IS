<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import PageSection from '@/components/mvp/PageSection.vue';
import DataTable from '@/components/mvp/DataTable.vue';
import { Button } from '@/components/ui/button';
import type { TableColumn, TableRow } from '@/types/mvp';

defineProps<{
    eyebrow?: string;
    title: string;
    description: string;
    createLabel: string;
    tableTitle: string;
    tableDescription: string;
    columns: TableColumn[];
    rows: TableRow[];
    emptyText?: string;
    actionLabel?: string;
}>();

defineEmits<{
    create: [];
}>();
</script>

<template>
    <div class="space-y-6">
        <PageSection
            :eyebrow="eyebrow"
            :title="title"
            :description="description"
        >
            <template #actions>
                <Button class="gap-2" @click="$emit('create')">
                    <Plus class="size-4" />
                    {{ createLabel }}
                </Button>
            </template>

            <slot name="stats" />
        </PageSection>

        <DataTable
            :title="tableTitle"
            :description="tableDescription"
            :columns="columns"
            :rows="rows"
            :empty-text="emptyText"
            :action-label="actionLabel"
        >
            <template #row-actions="{ row }">
                <slot name="row-actions" :row="row" />
            </template>
        </DataTable>

        <slot name="after-table" />
    </div>
</template>
