<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Brush,
    ChevronRight,
    CircleDollarSign,
    KeyRound,
    LockKeyhole,
    ReceiptText,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    account: {
        name: string;
        email: string;
        email_verified: boolean;
        account_status: string;
        active_role: string;
        roles: string[];
        is_multi_role: boolean;
        certifications_count: number;
        achievements_count: number;
        two_factor_enabled: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Pengaturan', href: '/settings' }];
const roleForm = useForm({ role: props.account.active_role, redirect_to: '/settings' });

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    coach: 'Pelatih',
    parent: 'Orang tua',
    athlete: 'Atlet',
};

function switchRole(role: string): void {
    if (role === props.account.active_role || roleForm.processing) return;
    roleForm.role = role;
    roleForm.put('/account/active-role', { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pengaturan" />

        <SettingsLayout>
            <div class="space-y-6">
                <section
                    class="overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/10 via-card to-card p-5 sm:p-6"
                >
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-[0.2em] text-primary uppercase">
                                Pusat pengaturan
                            </p>
                            <h1 class="mt-2 text-2xl font-bold tracking-tight break-words">{{ props.account.name }}</h1>
                            <p class="mt-1 text-sm break-all text-muted-foreground">{{ props.account.email }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full border bg-background px-3 py-1 text-xs font-medium"
                                    >Status: {{ props.account.account_status }}</span
                                >
                                <span
                                    class="rounded-full border px-3 py-1 text-xs font-medium"
                                    :class="
                                        props.account.email_verified
                                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                    "
                                >
                                    {{
                                        props.account.email_verified
                                            ? 'Email terverifikasi'
                                            : 'Email belum terverifikasi'
                                    }}
                                </span>
                                <span class="rounded-full border bg-background px-3 py-1 text-xs font-medium"
                                    >Peran aktif:
                                    {{ roleLabels[props.account.active_role] ?? props.account.active_role }}</span
                                >
                            </div>
                        </div>
                        <Button as-child class="w-full sm:w-auto"
                            ><Link href="/settings/profile">Lengkapi profil</Link></Button
                        >
                    </div>
                </section>

                <section v-if="props.account.is_multi_role" class="rounded-2xl border bg-card p-4 sm:p-5">
                    <div class="mb-4">
                        <h2 class="font-semibold">Konteks peran aktif</h2>
                        <p class="text-sm text-muted-foreground">
                            Hak akses, menu, dashboard, dan data mengikuti peran yang sedang dipilih.
                        </p>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <button
                            v-for="role in props.account.roles"
                            :key="role"
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left transition hover:border-primary/50 hover:bg-primary/5"
                            :class="
                                role === props.account.active_role
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'bg-background'
                            "
                            :disabled="roleForm.processing"
                            @click="switchRole(role)"
                        >
                            <span class="block text-sm font-semibold">{{ roleLabels[role] ?? role }}</span>
                            <span class="mt-1 block text-xs text-muted-foreground">{{
                                role === props.account.active_role ? 'Sedang digunakan' : 'Gunakan peran ini'
                            }}</span>
                        </button>
                    </div>
                </section>

                <div class="grid gap-4 md:grid-cols-2">
                    <Link
                        href="/settings/profile"
                        class="group rounded-2xl border bg-card p-5 transition hover:border-primary/40 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span class="rounded-xl bg-primary/10 p-2.5 text-primary"
                                ><UserRound class="size-5"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="font-semibold">Profil dan identitas</h2>
                                    <ChevronRight class="size-4 transition group-hover:translate-x-1" />
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Data akun, foto 3×4, bio, sertifikasi, prestasi, dan informasi sesuai peran.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-muted px-2.5 py-1"
                                        >{{ props.account.certifications_count }} sertifikasi</span
                                    >
                                    <span class="rounded-full bg-muted px-2.5 py-1"
                                        >{{ props.account.achievements_count }} prestasi</span
                                    >
                                </div>
                            </div>
                        </div>
                    </Link>

                    <Link
                        href="/settings/password"
                        class="group rounded-2xl border bg-card p-5 transition hover:border-primary/40 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span class="rounded-xl bg-primary/10 p-2.5 text-primary"><KeyRound class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="font-semibold">Kata sandi</h2>
                                    <ChevronRight class="size-4 transition group-hover:translate-x-1" />
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Ganti kata sandi dengan verifikasi kata sandi saat ini.
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        href="/settings/two-factor"
                        class="group rounded-2xl border bg-card p-5 transition hover:border-primary/40 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span class="rounded-xl bg-primary/10 p-2.5 text-primary"
                                ><ShieldCheck class="size-5"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="font-semibold">Autentikasi dua faktor</h2>
                                    <ChevronRight class="size-4 transition group-hover:translate-x-1" />
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Tambahkan lapisan keamanan untuk login akun.
                                </p>
                                <span
                                    class="mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        props.account.two_factor_enabled
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    >{{ props.account.two_factor_enabled ? 'Aktif' : 'Belum aktif' }}</span
                                >
                            </div>
                        </div>
                    </Link>

                    <Link
                        href="/settings/appearance"
                        class="group rounded-2xl border bg-card p-5 transition hover:border-primary/40 hover:shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span class="rounded-xl bg-primary/10 p-2.5 text-primary"><Brush class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="font-semibold">Tampilan</h2>
                                    <ChevronRight class="size-4 transition group-hover:translate-x-1" />
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Pilih mode terang, gelap, atau mengikuti sistem perangkat.
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>

                <section v-if="props.account.roles.includes('admin')" class="rounded-2xl border bg-card p-4 sm:p-5">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="rounded-xl bg-primary/10 p-2 text-primary"><LockKeyhole class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">Pengaturan administrasi</h2>
                            <p class="text-sm text-muted-foreground">
                                Tautan ini hanya tersedia untuk akun yang memiliki peran admin.
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <Button as-child variant="outline" class="h-auto justify-start gap-3 py-3"
                            ><Link href="/admin/billing-settings"
                                ><ReceiptText class="size-4" /> Aturan tagihan</Link
                            ></Button
                        >
                        <Button as-child variant="outline" class="h-auto justify-start gap-3 py-3"
                            ><Link href="/payments/qris"
                                ><CircleDollarSign class="size-4" /> QRIS pembayaran</Link
                            ></Button
                        >
                        <Button as-child variant="outline" class="h-auto justify-start gap-3 py-3"
                            ><Link href="/admin/activity-logs"
                                ><BadgeCheck class="size-4" /> Log aktivitas</Link
                            ></Button
                        >
                    </div>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
