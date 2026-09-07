<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarRange, ClipboardCheck, CreditCard, Dumbbell, Trophy, UserCog, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { classes as adminClasses, index as adminIndex } from '@/routes/admin';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as championshipsIndex } from '@/routes/championships';
import { index as parentChildrenIndex } from '@/routes/parent/children';
import { index as paymentsIndex } from '@/routes/payments';
import { index as sessionsIndex } from '@/routes/sessions';
import { index as trainingScheduleIndex } from '@/routes/training-schedule';
import type { Auth } from '@/types/auth';
import type { AppRole, Metric } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        role: AppRole;
        roles?: AppRole[];
        metrics: Metric[];
    }>(),
    { roles: () => [] },
);

const page = usePage<{ auth: Auth }>();
const { t } = useI18n();
const firstName = computed(() => page.props.auth.user?.name?.trim().split(/\s+/)[0] || t('Pengguna'));
const dashboardRoles = computed<AppRole[]>(() => (props.roles.length > 0 ? props.roles : [props.role]));
const isMultiRole = computed(() => dashboardRoles.value.length > 1);

const roleLabels: Record<AppRole, string> = {
    admin: 'Admin',
    coach: 'Pelatih',
    parent: 'Orang tua',
    athlete: 'Atlet',
};

const heading = computed(() => {
    if (isMultiRole.value) return t('Selamat Datang, :name', { name: firstName.value });

    const keys: Record<AppRole, string> = {
        admin: 'Selamat datang, :name',
        coach: 'Siap untuk latihan hari ini, :name?',
        parent: 'Ringkasan keluarga Anda',
        athlete: 'Tetap konsisten, :name',
    };

    return t(keys[props.role], { name: firstName.value });
});

const description = computed(() => {
    if (isMultiRole.value) {
        return t('Dashboard ini menggabungkan akses :roles tanpa perlu mengganti tampilan dashboard.', {
            roles: dashboardRoles.value.map((role) => t(roleLabels[role])).join(', '),
        });
    }

    const keys: Record<AppRole, string> = {
        admin: 'Kondisi operasional klub, tindak lanjut, dan aktivitas terbaru dalam satu ringkasan.',
        coach: 'Sesi, absensi, agenda kejuaraan, dan honor yang terhubung ke akun Anda.',
        parent: 'Jadwal, kehadiran, tagihan, dan agenda semua anak yang terhubung.',
        athlete: 'Latihan berikutnya, kehadiran, pembayaran, dan agenda kejuaraan Anda.',
    };

    return t(keys[props.role]);
});

const eyebrow = computed(() =>
    isMultiRole.value ? t('Dashboard gabungan') : t('Ringkasan :role', { role: t(roleLabels[props.role]) }),
);

const quickActions = computed(() => {
    const actions = [] as Array<{ label: string; href: string; icon: typeof UserCog }>;
    const roles = dashboardRoles.value;

    if (roles.includes('admin')) {
        actions.push(
            { label: 'Kelola pengguna', href: adminIndex.url(), icon: UserCog },
            { label: 'Atur kelas', href: adminClasses.url(), icon: Dumbbell },
            { label: 'Payroll', href: '/admin/payroll', icon: CreditCard },
        );
    }
    if (roles.includes('coach')) {
        actions.push(
            { label: 'Sesi latihan', href: sessionsIndex.url(), icon: Dumbbell },
            { label: 'Absensi', href: attendanceIndex.url(), icon: ClipboardCheck },
        );
    }
    if (roles.includes('parent')) {
        actions.push(
            { label: 'Profil anak', href: parentChildrenIndex.url(), icon: UsersRound },
            { label: 'Pembayaran', href: paymentsIndex.url(), icon: CreditCard },
        );
    }
    if (roles.includes('athlete')) {
        actions.push(
            { label: 'Jadwal', href: trainingScheduleIndex.url(), icon: CalendarRange },
            { label: 'Kejuaraan', href: championshipsIndex.url(), icon: Trophy },
        );
    }

    return actions
        .filter((action, index, all) => all.findIndex((candidate) => candidate.href === action.href) === index)
        .slice(0, 6);
});
</script>

<template>
    <PageSection :eyebrow="eyebrow" :title="heading" :description="description">
        <template #actions>
            <Button
                v-for="action in quickActions"
                :key="action.href"
                as-child
                variant="outline"
                size="sm"
                class="gap-2"
            >
                <Link :href="action.href"><component :is="action.icon" class="size-4" />{{ t(action.label) }}</Link>
            </Button>
        </template>

        <!-- <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <StatCard v-for="metric in props.metrics" :key="`${metric.label}-${metric.detail}`" v-bind="metric" />
        </div> -->
    </PageSection>
</template>
