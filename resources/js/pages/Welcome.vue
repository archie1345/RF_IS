<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: false,
    },
);

const highlights = [
    {
        title: 'Account roster',
        detail: 'Admins, coaches, parents, and athletes stay connected from one role-aware workspace.',
    },
    {
        title: 'Payments',
        detail: 'Issue bills, track balances, collect receipts, and approve partial payments without a spreadsheet.',
    },
    {
        title: 'Training rhythm',
        detail: 'Sessions, attendance, announcements, and championship activity stay visible to the right people.',
    },
];
</script>

<template>
    <Head title="Rhino Fighter IS" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <section class="relative isolate flex min-h-[82svh] overflow-hidden border-b border-white/10">
            <div class="relative z-10 flex w-full flex-col">
                <header class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                    <Link :href="dashboard()" class="text-sm font-semibold tracking-[0.24em] text-white uppercase">
                        RF IS
                    </Link>

                    <nav class="flex items-center gap-2">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex h-10 items-center rounded-lg bg-white px-4 text-sm font-semibold text-neutral-950 shadow-sm transition hover:bg-cyan-100"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="inline-flex h-10 items-center rounded-lg border border-white/30 px-4 text-sm font-semibold text-white transition hover:border-white hover:bg-white/10"
                            >
                                Log in
                            </Link>
                            <!-- <Link
                                v-if="canRegister"
                                :href="register()"
                                class="hidden h-10 items-center rounded-lg bg-white px-4 text-sm font-semibold text-neutral-950 shadow-sm transition hover:bg-cyan-100 sm:inline-flex"
                            >
                                Register
                            </Link> -->
                        </template>
                    </nav>
                </header>

                <div
                    class="mx-auto grid w-full max-w-7xl flex-1 items-center gap-10 px-5 pt-10 pb-16 sm:px-8 lg:grid-cols-[1fr_0.52fr]"
                >
                    <div>
                        <div
                            class="mb-5 inline-flex rounded-full border border-white/20 px-3 py-1 text-sm text-cyan-100"
                        >
                            Taekwondo club operations
                        </div>

                        <p class="mb-4 text-sm font-semibold tracking-[0.28em] text-brand-coral/70 uppercase">RF IS</p>

                        <h1 class="max-w-5xl text-5xl leading-none font-semibold text-white sm:text-7xl lg:text-8xl">
                            Rhino Fighter Information System
                        </h1>

                        <p class="mt-5 max-w-2xl text-base leading-7 text-neutral-200 sm:text-lg">
                            A focused workspace for athlete profiles, attendance, payments, announcements, and
                            championship preparation.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                :href="$page.props.auth.user ? dashboard() : login()"
                                class="inline-flex h-11 items-center rounded-lg bg-brand-coral px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-coral/90"
                            >
                                {{ $page.props.auth.user ? 'Open dashboard' : 'Log in to dashboard' }}
                            </Link>
                            <Link
                                :href="dashboard()"
                                class="inline-flex h-11 items-center rounded-lg border border-white/25 px-5 text-sm font-semibold text-white transition hover:border-white hover:bg-white/10"
                            >
                                View workspace
                            </Link>
                        </div>
                    </div>

                    <div class="grid gap-4 border-l border-white/15 pl-6 text-sm text-neutral-300 lg:pl-8">
                        <div>
                            <p class="text-4xl font-semibold text-white">4</p>
                            <p class="mt-1">Role-aware portals for admins, coaches, parents, and athletes.</p>
                        </div>
                        <div class="h-px bg-white/15" />
                        <div>
                            <p class="text-4xl font-semibold text-white">1</p>
                            <p class="mt-1">
                                Shared operating system for training, billing, and championship readiness.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-neutral-950 px-5 py-10 sm:px-8 lg:py-14">
            <div class="mx-auto grid max-w-7xl gap-4 md:grid-cols-[1.1fr_2fr]">
                <div class="max-w-xl">
                    <p class="text-sm font-semibold text-brand-coral/70 uppercase">Club command center</p>
                    <h2 class="mt-3 text-2xl font-semibold text-white sm:text-3xl">
                        Built around the daily work of running a team.
                    </h2>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <article
                        v-for="(item, index) in highlights"
                        :key="item.title"
                        class="rounded-lg border border-white/10 bg-white/[0.04] p-5 shadow-sm"
                    >
                        <p class="mb-4 text-xs font-semibold tracking-[0.22em] text-brand-coral/70 uppercase">
                            0{{ index + 1 }}
                        </p>
                        <h3 class="text-base font-semibold text-white">{{ item.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-neutral-300">{{ item.detail }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="border-t border-white/10 bg-white px-5 py-8 text-neutral-950 sm:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-medium">Ready for attendance, billing, profiles, and championships.</p>
                <Link
                    :href="$page.props.auth.user ? dashboard() : login()"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-neutral-950 px-4 text-sm font-semibold text-white transition hover:bg-brand-coral/90"
                >
                    Continue
                </Link>
            </div>
        </section>
    </main>
</template>
