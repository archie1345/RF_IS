<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarRange,
    ClipboardCheck,
    CreditCard,
    Dumbbell,
    Trophy,
    UserCog,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import RoleSwitcher from '@/components/RoleSwitcher.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { classes as adminClasses, index as adminIndex } from '@/routes/admin';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as championshipsIndex } from '@/routes/championships';
import { index as parentChildrenIndex } from '@/routes/parent/children';
import { index as paymentsIndex } from '@/routes/payments';
import { index as sessionsIndex } from '@/routes/sessions';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import type { Auth, ParentChild } from '@/types/auth';
import type { AppRole, Metric } from '@/types/resource-table';

const props = defineProps<{
    role: AppRole;
    metrics: Metric[];
    children: ParentChild[];
    activeChild: ParentChild | null;
}>();

const emit = defineEmits<{
    (e: 'switch-child', value: string): void;
}>();

const page = usePage<{ auth: Auth }>();
const firstName = computed(() => page.props.auth.user?.name?.trim().split(/\s+/)[0] || 'Pengguna');
const isMultiRole = computed(() => (page.props.auth.user?.roles?.length ?? 0) > 1);

const childOptions = (children: ParentChild[]) => [
    { value: '', label: 'Semua anak' },
    ...children.map((child) => ({ value: String(child.athlete_id), label: child.name })),
];

function switchChild(value: string | string[]): void {
    emit('switch-child', Array.isArray(value) ? (value[0] ?? '') : value);
}

const heading = computed(() => {
    if (props.role === 'admin') return `Selamat datang, ${firstName.value}`;
    if (props.role === 'coach') return `Siap untuk latihan hari ini, ${firstName.value}?`;
    if (props.role === 'parent') {
        return props.activeChild?.name
            ? `Perkembangan ${props.activeChild.name}`
            : 'Ringkasan anak Anda';
    }

    return `Tetap konsisten, ${firstName.value}`;
});

const description = computed(() => {
    return {
        admin: 'Lihat kondisi operasional klub, hal yang perlu ditindaklanjuti, dan aktivitas terbaru dalam satu ringkasan.',
        coach: 'Prioritaskan sesi terdekat, absensi latihan, agenda kejuaraan, dan honor yang terhubung ke akun Anda.',
        parent: 'Pantau jadwal, kehadiran, tagihan, dan informasi penting untuk anak yang sedang dipilih.',
        athlete: 'Cek latihan berikutnya, rekam kehadiran, status pembayaran, dan agenda kejuaraan Anda.',
    }[props.role];
});

const eyebrow = computed(() => {
    return {
        admin: 'Ringkasan Operasional',
        coach: 'Ruang Kerja Pelatih',
        parent: 'Ringkasan Orang Tua',
        athlete: 'Ringkasan Atlet',
    }[props.role];
});

const displayMetrics = computed<Metric[]>(() => {
    const copy: Record<AppRole, Array<Pick<Metric, 'label' | 'detail'>>> = {
        admin: [
            { label: 'Atlet aktif', detail: 'Jumlah atlet dalam roster klub' },
            { label: 'Pelatih terdaftar', detail: 'Akun pelatih yang tercatat' },
            { label: 'Tagihan belum lunas', detail: 'Total saldo pembayaran terbuka' },
            { label: 'Absensi hari ini', detail: 'Catatan kehadiran yang dibuat hari ini' },
        ],
        coach: [
            { label: 'Atlet hadir', detail: 'Kehadiran dari sesi yang Anda tangani' },
            { label: 'Agenda mendatang', detail: 'Kejuaraan dan kegiatan yang dijadwalkan' },
            { label: 'Honor tertunda', detail: 'Sisa honor yang belum diselesaikan' },
        ],
        parent: [
            { label: 'Konteks anak', detail: 'Data mengikuti anak yang sedang dipilih' },
            { label: 'Tagihan anak', detail: 'Saldo terbuka dari anak yang dapat Anda akses' },
            { label: 'Agenda mendatang', detail: 'Kejuaraan dan kegiatan yang tersedia' },
        ],
        athlete: [
            { label: 'Kehadiran saya', detail: 'Jumlah sesi dengan status hadir' },
            { label: 'Agenda mendatang', detail: 'Kejuaraan dan kegiatan yang dijadwalkan' },
            { label: 'Tagihan saya', detail: 'Saldo pembayaran yang belum selesai' },
        ],
    };

    return props.metrics.map((metric, index) => ({
        ...metric,
        ...(copy[props.role][index] ?? {}),
        value:
            props.role === 'parent' && index === 0
                ? props.activeChild?.name || (props.children.length > 1 ? 'Semua anak' : props.children[0]?.name || '-')
                : metric.value,
    }));
});

const quickActions = computed(() => {
    if (props.role === 'admin') {
        return [
            { label: 'Kelola pengguna', href: adminIndex.url(), icon: UserCog },
            { label: 'Atur kelas', href: adminClasses.url(), icon: Dumbbell },
            { label: 'Buka keuangan', href: paymentsIndex.url(), icon: CreditCard },
        ];
    }

    if (props.role === 'coach') {
        return [
            { label: 'Sesi saya', href: sessionsIndex.url(), icon: Dumbbell },
            { label: 'Absensi latihan', href: attendanceIndex.url(), icon: ClipboardCheck },
            { label: 'Jadwal', href: trainingScheduleIndex.url(), icon: CalendarRange },
        ];
    }

    if (props.role === 'parent') {
        return [
            { label: 'Profil anak', href: parentChildrenIndex.url(), icon: UsersRound },
            { label: 'Pembayaran', href: paymentsIndex.url(), icon: CreditCard },
            { label: 'Jadwal anak', href: trainingScheduleIndex.url(), icon: CalendarRange },
        ];
    }

    return [
        { label: 'Scan absensi', href: attendanceIndex.url(), icon: ClipboardCheck },
        { label: 'Jadwal saya', href: trainingScheduleIndex.url(), icon: CalendarRange },
        { label: 'Kejuaraan', href: championshipsIndex.url(), icon: Trophy },
    ];
});
</script>

<template>
    <PageSection :eyebrow="eyebrow" :title="heading" :description="description">
        <template #actions>
            <div class="flex flex-wrap items-center gap-2">
                <RoleSwitcher v-if="isMultiRole" />
                <Button
                    v-for="action in quickActions"
                    :key="action.href"
                    as-child
                    variant="outline"
                    size="sm"
                    class="gap-2"
                >
                    <Link :href="action.href">
                        <component :is="action.icon" class="size-4" />
                        {{ action.label }}
                    </Link>
                </Button>
            </div>
        </template>

        <div v-if="props.role === 'parent' && props.children.length > 1" class="mb-5 max-w-sm">
            <FormSelectField
                id="dashboard-selected-child"
                :model-value="String(props.activeChild?.athlete_id ?? '')"
                label="Anak yang ditampilkan"
                :options="childOptions(props.children)"
                :show-placeholder="false"
                @update:model-value="switchChild"
            />
        </div>

        <div class="grid gap-3 sm:grid-cols-2" :class="role === 'admin' ? 'xl:grid-cols-4' : 'xl:grid-cols-3'">
            <StatCard v-for="metric in displayMetrics" :key="metric.label" v-bind="metric" />
        </div>
    </PageSection>
</template>
