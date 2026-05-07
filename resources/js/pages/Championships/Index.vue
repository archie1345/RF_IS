<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DataTable from '@/components/mvp/DataTable.vue';
import PageSection from '@/components/mvp/PageSection.vue';
import StatCard from '@/components/mvp/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { managementRoutes } from '@/data/mvp';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/mvp';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    metrics: Metric[];
    rows: TableRow[];
    athletes: SelectOption[];
    events: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'Championships', href: managementRoutes.championships },
];

const columns: TableColumn[] = [
    { key: 'event', label: 'Championship' },
    { key: 'date', label: 'Date' },
    { key: 'location', label: 'Location' },
    { key: 'registration', label: 'Registration' },
    { key: 'payment', label: 'Payment' },
    { key: 'slots', label: 'Slots', align: 'right' },
];

const form = useForm({
    athlete_id: '',
    event_id: '',
    category: 'KYORUGI',
    division: '',
});

function submit() {
    form.post('/championships/registrations', {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Championships" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection eyebrow="Event module" title="Championships and registrations" description="Track upcoming events and create registration records against live slot availability.">
                <template #actions>
                    <Button type="button">New championship</Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <div class="grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <DataTable title="Upcoming championships" description="Live event list with registration counts from the database." :columns="columns" :rows="props.rows" />

                <PageSection title="Registration checklist" description="Register an athlete into an existing championship event.">
                    <form class="grid gap-4" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="event-athlete">Athlete</Label>
                            <select id="event-athlete" v-model="form.athlete_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                <option value="">Select athlete</option>
                                <option v-for="athlete in props.athletes" :key="athlete.value" :value="athlete.value">
                                    {{ athlete.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.athlete_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="event-name">Championship</Label>
                            <select id="event-name" v-model="form.event_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                <option value="">Select event</option>
                                <option v-for="event in props.events" :key="event.value" :value="event.value">
                                    {{ event.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.event_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="event-category">Category</Label>
                            <select id="event-category" v-model="form.category" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs">
                                <option value="KYORUGI">Kyorugi</option>
                                <option value="POOMSAE">Poomsae</option>
                                <option value="FREESTYLE">Freestyle</option>
                                <option value="UNKNOWN">Unknown</option>
                            </select>
                            <InputError :message="form.errors.category" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="event-weight">Division / weight</Label>
                            <Input id="event-weight" v-model="form.division" placeholder="Junior under 45 kg" />
                            <InputError :message="form.errors.division" />
                        </div>
                        <Button type="submit" :disabled="form.processing">Submit registration</Button>
                    </form>
                </PageSection>
            </div>
        </div>
    </AppLayout>
</template>
