<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { championshipRows, managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, TableColumn } from '@/types/mvp';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Championships', href: managementRoutes.championships },
];

const metrics: Metric[] = [
    {
        label: 'Open registrations',
        value: '2',
        detail: 'One closes within the next 72 hours',
        tone: 'warning',
    },
    {
        label: 'Athletes submitted',
        value: '61',
        detail: '12 entries still missing documents',
        tone: 'info',
    },
    {
        label: 'Approved for travel',
        value: '44',
        detail: 'Parents completed consent for most entries',
        tone: 'success',
    },
];

const columns: TableColumn[] = [
    { key: 'event', label: 'Championship' },
    { key: 'date', label: 'Date' },
    { key: 'location', label: 'Location' },
    { key: 'registration', label: 'Registration' },
    { key: 'slots', label: 'Slots', align: 'right' },
];
</script>

<template>
    <Head title="Championships" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Event module"
                title="Championships and registrations"
                description="This MVP page combines event publishing with athlete registration readiness so admins, coaches, and parents can align around one entry workflow."
            >
                <template #actions>
                    <Button>New championship</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <DataTable
                    title="Upcoming championships"
                    description="Reusable event list pattern that can later expand with filters, registrations, and approval states."
                    :columns="columns"
                    :rows="championshipRows"
                />

                <PageSection
                    title="Registration checklist"
                    description="A simple form shell for the entry workflow. It is intentionally compact so it can become a modal or slide-over later."
                >
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="event-athlete">Athlete</Label>
                            <Input id="event-athlete" placeholder="Select athlete" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="event-name">Championship</Label>
                            <Input id="event-name" placeholder="Regional Spring Cup" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="event-weight">Division / weight</Label>
                            <Input id="event-weight" placeholder="Junior under 45 kg" />
                        </div>
                        <Button>Submit registration</Button>
                    </div>
                </PageSection>
            </div>
        </div>
    </AppLayout>
</template>
