<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, AtSign, MessageCircle } from '@lucide/vue';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import { dashboard, login } from '@/routes';

const page = usePage<{
    publicAdminWhatsapp?: string;
    publicWhatsappBubbleEnabled?: boolean;
    auth: { user?: unknown };
}>();

const instagramUrl = 'https://www.instagram.com/taekwondo_rhino_fighter/?hl=en';
const clubLinksUrl = 'https://linktr.ee/rhinofighter';
const defaultPhone = '6281234567890';
const pageRoot = ref<HTMLElement | null>(null);

const whatsappUrl = computed(() => {
    const phone = page.props.publicAdminWhatsapp || defaultPhone;
    return buildWhatsAppUrl(phone, 'Halo, saya ingin bertanya mengenai Rhino Fighter Taekwondo.');
});

const showWhatsappBubble = computed(
    () => Boolean(whatsappUrl.value) && page.props.publicWhatsappBubbleEnabled !== false,
);

const highlights = [
    {
        number: '01',
        eyebrow: 'TRAIN',
        title: 'Train with intention.',
        detail: 'Setiap sesi dirancang untuk membangun teknik, fokus, dan keberanian.',
        accent: 'bg-[#e85649]',
    },
    {
        number: '02',
        eyebrow: 'PROGRESS',
        title: 'Make progress visible.',
        detail: 'Setiap langkah kecil menjadi bagian dari perjalanan atlet yang lebih kuat.',
        accent: 'bg-[#4892e8]',
    },
    {
        number: '03',
        eyebrow: 'COMMUNITY',
        title: 'Grow stronger together.',
        detail: 'Pelatih, atlet, dan keluarga bergerak dalam satu semangat Rhino Fighter.',
        accent: 'bg-[#d0e848]',
    },
];

let animationContext: gsap.Context | undefined;
let removeCardListeners: (() => void) | undefined;

onMounted(() => {
    if (!pageRoot.value) {
        return;
    }

    const root = pageRoot.value;
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    gsap.registerPlugin(ScrollTrigger);

    animationContext = gsap.context(() => {
        const revealElements = gsap.utils.toArray<HTMLElement>('[data-reveal]');

        if (prefersReducedMotion) {
            gsap.set('[data-reveal], [data-hero-line], [data-hero-card]', {
                autoAlpha: 1,
                clearProps: 'all',
            });
            return;
        }

        const intro = gsap.timeline({ defaults: { ease: 'power3.out' } });

        intro
            .from('[data-header]', { y: -24, autoAlpha: 0, duration: 0.7 })
            .from('[data-hero-kicker]', { x: -20, autoAlpha: 0, duration: 0.55 }, '-=0.35')
            .from('[data-hero-line]', { yPercent: 105, autoAlpha: 0, duration: 0.85, stagger: 0.12 }, '-=0.25')
            .from('[data-hero-copy]', { y: 24, autoAlpha: 0, duration: 0.65 }, '-=0.45')
            .from('[data-hero-card]', { x: 36, autoAlpha: 0, duration: 0.8 }, '-=0.6')
            .from('[data-marquee]', { scaleX: 0, transformOrigin: 'left center', duration: 0.8 }, '-=0.4');

        revealElements.forEach((element) => {
            gsap.fromTo(
                element,
                { y: 42, autoAlpha: 0 },
                {
                    y: 0,
                    autoAlpha: 1,
                    duration: 0.8,
                    ease: 'power3.out',
                    scrollTrigger: { trigger: element, start: 'top 84%', once: true },
                },
            );
        });

        const cardListeners: Array<() => void> = [];

        gsap.utils.toArray<HTMLElement>('[data-card]').forEach((card) => {
            const accent = card.querySelector<HTMLElement>('[data-card-accent]');
            const onEnter = () => {
                gsap.to(card, { y: -8, duration: 0.35, ease: 'power2.out', overwrite: true });
                if (accent) {
                    gsap.to(accent, { scale: 1.8, rotate: 45, duration: 0.35, ease: 'back.out(2)', overwrite: true });
                }
            };
            const onLeave = () => {
                gsap.to(card, { y: 0, duration: 0.45, ease: 'power3.out', overwrite: true });
                if (accent) {
                    gsap.to(accent, { scale: 1, rotate: 0, duration: 0.45, ease: 'power3.out', overwrite: true });
                }
            };

            card.addEventListener('mouseenter', onEnter);
            card.addEventListener('mouseleave', onLeave);
            cardListeners.push(() => {
                card.removeEventListener('mouseenter', onEnter);
                card.removeEventListener('mouseleave', onLeave);
            });
        });

        removeCardListeners = () => cardListeners.forEach((remove) => remove());
    }, root);
});

