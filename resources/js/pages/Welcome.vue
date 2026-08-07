<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import { dashboard, login } from '@/routes';

const page = usePage<{
    publicAdminWhatsapp?: string;
    publicWhatsappBubbleEnabled?: boolean;
    auth: { user?: unknown };
}>();

const defaultPhone = '6281234567890';

const whatsappUrl = computed(() => {
    const phone = page.props.publicAdminWhatsapp || defaultPhone;
    return buildWhatsAppUrl(phone, 'Halo, saya ingin bertanya mengenai Rhino Fighter Taekwondo.');
});

const showWhatsappBubble = computed(
    () => Boolean(whatsappUrl.value) && page.props.publicWhatsappBubbleEnabled !== false,
);

const highlights = [
    { title: 'Profil & peran', detail: 'Admin, pelatih, orang tua, dan atlet terhubung dalam satu sistem.' },
    { title: 'Latihan', detail: 'Jadwal, kelas, kehadiran, dan sesi privat tetap mudah ditelusuri.' },
    {
        title: 'Keuangan & kompetisi',
        detail: 'Tagihan, payroll, kejuaraan, UKT, serta bukti pembayaran tersimpan rapi.',
    },
];
</script>

<template>
    <Head title="Rhino Fighter IS" />

    <main class="min-h-screen bg-neutral-950 text-white">
        <section class="flex min-h-[78svh] flex-col border-b border-white/10">
            <header class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                <Link :href="dashboard()" class="text-sm font-semibold tracking-[0.24em] uppercase">RF IS</Link>
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="page.props.auth?.user"
                        :href="dashboard()"
                        class="inline-flex h-10 items-center rounded-lg bg-white px-4 text-sm font-semibold text-neutral-950"
                    >
                        Buka dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-10 items-center rounded-lg border border-white/30 px-4 text-sm font-semibold hover:bg-white/10"
                        >
                            Masuk
                        </Link>
                    </template>
                </nav>
            </header>

            <div
                class="mx-auto grid w-full max-w-7xl flex-1 items-center gap-10 px-5 py-14 sm:px-8 lg:grid-cols-[1fr_0.56fr]"
            >
                <div>
                    <p class="mb-4 text-sm font-semibold tracking-[0.28em] text-red-300 uppercase">Rhino Fighter</p>
                    <h1 class="max-w-5xl text-5xl leading-none font-semibold sm:text-7xl lg:text-8xl">
                        Information System
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-neutral-300 sm:text-lg">
                        Satu tempat untuk profil atlet, jadwal latihan, presensi, pembayaran, payroll, pengumuman, UKT,
                        dan kejuaraan.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="page.props.auth?.user ? dashboard() : login()"
                            class="inline-flex h-11 items-center rounded-lg bg-red-500 px-5 text-sm font-semibold hover:bg-red-400"
                        >
                            {{ page.props.auth?.user ? 'Buka dashboard' : 'Masuk ke sistem' }}
                        </Link>
                    </div>
                </div>

                <div class="grid gap-4 border-l border-white/15 pl-6 lg:pl-8">
                    <article
                        v-for="(item, index) in highlights"
                        :key="item.title"
                        class="border-b border-white/15 pb-4 last:border-0 last:pb-0"
                    >
                        <p class="text-xs font-semibold tracking-[0.2em] text-red-300 uppercase">0{{ index + 1 }}</p>
                        <h2 class="mt-2 text-lg font-semibold">{{ item.title }}</h2>
                        <p class="mt-1 text-sm leading-6 text-neutral-300">{{ item.detail }}</p>
                    </article>
                </div>
            </div>
        </section>

        <footer
            class="mx-auto flex w-full max-w-7xl flex-col gap-2 px-5 py-8 text-sm text-neutral-400 sm:flex-row sm:items-center sm:justify-between sm:px-8"
        >
            <span>Rhino Fighter Information System</span>
            <span>Pendaftaran akun baru diproses oleh admin.</span>
        </footer>

        <a
            v-if="showWhatsappBubble"
            :href="whatsappUrl ?? '#'"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Hubungi admin melalui WhatsApp"
            title="Hubungi admin melalui WhatsApp"
            class="fixed right-5 bottom-5 z-50 inline-flex size-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xl ring-1 ring-white/20 transition hover:scale-105 hover:bg-emerald-400 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:right-7 sm:bottom-7"
        >
            <MessageCircle class="size-7" aria-hidden="true" />
        </a>
    </main>
</template>
