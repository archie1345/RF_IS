<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { athleteRows, managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, TableColumn } from '@/types/mvp';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Athletes', href: managementRoutes.athletes },
];

const metrics: Metric[] = [
    {
        label: 'Active athlete records',
        value: '148',
        detail: '6 waiting for admin approval',
        tone: 'success',
    },
    {
        label: 'Profiles missing documents',
        value: '9',
        detail: 'Parent or ID data still incomplete',
        tone: 'warning',
    },
    {
        label: 'Branch transfers this month',
        value: '4',
        detail: 'Needs roster updates after reassignment',
        tone: 'info',
    },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'branch', label: 'Branch' },
    { key: 'group', label: 'Group' },
    { key: 'geup', label: 'Geup' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Athletes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Admin module"
                title="Athlete CRUD workspace"
                description="A first-pass athlete management template with a roster table and a reusable intake form layout ready for live create, update, and delete actions."
            >
                <template #actions>
                    <Button>New athlete</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                <DataTable
                    title="Current athlete roster"
                    description="Shared table component for list views across the operations modules."
                    :columns="columns"
                    :rows="athleteRows"
                />

                <PageSection
                    title="Athlete intake form"
                    description="This form shell mirrors the fields we already have in the domain models and can be reused for create and edit modes."
                >
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="athlete-name">Full name</Label>
                            <Input id="athlete-name" placeholder="Enter athlete name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="athlete-branch">Branch</Label>
                            <Input id="athlete-branch" placeholder="Jakarta Selatan" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="athlete-group">Group</Label>
                                <Input id="athlete-group" placeholder="Junior Sparring" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="athlete-geup">Geup</Label>
                                <Input id="athlete-geup" placeholder="GEUP 4" />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button>Save athlete</Button>
                            <Button variant="outline" as-child>
                                <Link :href="managementRoutes.dashboard">Cancel</Link>
                            </Button>
                        </div>
                    </div>
                </PageSection>
            </div>
        </div>
    </AppLayout>
</template>
