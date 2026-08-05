<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarRange, ClipboardCheck, CreditCard, Dumbbell, Trophy, UserCog, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
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
const firstName = computed(() => page.props.auth.user?.name?.trim().split(/\s+/)[0] || 'Pengguna');
const dashboardRoles = computed<AppRole[]>(() => (props.roles.length > 0 ? props.roles : [props.role]));
const isMultiRole = computed(() => dashboardRoles.value.length > 1);

const roleLabels: Record<AppRole, string> = {
    admin: 'Admin',
    coach: 'Pelatih',
    parent: 'Orang tua',
    athlete: 'Atlet',
};

const heading = computed(() =>
    isMultiRole.value
        ? `Ringkasan semua peran, ${firstName.value}`
        : {
              admin: `Selamat datang, ${firstName.value}`,
              coach: `Siap untuk latihan hari ini, ${firstName.value}?`,
              parent: 'Ringkasan keluarga Anda',
              athlete: `Tetap konsisten, ${firstName.value}`,
          }[props.role],
);

const description = computed(() =>
    isMultiRole.value
        ? `Dashboard ini menggabungkan akses ${dashboardRoles.value.map((role) => roleLabels[role]).join(', ')} tanpa perlu mengganti tampilan dashboard.`
        : {
              admin: 'Kondisi operasional klub, tindak lanjut, dan aktivitas terbaru dalam satu ringkasan.',
              coach: 'Sesi, absensi, agenda kejuaraan, dan honor yang terhubung ke akun Anda.',
              parent: 'Jadwal, kehadiran, tagihan, dan agenda semua anak yang terhubung.',
              athlete: 'Latihan berikutnya, kehadiran, pembayaran, dan agenda kejuaraan Anda.',
          }[props.role],
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
    <PageSection
        :eyebrow="isMultiRole ? 'Dashboard gabungan' : `Ringkasan ${roleLabels[props.role]}`"
        :title="heading"
        :description="description"
    >
        <template #actions>
            <Button
                v-for="action in quickActions"
                :key="action.href"
                as-child
                variant="outline"
                size="sm"
                class="gap-2"
            >
                <Link :href="action.href"><component :is="action.icon" class="size-4" />{{ action.label }}</Link>
            </Button>
        </template>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard v-for="metric in props.metrics" :key="`${metric.label}-${metric.detail}`" v-bind="metric" />
        </div>
    </PageSection>
</template>
