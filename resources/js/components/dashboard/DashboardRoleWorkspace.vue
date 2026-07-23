<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    CalendarClock,
    Check,
    LoaderCircle,
    Megaphone,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as paymentsIndex } from '@/routes/payments';
import { update as roleContextUpdate } from '@/routes/role-context';
import { index as sessionsIndex } from '@/routes/sessions';
import type { AppRole, TableBadgeCell, TableRow, TrainingDay } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        role: AppRole;
        roles?: AppRole[];
        announcements?: TableRow[];
        trainingDays?: TrainingDay[];
        paymentRows?: TableRow[];
    }>(),
    {
        roles: () => [],
        announcements: () => [],
        trainingDays: () => [],
        paymentRows: () => [],
    },
);

const isSwitching = ref(false);

const roleLabels: Record<AppRole, string> = {
    admin: 'Admin',
    coach: 'Coach',
    parent: 'Parent',
    athlete: 'Athlete',
};

const roleDescriptions: Record<AppRole, string> = {
    admin: 'Manage club operations, people, attendance, communications, and finance from one active workspace.',
    coach: 'Focus on assigned training sessions, athlete attendance, events, and coach payment records.',
    parent: 'Review the selected child context, training activity, attendance, announcements, and outstanding bills.',
    athlete: 'Keep track of personal training, attendance, events, announcements, and payment status.',
};

const availableRoles = computed<AppRole[]>(() => {
    const roles = props.roles.length > 0 ? props.roles : [props.role];

    return Array.from(new Set([...roles, props.role]));
});

const isMultiRole = computed(() => availableRoles.value.length > 1);
const today = new Date().toISOString().slice(0, 10);

const nextTraining = computed(() =>
    [...props.trainingDays]
        .filter((training) => training.date >= today)
        .sort((left, right) => `${left.date} ${left.time}`.localeCompare(`${right.date} ${right.time}`))[0] ?? null,
);

const nextTrainingDate = computed(() => {
    if (!nextTraining.value) return 'No upcoming session';

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${nextTraining.value.date}T00:00:00`));
});

function tableBadgeText(value: unknown): string {
    if (value && typeof value === 'object' && 'text' in value) {
        return String((value as TableBadgeCell).text);
    }

    return String(value ?? '');
}

const paymentAttentionCount = computed(() =>
    props.paymentRows.filter((row) => {
        const remainingDigits = String(row.remaining ?? '').replace(/[^0-9]/g, '');

        if (remainingDigits !== '') {
            return Number(remainingDigits) > 0;
        }

        const status = tableBadgeText(row.status).toLowerCase();

        return ['unpaid', 'partial', 'pending', 'waiting'].some((label) => status.includes(label));
    }).length,
);

function switchRole(role: AppRole): void {
    if (role === props.role || isSwitching.value) return;

    router.put(
        roleContextUpdate.url(),
        { role },
        {
            preserveScroll: false,
            preserveState: false,
            onStart: () => {
                isSwitching.value = true;
            },
            onFinish: () => {
                isSwitching.value = false;
            },
        },
    );
}
</script>

<template>
    <section class="rounded-xl border bg-card p-5 shadow-sm md:p-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <BriefcaseBusiness class="size-5" />
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Role workspace</p>
                        <span class="rounded-full border bg-background px-2.5 py-0.5 text-xs font-medium">
                            {{ isMultiRole ? `${availableRoles.length} roles available` : 'Single role account' }}
                        </span>
                    </div>
                    <h2 class="mt-1 text-xl font-bold tracking-tight">Working as {{ roleLabels[props.role] }}</h2>
                    <p class="mt-1 max-w-3xl text-sm text-muted-foreground">{{ roleDescriptions[props.role] }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2" aria-label="Available account roles">
                <Button
                    v-for="availableRole in availableRoles"
                    :key="availableRole"
                    type="button"
                    size="sm"
                    :variant="availableRole === props.role ? 'default' : 'outline'"
                    :disabled="isSwitching"
                    :aria-pressed="availableRole === props.role"
                    class="gap-2"
                    @click="switchRole(availableRole)"
                >
                    <LoaderCircle v-if="isSwitching && availableRole !== props.role" class="size-3.5 animate-spin" />
                    <Check v-else-if="availableRole === props.role" class="size-3.5" />
                    {{ roleLabels[availableRole] }}
                </Button>
            </div>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            <Link
                :href="sessionsIndex.url()"
                class="group rounded-lg border bg-background p-4 transition-colors hover:border-primary/40 hover:bg-muted/30"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Next training</p>
                        <p class="mt-1 font-semibold">{{ nextTrainingDate }}</p>
                        <p class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                            {{
                                nextTraining
                                    ? `${nextTraining.title} · ${nextTraining.time} · ${nextTraining.branch}`
                                    : 'No visible future training session for the active role.'
                            }}
                        </p>
                    </div>
                    <CalendarClock class="size-5 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                </div>
            </Link>

            <Link
                :href="paymentsIndex.url()"
                class="group rounded-lg border bg-background p-4 transition-colors hover:border-primary/40 hover:bg-muted/30"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Payment attention</p>
                        <p class="mt-1 font-semibold">
                            {{ paymentAttentionCount }} {{ paymentAttentionCount === 1 ? 'item' : 'items' }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Open or partially completed records in the current dashboard preview.
                        </p>
                    </div>
                    <WalletCards class="size-5 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                </div>
            </Link>

            <Link
                :href="announcementsIndex.url()"
                class="group rounded-lg border bg-background p-4 transition-colors hover:border-primary/40 hover:bg-muted/30"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Announcements</p>
                        <p class="mt-1 font-semibold">
                            {{ props.announcements.length }} visible {{ props.announcements.length === 1 ? 'notice' : 'notices' }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Notices are filtered for the currently active role.
                        </p>
                    </div>
                    <Megaphone class="size-5 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                </div>
            </Link>
        </div>
    </section>
</template>
