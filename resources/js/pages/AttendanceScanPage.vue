<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import ScanStatusBanner from '@/components/attendance/ScanStatusBanner.vue';
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
    { title: 'Phone Attendance', href: `/attendance/scan/${props.token}` },
];

function checkIn() {
    router.post(`/attendance/scan/${props.token}`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Phone Attendance Check-in" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex min-h-[70vh] w-full max-w-md flex-col justify-center gap-4 p-4">
            <section class="rounded-3xl border bg-card p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.22em] text-red-500">RF Attendance</p>
                <h1 class="mt-1 text-2xl font-black">Phone Check-in</h1>
                <p class="mt-2 text-sm text-muted-foreground">Use a mobile phone for this attendance flow.</p>
            </section>

            <section v-if="props.session" class="rounded-3xl border bg-card p-5 shadow-sm">
                <p class="text-sm font-medium text-muted-foreground">Session</p>
                <h2 class="mt-1 text-xl font-black">{{ props.session.title }}</h2>
                <div class="mt-4 grid gap-2 text-sm">
                    <p><span class="text-muted-foreground">Date:</span> {{ props.session.date }}</p>
                    <p><span class="text-muted-foreground">Time:</span> {{ props.session.time }}</p>
                    <p><span class="text-muted-foreground">Location:</span> {{ props.session.location ?? props.session.branch ?? '-' }}</p>
                    <p><span class="text-muted-foreground">Group:</span> {{ props.session.group ?? '-' }}</p>
                </div>
            </section>

            <ScanStatusBanner :state="props.state" :message="props.message" />

            <section class="rounded-3xl border bg-card p-5 shadow-sm">
                <p class="text-sm font-medium text-muted-foreground">Athlete</p>
                <div class="mt-3 rounded-xl border bg-muted p-4">
                    <p v-if="props.athlete?.name" class="font-semibold">{{ props.athlete.name }}</p>
                    <p v-else class="font-semibold">Athlete login required</p>
                    <p v-if="props.athlete?.current_status" class="mt-1 text-sm">Current status: {{ props.athlete.current_status }}</p>
                </div>

                <div class="mt-4 grid gap-2">
                    <Button v-if="props.canSubmit" type="button" class="w-full" @click="checkIn">Check in now</Button>
                    <Button as-child type="button" variant="outline" class="w-full">
                        <a href="/dashboard">Done / Back to dashboard</a>
                    </Button>
                </div>

                <p v-if="!props.deviceAllowed" class="mt-3 text-center text-xs text-muted-foreground">Open this page from a phone to continue.</p>
            </section>
        </div>
    </AppLayout>
</template>
