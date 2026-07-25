<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { KeyRound, LayoutDashboard, Palette, ShieldCheck, UserRound } from 'lucide-vue-next';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const navigation = [
    { title: 'Ringkasan', description: 'Status akun dan pintasan', href: '/settings', icon: LayoutDashboard, exact: true },
    { title: 'Profil', description: 'Identitas dan dokumen', href: '/settings/profile', icon: UserRound },
    { title: 'Kata sandi', description: 'Perbarui kredensial', href: '/settings/password', icon: KeyRound },
    { title: 'Dua faktor', description: 'Keamanan login', href: '/settings/two-factor', icon: ShieldCheck },
    { title: 'Tampilan', description: 'Tema aplikasi', href: '/settings/appearance', icon: Palette },
];

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

function active(item: (typeof navigation)[number]): boolean {
    if (item.exact) return page.url.split('?')[0] === item.href;
    return isCurrentUrl(item.href);
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
        <header class="rounded-2xl border bg-card px-4 py-5 shadow-sm sm:px-6">
            <p class="text-xs font-semibold tracking-[0.2em] text-primary uppercase">Akun dan preferensi</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight">Pengaturan</h1>
            <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                Kelola identitas, keamanan, konteks peran, dan tampilan aplikasi dari satu tempat.
            </p>
        </header>

        <div class="grid min-w-0 gap-6 xl:grid-cols-[18rem_minmax(0,1fr)] xl:items-start">
            <aside class="min-w-0 xl:sticky xl:top-4">
                <nav
                    class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-2 xl:mx-0 xl:grid xl:overflow-visible xl:px-0 xl:pb-0"
                    aria-label="Pengaturan"
                >
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="group min-w-[10.5rem] rounded-xl border p-3 transition xl:min-w-0"
                        :class="active(item) ? 'border-primary/40 bg-primary/10 text-primary shadow-sm' : 'border-border bg-card hover:border-primary/30 hover:bg-muted/40'"
                    >
                        <div class="flex items-start gap-3">
                            <component :is="item.icon" class="mt-0.5 size-4 shrink-0" />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ item.title }}</p>
                                <p class="mt-0.5 hidden text-xs text-muted-foreground xl:block">{{ item.description }}</p>
                            </div>
                        </div>
                    </Link>
                </nav>
            </aside>

            <main class="min-w-0">
                <section class="min-w-0 rounded-2xl border border-border/70 bg-card p-4 shadow-sm sm:p-6">
                    <slot />
                </section>
            </main>
        </div>
    </div>
</template>
