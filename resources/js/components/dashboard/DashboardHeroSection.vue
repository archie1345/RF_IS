<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
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

const childOptions = (children: ParentChild[]) => [
    ...children.map((child) => ({ value: String(child.athlete_id), label: child.name })),
];

const roleTitle = computed(() => {
    return {
        admin: 'Operations dashboard',
        coach: 'Coach dashboard',
        parent: 'Parent dashboard',
        athlete: 'Athlete dashboard',
    }[props.role];
});

const roleDescription = computed(() => {
    return {
        admin: 'Monitor the club, issue bills, post announcements, and follow recent activity.',
        coach: 'Check sessions, attendance, events, and any bills connected to your account.',
        parent: 'Review children, attendance, events, and bills without digging through admin menus.',
        athlete: 'See your training, events, achievements, and payment status at a glance.',
    }[props.role];
});

const quickActions = computed(() => {
    if (props.role === 'admin') {
        return [
            { label: 'Issue bill', href: managementRoutes.payments },
            { label: 'Post announcement', href: managementRoutes.announcements },
        ];
    }

    if (props.role === 'coach') {
        return [
            { label: 'Sessions', href: managementRoutes.sessions },
            { label: 'Payments', href: managementRoutes.payments },
        ];
    }

    return [
        { label: 'Payments', href: managementRoutes.payments },
        { label: 'Championships', href: managementRoutes.championships },
    ];
});
</script>

<template>
    <PageSection eyebrow="Dashboard" :title="roleTitle" :description="roleDescription">
        <template #actions>
            <div class="flex flex-wrap gap-2">
                <Button v-for="action in quickActions" :key="action.href" as-child variant="outline" size="sm">
                    <Link :href="action.href">{{ action.label }}</Link>
                </Button>
            </div>
        </template>

        <div v-if="props.role === 'parent'" class="mt-4 max-w-sm">
            <FormSelectField
                id="dashboard-selected-child"
                :model-value="String(props.activeChild?.athlete_id ?? '')"
                label="Child shown on dashboard"
                :options="childOptions(props.children)"
                @update:model-value="emit('switch-child', $event)"
            />
        </div>

        <div class="grid gap-4" :class="role === 'admin' ? 'md:grid-cols-4' : 'md:grid-cols-3'">
            <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
        </div>
    </PageSection>
</template>

