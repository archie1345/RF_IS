<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes, sessionRows } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, TableColumn } from '@/types/mvp';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Coach Sessions', href: managementRoutes.sessions },
];

const metrics: Metric[] = [
    {
        label: 'Scheduled sessions',
        value: '28',
        detail: 'Across 4 branches this week',
        tone: 'info',
    },
    {
        label: 'Coach coverage',
        value: '93%',
        detail: 'One weekend slot still unassigned',
        tone: 'warning',
    },
    {
        label: 'Competition blocks',
        value: '6',
        detail: 'Focused prep plans currently active',
        tone: 'success',
    },
];

const columns: TableColumn[] = [
    { key: 'session', label: 'Session' },
    { key: 'branch', label: 'Branch' },
    { key: 'coach', label: 'Coach' },
    { key: 'schedule', label: 'Schedule' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Coach Sessions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Coach module"
                title="Coach session planner"
                description="The session planner template gives the coaching team a clean calendar-adjacent workspace before we introduce drag-and-drop scheduling."
            >
                <template #actions>
                    <Button>Schedule session</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <DataTable
                    title="Session lineup"
                    description="Reusable operations table for coach scheduling, branch assignments, and training block visibility."
                    :columns="columns"
                    :rows="sessionRows"
                />

                <PageSection
                    title="Session draft"
                    description="This panel is ready to evolve into a shared create and edit experience for coach sessions."
                >
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="session-name">Session name</Label>
                            <Input id="session-name" placeholder="Junior sparring block" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="session-branch">Branch</Label>
                            <Input id="session-branch" placeholder="Jakarta Selatan" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="session-schedule">Schedule</Label>
                            <Input id="session-schedule" placeholder="Mon, Wed, Fri 16:00" />
                        </div>
                        <Button>Save schedule</Button>
                    </div>
                </PageSection>
            </div>
        </div>
    </AppLayout>
</template>
