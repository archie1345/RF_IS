<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { managementRoutes } from '@/data/management';
import type { BreadcrumbItem } from '@/types';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    children: Array<{
        athlete_id: number;
        name: string;
        email: string;
        branch: string;
        group: string;
        is_active: boolean;
    }>;
    activeChildId: number | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Child Switcher', href: managementRoutes.parentChildSwitcher },
];

function switchChild(athleteId: number) {
    router.post(`/parent/children/${athleteId}/switch`, {}, { preserveScroll: true });
}

function clearChild() {
    router.delete('/parent/children/switch', { preserveScroll: true });
}
</script>

<template>
    <Head title="Child Switcher" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection title="Choose Child Context" description="Select which child account data to view across attendance, payments, and championships.">
                <template #actions>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <Button type="button" variant="outline" class="w-full sm:w-auto" @click="router.visit(managementRoutes.dashboard)">Back to dashboard</Button>
                        <Button v-if="props.activeChildId" type="button" variant="outline" class="w-full sm:w-auto" @click="clearChild">Exit child view</Button>
                    </div>
                </template>
            </PageSection>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="child in props.children" :key="child.athlete_id" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-lg font-semibold">{{ child.name }}</h3>
                            <span v-if="child.is_active" class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Active</span>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ child.email }}</p>
                        <p class="text-sm text-muted-foreground">Branch: {{ child.branch }}</p>
                        <p class="text-sm text-muted-foreground">Group: {{ child.group }}</p>
                    </div>
                    <div class="mt-4">
                        <Button type="button" class="w-full" :variant="child.is_active ? 'outline' : 'default'" @click="switchChild(child.athlete_id)">
                            {{ child.is_active ? 'Currently active' : 'Switch to this child' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
