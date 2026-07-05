<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    token: string;
    deviceAllowed: boolean;
    state: string;
    message: string | null;
    canSubmit: boolean;
    session: {
        title: string;
        date: string;
        time: string;
        location: string | null;
        branch: string | null;
        group: string | null;
        opens_at: string | null;
        closes_at: string | null;
    } | null;
    athlete: {
        name: string | null;
        current_status: string | null;
    } | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'QR Attendance', href: `/attendance/scan/${props.token}` },
];

function checkIn() {
    router.post(`/attendance/scan/${props.token}`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="QR Attendance Check-in" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex min-h-[70vh] w-full max-w-md flex-col justify-center gap-4 p-4">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <p class="text-sm font-medium text-muted-foreground">Phone attendance</p>
                <h1 class="mt-1 text-2xl font-semibold">QR Check-in</h1>
                <p class="mt-2 text-sm text-muted-foreground">This page only records attendance from a mobile phone. Laptop and desktop check-ins are blocked.</p>
            </section>

            <section v-if="props.session" class="rounded-2xl border bg-card p-5 shadow-sm">
                <p class="text-sm font-medium text-muted-foreground">Session</p>
                <h2 class="mt-1 text-xl font-semibold">{{ props.session.title }}</h2>
                <div class="mt-4 grid gap-2 text-sm">
                    <p><span class="text-muted-foreground">Date:</span> {{ props.session.date }}</p>
                    <p><span class="text-muted-foreground">Time:</span> {{ props.session.time }}</p>
                    <p><span class="text-muted-foreground">Location:</span> {{ props.session.location ?? '-' }}</p>
                    <p><span class="text-muted-foreground">Branch:</span> {{ props.session.branch ?? '-' }}</p>
                    <p><span class="text-muted-foreground">Group:</span> {{ props.session.group ?? '-' }}</p>
                </div>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <p class="text-sm font-medium text-muted-foreground">Status</p>
                <div class="mt-3 rounded-xl border bg-muted p-4">
                    <p class="font-semibold">{{ props.message ?? 'Check this QR attendance status.' }}</p>
                    <p v-if="props.athlete?.name" class="mt-2 text-sm">Athlete: {{ props.athlete.name }}</p>
                    <p v-if="props.athlete?.current_status" class="mt-1 text-sm">Current status: {{ props.athlete.current_status }}</p>
                </div>

                <div class="mt-4 grid gap-2">
                    <Button v-if="props.canSubmit" type="button" class="w-full" @click="checkIn">Check in</Button>
                    <Button as-child type="button" variant="outline" class="w-full">
                        <a href="/dashboard">Done / Back to dashboard</a>
                    </Button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
