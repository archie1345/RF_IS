<script setup lang="ts">
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import type { ParentChild } from '@/types/auth';
import type { AppRole, Metric } from '@/types/management';

const props = defineProps<{
    role: AppRole;
    metrics: Metric[];
    children: ParentChild[];
    activeChild: ParentChild | null;
}>();

const emit = defineEmits<{
    (e: 'switch-child', value: string): void;
}>();
</script>

<template>
    <PageSection eyebrow="Role dashboard" :title="`${props.role.toUpperCase()} dashboard`" description="Role-focused operational overview using reusable dynamic tables.">
        <div v-if="props.role === 'parent'" class="mt-4 max-w-sm">
            <label class="mb-2 block text-sm font-medium">Selected child</label>
            <select
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                :value="props.activeChild?.athlete_id ?? ''"
                @change="emit('switch-child', ($event.target as HTMLSelectElement).value)"
            >
                <option value="">All children</option>
                <option v-for="child in props.children" :key="child.athlete_id" :value="child.athlete_id">
                    {{ child.name }}
                </option>
            </select>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
        </div>
    </PageSection>
</template>