onBeforeUnmount(() => {
    removeCardListeners?.();
    animationContext?.revert();
});
</script>

<template>
    <Head title="Rhino Fighter Taekwondo" />

    <main ref="pageRoot" class="rf-page min-h-screen overflow-hidden bg-[#0d0e10] text-[#f6f3ec]">
        <section class="relative border-b border-white/12">
            <div class="rf-grid-pointer pointer-events-none absolute inset-0 opacity-60" aria-hidden="true"></div>
            <header
                data-header
                class="relative z-10 mx-auto flex w-full max-w-[1440px] items-center justify-between gap-6 px-5 py-6 sm:px-8 lg:px-12"
            >
                <Link
                    :href="dashboard()"
                    class="group flex items-center gap-3"
                    aria-label="Rhino Fighter Taekwondo home"
                >
                    <span
                        class="flex h-14 w-36 items-center justify-center px-3 py-2 transition-transform group-hover:-rotate-2"
                    >
                        <img src="/rffull.svg" alt="Rhino Fighter Taekwondo" class="h-full w-full object-contain" />
                    </span>
                </Link>
                <nav
                    class="hidden items-center gap-7 text-[11px] font-bold tracking-[0.18em] text-white/55 uppercase md:flex"
                >
                    <a href="#club" class="transition-colors hover:text-white">The club</a>
                    <a href="#values" class="transition-colors hover:text-white">Values</a>
                    <a
                        :href="instagramUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="transition-colors hover:text-white"
                        >Instagram</a
                    >
                </nav>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="page.props.auth?.user"
                        :href="dashboard()"
                        class="inline-flex h-10 items-center gap-2 border border-white/30 px-4 text-xs font-bold tracking-[0.12em] uppercase transition hover:border-[#d0e848] hover:text-[#d0e848]"
                        >Ruang klub <ArrowUpRight class="size-3.5" aria-hidden="true"
                    /></Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-flex h-10 items-center gap-2 bg-[#f6f3ec] px-4 text-xs font-bold tracking-[0.12em] text-[#0d0e10] uppercase transition hover:bg-[#d0e848]"
                        >Masuk <ArrowUpRight class="size-3.5" aria-hidden="true"
                    /></Link>
                </div>
            </header>

            <div
                class="relative z-10 mx-auto grid min-h-[650px] w-full max-w-[1440px] items-end gap-12 px-5 pt-16 pb-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:px-12 lg:pt-28 lg:pb-20"
            >
                <div>
                    <div
                        data-hero-kicker
                        class="mb-7 flex items-center gap-3 text-[11px] font-bold tracking-[0.22em] text-[#e85649] uppercase"
                    >
                        <span class="size-2 rounded-full bg-[#e85649] shadow-[0_0_18px_#e85649]"></span>Taekwondo ·
                        Rhino Fighter
                    </div>
                    <h1
                        data-hero-title
                        class="max-w-5xl text-[clamp(4.7rem,13vw,12.5rem)] leading-[0.78] font-black tracking-[-0.095em] uppercase"
                    >
                        <span class="block overflow-hidden"
                            ><span data-hero-line class="inline-block">Train with</span></span
                        >
                        <span class="block overflow-hidden"
                            ><span data-hero-line class="inline-block text-[#e85649]">purpose.</span></span
                        >
                        <span class="block overflow-hidden"
                            ><span data-hero-line class="inline-block">Grow stronger.</span></span
                        >
                    </h1>
                    <div class="mt-9 flex max-w-2xl flex-col gap-7 sm:flex-row sm:items-end sm:gap-12">
                        <p data-hero-copy class="max-w-md text-base leading-7 text-white/60 sm:text-lg">
                            Rhino Fighter Taekwondo membangun disiplin dan rasa percaya diri — dari matras ke podium,
                            sampai menjadi bagian dari keluarga Rhino Fighter.
                        </p>
                        <a
                            href="#club"
                            class="group inline-flex shrink-0 items-center gap-3 text-xs font-bold tracking-[0.18em] uppercase"
                            >Tentang club
                            <span
                                class="flex size-9 items-center justify-center rounded-full border border-white/35 transition group-hover:border-[#d0e848] group-hover:bg-[#d0e848] group-hover:text-[#0d0e10]"
                                ><ArrowUpRight class="size-4" aria-hidden="true" /></span
                        ></a>
                    </div>
                </div>
                <div
                    data-hero-card
                    class="relative hidden min-h-[370px] border border-white/20 bg-[#141619]/80 p-6 lg:block"
                >
                    <div class="absolute inset-x-0 top-0 h-1 bg-[#d0e848]"></div>
                    <div
                        class="flex items-start justify-between text-[10px] font-bold tracking-[0.2em] text-white/50 uppercase"
                    >
                        <span>RF / CLUB</span><span>01 — 04</span>
                    </div>
                    <div class="mt-16 text-[11rem] leading-none font-black tracking-[-0.14em] text-white/10">01</div>
                    <div class="absolute inset-x-6 bottom-6 border-t border-white/15 pt-4">
                        <p class="text-[11px] font-bold tracking-[0.2em] text-[#d0e848] uppercase">
                            One club. One rhythm.
                        </p>
                        <p class="mt-2 max-w-[230px] text-sm leading-6 text-white/55">
                            Focus on the fighter. We keep the formation moving.
                        </p>
                    </div>
                    <div
                        class="rf-crosshair absolute right-8 bottom-24 size-16 border border-[#4892e8]/80"
                        aria-hidden="true"
                    ></div>
                </div>
            </div>
            <div class="relative z-10 overflow-hidden border-t border-white/12 bg-[#e85649] text-[#0d0e10]">
                <div
                    data-marquee
                    class="rf-marquee flex min-w-max items-center gap-8 py-3 text-sm font-black tracking-[0.18em] uppercase"
                >
                    <span>Discipline</span><span>✳</span><span>Focus</span><span>✳</span><span>Control</span
                    ><span>✳</span><span>Rhino Fighter</span><span>✳</span><span>Discipline</span><span>✳</span
                    ><span>Focus</span><span>✳</span><span>Control</span><span>✳</span><span>Rhino Fighter</span>
                </div>
            </div>
        </section>

        <section id="club" class="relative mx-auto w-full max-w-[1440px] px-5 py-20 sm:px-8 lg:px-12 lg:py-28">
            <div data-reveal class="mb-12 grid gap-8 lg:grid-cols-[0.65fr_1fr] lg:items-end">
                <div>
                    <p class="text-[11px] font-bold tracking-[0.22em] text-[#4892e8] uppercase">
                        /// Rhino Fighter club
                    </p>
                    <h2
                        class="mt-4 max-w-xl text-4xl leading-[0.95] font-black tracking-[-0.06em] uppercase sm:text-6xl"
                    >
                        More than<br />a club.
                    </h2>
                </div>
                <p data-reveal class="max-w-lg text-base leading-7 text-white/55 lg:justify-self-end">
                    Tempat para atlet berlatih dengan tujuan, bertumbuh dengan disiplin, dan melangkah bersama sebagai
                    satu keluarga.
                </p>
            </div>
            <div id="values" class="grid gap-px border border-white/15 bg-white/15 md:grid-cols-3">
                <article
                    v-for="item in highlights"
                    :key="item.number"
                    data-card
                    data-reveal
                    class="group relative min-h-[290px] bg-[#0d0e10] p-6 transition-colors hover:bg-[#15171a] sm:p-8"
                >
                    <div class="mb-14 flex items-center justify-between">
                        <span data-card-accent :class="['size-3', item.accent]"></span
                        ><span class="text-xs font-bold tracking-[0.18em] text-white/40">{{ item.number }}</span>
                    </div>
                    <p class="text-[10px] font-bold tracking-[0.22em] text-white/40 uppercase">{{ item.eyebrow }}</p>
                    <h3 class="mt-3 max-w-xs text-2xl leading-[0.95] font-black tracking-[-0.045em] uppercase">
                        {{ item.title }}
                    </h3>
                    <p class="mt-5 max-w-sm text-sm leading-6 text-white/50">{{ item.detail }}</p>
                    <div class="absolute right-7 bottom-7 opacity-0 transition group-hover:opacity-100">
                        <ArrowUpRight class="size-5 text-[#d0e848]" aria-hidden="true" />
                    </div>
                </article>
            </div>
        </section>

        <section class="border-y border-white/12 bg-[#141619]">
            <div
                class="mx-auto grid w-full max-w-[1440px] gap-12 px-5 py-20 sm:px-8 lg:grid-cols-[0.8fr_1fr] lg:items-center lg:px-12 lg:py-24"
            >
                <div data-reveal>
                    <p class="text-[11px] font-bold tracking-[0.22em] text-[#d0e848] uppercase">Inside the club</p>
                    <h2
                        class="mt-4 max-w-xl text-4xl leading-[0.9] font-black tracking-[-0.06em] uppercase sm:text-6xl"
                    >
                        Train hard.<br />Grow together.
                    </h2>
                    <p class="mt-6 max-w-lg text-base leading-7 text-white/55">
                        Berbasis di Malang, Rhino Fighter membina atlet usia dini hingga dewasa lewat latihan yang
                        kompetitif, disiplin, dan tetap kekeluargaan.
                    </p>
                    <div class="mt-7 flex flex-wrap gap-4">
                        <a
                            :href="instagramUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-3 text-xs font-bold tracking-[0.16em] uppercase transition-colors hover:text-[#d0e848]"
                        >
                            Lihat aktivitas terbaru <AtSign class="size-4" aria-hidden="true" />
                        </a>
                        <a
                            :href="clubLinksUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-3 text-xs font-bold tracking-[0.16em] text-white/45 uppercase transition-colors hover:text-white"
                        >
                            Daftar & kontak <ArrowUpRight class="size-4" aria-hidden="true" />
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-px border border-white/15 bg-white/15">
                    <article
                        data-card
                        data-reveal
                        class="group bg-[#141619] p-6 transition-colors hover:bg-[#1b1e21] sm:p-8"
                    >
                        <div class="mb-10 flex items-center justify-between">
                            <span data-card-accent class="size-3 bg-[#e85649]"></span>
                            <span class="text-xs font-bold tracking-[0.18em] text-white/35">01</span>
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.16em] text-white/45 uppercase">A place to start</p>
                        <h3 class="mt-3 text-2xl leading-[0.95] font-black tracking-[-0.05em] uppercase">
                            Pemula welcome.
                        </h3>
                        <p class="mt-4 text-sm leading-6 text-white/50">
                            Mulai dari dasar, belajar teknik, dan bangun kepercayaan diri bersama pelatih.
                        </p>
                    </article>
                    <article
                        data-card
                        data-reveal
                        class="group bg-[#141619] p-6 transition-colors hover:bg-[#1b1e21] sm:p-8"
                    >
                        <div class="mb-10 flex items-center justify-between">
                            <span data-card-accent class="size-3 bg-[#4892e8]"></span>
                            <span class="text-xs font-bold tracking-[0.18em] text-white/35">02</span>
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.16em] text-white/45 uppercase">A way to grow</p>
                        <h3 class="mt-3 text-2xl leading-[0.95] font-black tracking-[-0.05em] uppercase">
                            Train with purpose.
                        </h3>
                        <p class="mt-4 text-sm leading-6 text-white/50">
                            Latihan rutin untuk poomsae, kyorugi, fisik, fokus, dan mental bertanding.
                        </p>
                    </article>
                    <article
                        data-card
                        data-reveal
                        class="group bg-[#141619] p-6 transition-colors hover:bg-[#1b1e21] sm:p-8"
                    >
                        <div class="mb-10 flex items-center justify-between">
                            <span data-card-accent class="size-3 bg-[#d0e848]"></span>
                            <span class="text-xs font-bold tracking-[0.18em] text-white/35">03</span>
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.16em] text-white/45 uppercase">
                            A team to belong to
                        </p>
                        <h3 class="mt-3 text-2xl leading-[0.95] font-black tracking-[-0.05em] uppercase">
                            Grow as one.
                        </h3>
                        <p class="mt-4 text-sm leading-6 text-white/50">
                            Klub yang kompetitif namun kekeluargaan, dengan dukungan pelatih, teman, dan keluarga.
                        </p>
                    </article>
                    <article
                        data-card
                        data-reveal
                        class="group bg-[#141619] p-6 transition-colors hover:bg-[#1b1e21] sm:p-8"
                    >
                        <div class="mb-10 flex items-center justify-between">
                            <span data-card-accent class="size-3 bg-white"></span>
                            <span class="text-xs font-bold tracking-[0.18em] text-white/35">04</span>
                        </div>
                        <p class="text-[10px] font-bold tracking-[0.16em] text-white/45 uppercase">
                            A journey that continues
                        </p>
                        <h3 class="mt-3 text-2xl leading-[0.95] font-black tracking-[-0.05em] uppercase">
                            Stay in motion.
                        </h3>
                        <p class="mt-4 text-sm leading-6 text-white/50">
                            Ikuti kabar latihan, agenda, dan pencapaian terbaru langsung dari kanal klub.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="border-y border-white/12 bg-[#f6f3ec] text-[#0d0e10]">
            <div
                class="mx-auto grid w-full max-w-[1440px] gap-10 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_0.8fr] lg:items-center lg:px-12 lg:py-24"
            >
                <div>
                    <p class="text-[11px] font-bold tracking-[0.22em] text-[#e85649] uppercase">Ready position</p>
                    <h2
                        class="mt-4 max-w-2xl text-5xl leading-[0.85] font-black tracking-[-0.08em] uppercase sm:text-7xl"
                    >
                        Your next<br />round starts here.
                    </h2>
                </div>
                <div class="lg:justify-self-end">
                    <p class="max-w-md text-base leading-7 text-black/60">
                        Bergabung dengan ritme latihan kami, atau masuk untuk melanjutkan perjalananmu bersama klub.
                    </p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <Link
                            :href="page.props.auth?.user ? dashboard() : login()"
                            class="inline-flex h-12 items-center gap-3 bg-[#e85649] px-5 text-xs font-bold tracking-[0.16em] uppercase transition hover:bg-[#0d0e10] hover:text-white"
                            >{{ page.props.auth?.user ? 'Buka ruang klub' : 'Masuk ke klub'
                            }}<ArrowUpRight class="size-4" aria-hidden="true" /></Link
                        ><a
                            :href="instagramUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-12 items-center gap-3 border border-black/20 px-5 text-xs font-bold tracking-[0.16em] uppercase transition hover:border-black hover:bg-black hover:text-white"
                            ><AtSign class="size-4" aria-hidden="true" /> Instagram</a
                        >
                    </div>
                </div>
            </div>
        </section>

        <footer
            class="mx-auto flex w-full max-w-[1440px] flex-col gap-5 px-5 py-8 text-[10px] font-bold tracking-[0.16em] text-white/40 uppercase sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12"
        >
            <span>Rhino Fighter Taekwondo Club</span><span class="text-[#d0e848]">Train hard. Move sharp.</span
            ><a :href="instagramUrl" target="_blank" rel="noopener noreferrer" class="transition hover:text-white"
                >@taekwondo_rhino_fighter ↗</a
            >
        </footer>
        <a
            v-if="showWhatsappBubble"
            :href="whatsappUrl ?? '#'"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Hubungi admin melalui WhatsApp"
            title="Hubungi admin melalui WhatsApp"
            class="fixed right-5 bottom-5 z-50 inline-flex size-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xl ring-1 ring-white/20 transition hover:scale-105 hover:bg-emerald-400 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:right-7 sm:bottom-7"
            ><MessageCircle class="size-7" aria-hidden="true"
        /></a>
    </main>
</template>

<style scoped>
.rf-page {
    --grid-line: rgba(255, 255, 255, 0.07);
}
.rf-grid-pointer {
    background-image:
        linear-gradient(to right, var(--grid-line) 1px, transparent 1px),
        linear-gradient(to bottom, var(--grid-line) 1px, transparent 1px);
    background-size: 72px 72px;
    mask-image: linear-gradient(to bottom, black, transparent 78%);
}
.rf-crosshair::before,
.rf-crosshair::after {
    position: absolute;
    content: '';
    background: #4892e8;
}
.rf-crosshair::before {
    top: 50%;
    left: -12px;
    width: calc(100% + 24px);
    height: 1px;
}
.rf-crosshair::after {
    top: -12px;
    left: 50%;
    width: 1px;
    height: calc(100% + 24px);
}
.rf-marquee {
    animation: rf-marquee 24s linear infinite;
}
@keyframes rf-marquee {
    to {
        transform: translateX(-50%);
    }
}
@media (prefers-reduced-motion: reduce) {
    .rf-marquee {
        animation: none;
    }
}
</style>
