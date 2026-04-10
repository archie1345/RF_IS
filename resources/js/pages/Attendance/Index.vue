<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { attendanceRows, managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, TableColumn } from '@/types/mvp';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Attendance', href: managementRoutes.attendance },
];

const metrics: Metric[] = [
    {
        label: 'Attendance today',
        value: '89%',
        detail: '94 of 106 expected athletes checked in',
        tone: 'success',
    },
    {
        label: 'Late arrivals',
        value: '7',
        detail: 'Most were from the morning conditioning group',
        tone: 'warning',
    },
    {
        label: 'Repeated absences',
        value: '3',
        detail: 'Needs parent follow-up this week',
        tone: 'info',
    },
];

const columns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'session', label: 'Session' },
    { key: 'coach', label: 'Coach' },
    { key: 'checkin', label: 'Check-in', align: 'right' },
    { key: 'status', label: 'Status' },
];
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Basic module"
                title="Attendance tracking"
                description="The attendance MVP covers the core coaching workflow: capture daily presence, flag issues fast, and keep the roster readable across roles."
            >
                <template #actions>
                    <Button>Mark attendance</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <DataTable
                    title="Session check-ins"
                    description="A shared attendance table that works for admin oversight, coach operations, and parent visibility."
                    :columns="columns"
                    :rows="attendanceRows"
                />

                <PageSection
                    title="Coach note"
                    description="A lightweight side panel for the daily attendance workflow before we wire in full filters and presence actions."
                >
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="attendance-session">Session</Label>
                            <Input id="attendance-session" placeholder="Morning conditioning" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="attendance-note">Issue to track</Label>
                            <Input id="attendance-note" placeholder="Late arrivals from school pickup" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="attendance-followup">Follow-up owner</Label>
                            <Input id="attendance-followup" placeholder="Coach Maya" />
                        </div>
                        <Button>Save note</Button>
                    </div>
                </PageSection>
            </div>
        </div>
    </AppLayout>
</template>
