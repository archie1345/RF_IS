<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import {
    dashboardContent,
    dashboardSnapshotRows,
    managementRoutes,
} from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Auth } from '@/types/auth';
import type { AppRole, TableColumn } from '@/types/mvp';
import { type BreadcrumbItem } from '@/types';
import { computed } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: managementRoutes.dashboard,
    },
];

const page = usePage<{ auth: Auth }>();

const role = computed<AppRole>(() => {
    const userRole = page.props.auth?.user?.role;

    return userRole === 'admin' ||
        userRole === 'coach' ||
        userRole === 'parent' ||
        userRole === 'athlete'
        ? userRole
        : 'athlete';
});

const content = computed(() => dashboardContent[role.value]);

const snapshotColumns: TableColumn[] = [
    { key: 'workflow', label: 'Workflow' },
    { key: 'owner', label: 'Owner' },
    { key: 'status', label: 'Status' },
    { key: 'target', label: 'Target' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="MVP dashboard"
                :title="content.headline"
                :description="content.description"
            >
                <template #actions>
                    <Button as-child>
                        <Link :href="content.panels[0].ctaHref">
                            {{ content.panels[0].ctaLabel }}
                        </Link>
                    </Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard
                        v-for="metric in content.metrics"
                        :key="metric.label"
                        v-bind="metric"
                    />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-2">
                <section
                    v-for="panel in content.panels"
                    :key="panel.title"
                    class="rounded-3xl border border-border/70 bg-gradient-to-br from-card via-card to-muted/60 p-6 shadow-sm"
                >
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <h2 class="text-xl font-semibold tracking-tight">
                                {{ panel.title }}
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ panel.description }}
                            </p>
                        </div>
                        <ul class="space-y-2 text-sm text-muted-foreground">
                            <li
                                v-for="item in panel.items"
                                :key="item"
                                class="rounded-2xl bg-background/80 px-4 py-3"
                            >
                                {{ item }}
                            </li>
                        </ul>
                        <Button as-child variant="outline">
                            <Link :href="panel.ctaHref">{{ panel.ctaLabel }}</Link>
                        </Button>
                    </div>
                </section>
            </div>

            <DataTable
                title="Cross-team operating snapshot"
                description="A shared view of the highest-priority workflows behind the MVP modules."
                :columns="snapshotColumns"
                :rows="dashboardSnapshotRows"
            />
        </div>
    </AppLayout>
</template>
