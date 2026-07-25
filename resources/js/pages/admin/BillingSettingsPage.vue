<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, CircleDollarSign, PencilLine, Plus, ReceiptText, Send, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption } from '@/types/resource-table';

type BillingRuleRecord = {
    id: number;
    name: string;
    charge_kind: 'MONTHLY' | 'ONE_TIME';
    payment_type: string;
    amount: number;
    branch_id: number | null;
    group_id: number | null;
    scope: string;
    due_days: number;
    effective_from: string | null;
    effective_until: string | null;
    is_active: boolean;
    notes: string | null;
    payments_count: number;
};

type GroupOption = SelectOption & { branch_id: number | null };

const props = defineProps<{
    setting: {
        invoice_day: number;
        invoice_time: string;
        default_amount: number;
        is_active: boolean;
    };
    rules: BillingRuleRecord[];
    branches: SelectOption[];
    groups: GroupOption[];
    metrics: Metric[];
}>();

const popup = useAppPopup();
const ruleModalOpen = ref(false);
const editingRuleId = ref<number | null>(null);
const issueModalOpen = ref(false);
const issuingRule = ref<BillingRuleRecord | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: '/dashboard' },
    { title: 'Keuangan', href: '/payments' },
    { title: 'Aturan Tagihan', href: '/admin/billing-settings' },
];

const scheduleForm = useForm({
    invoice_day: String(props.setting.invoice_day),
    invoice_time: props.setting.invoice_time,
    default_amount: String(props.setting.default_amount),
    is_active: props.setting.is_active,
});

const monthlyGenerationForm = useForm({
    month: new Date().toISOString().slice(0, 7),
});

const ruleForm = useForm({
    name: '',
    charge_kind: 'MONTHLY' as 'MONTHLY' | 'ONE_TIME',
    payment_type: 'TUITION',
    amount: '',
    branch_id: '',
    group_id: '',
    due_days: '14',
    effective_from: '',
    effective_until: '',
    is_active: true,
    notes: '',
});

const issueForm = useForm({
    issue_date: new Date().toISOString().slice(0, 10),
});

const chargeKindOptions = [
    { value: 'MONTHLY', label: 'Bulanan / SPP' },
    { value: 'ONE_TIME', label: 'Satu kali' },
];

const paymentTypeOptions = [
    { value: 'TUITION', label: 'Iuran / SPP' },
    { value: 'UNIFORM', label: 'Seragam' },
    { value: 'LICENSE', label: 'Lisensi / UKT' },
    { value: 'CHAMPIONSHIP', label: 'Kejuaraan' },
    { value: 'OTHER', label: 'Lainnya' },
];

const filteredGroupOptions = computed(() => {
    if (!ruleForm.branch_id) return props.groups;

    return props.groups.filter((group) => String(group.branch_id ?? '') === String(ruleForm.branch_id));
});

watch(
    () => ruleForm.charge_kind,
    (kind) => {
        if (kind === 'MONTHLY') ruleForm.payment_type = 'TUITION';
    },
);

watch(
    () => ruleForm.branch_id,
    () => {
        const group = props.groups.find((option) => String(option.value) === String(ruleForm.group_id));
        if (group && ruleForm.branch_id && String(group.branch_id) !== String(ruleForm.branch_id)) {
            ruleForm.group_id = '';
        }
    },
);

function rupiah(value: number | string): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
        Number(value || 0),
    );
}

function kindLabel(kind: BillingRuleRecord['charge_kind']): string {
    return kind === 'MONTHLY' ? 'Bulanan' : 'Satu kali';
}

function periodLabel(rule: BillingRuleRecord): string {
    if (!rule.effective_from && !rule.effective_until) return 'Berlaku tanpa batas periode';
    return `${rule.effective_from ?? 'Awal'} — ${rule.effective_until ?? 'Seterusnya'}`;
}

function saveSchedule(): void {
    scheduleForm.patch('/admin/billing-settings/schedule', { preserveScroll: true });
}

function generateMonthly(): void {
    monthlyGenerationForm.post('/admin/billing-settings/generate-monthly', { preserveScroll: true });
}

function openCreateRule(): void {
    editingRuleId.value = null;
    ruleForm.reset();
    ruleForm.charge_kind = 'MONTHLY';
    ruleForm.payment_type = 'TUITION';
    ruleForm.due_days = '14';
    ruleForm.is_active = true;
    ruleForm.clearErrors();
    ruleModalOpen.value = true;
}

function openEditRule(rule: BillingRuleRecord): void {
    editingRuleId.value = rule.id;
    ruleForm.name = rule.name;
    ruleForm.charge_kind = rule.charge_kind;
    ruleForm.payment_type = rule.payment_type;
    ruleForm.amount = String(rule.amount);
    ruleForm.branch_id = rule.branch_id === null ? '' : String(rule.branch_id);
    ruleForm.group_id = rule.group_id === null ? '' : String(rule.group_id);
    ruleForm.due_days = String(rule.due_days);
    ruleForm.effective_from = rule.effective_from ?? '';
    ruleForm.effective_until = rule.effective_until ?? '';
    ruleForm.is_active = rule.is_active;
    ruleForm.notes = rule.notes ?? '';
    ruleForm.clearErrors();
    ruleModalOpen.value = true;
}

function closeRuleModal(): void {
    ruleModalOpen.value = false;
    editingRuleId.value = null;
    ruleForm.clearErrors();
}

function submitRule(): void {
    const options = {
        preserveScroll: true,
        onSuccess: closeRuleModal,
    };

    if (editingRuleId.value !== null) {
        ruleForm.put(`/admin/billing-rules/${editingRuleId.value}`, options);
        return;
    }

    ruleForm.post('/admin/billing-rules', options);
}

async function archiveRule(rule: BillingRuleRecord): Promise<void> {
    const confirmed = await popup.confirm({
        title: 'Arsipkan aturan tagihan?',
        message: `Aturan “${rule.name}” tidak akan dipakai untuk tagihan baru. ${rule.payments_count} tagihan yang sudah terbit tetap tersimpan.`,
        tone: 'danger',
        confirmLabel: 'Arsipkan aturan',
    });
    if (!confirmed) return;

    router.delete(`/admin/billing-rules/${rule.id}`, { preserveScroll: true });
}

function openIssueModal(rule: BillingRuleRecord): void {
    issuingRule.value = rule;
    issueForm.issue_date = new Date().toISOString().slice(0, 10);
    issueForm.clearErrors();
    issueModalOpen.value = true;
}

function closeIssueModal(): void {
    issueModalOpen.value = false;
    issuingRule.value = null;
    issueForm.clearErrors();
}

function issueOneTimeCharge(): void {
    if (!issuingRule.value) return;
    issueForm.post(`/admin/billing-rules/${issuingRule.value.id}/generate`, {
        preserveScroll: true,
        onSuccess: closeIssueModal,
    });
}
</script>

<template>
    <Head title="Aturan Tagihan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Konfigurasi keuangan"
                title="Aturan Tagihan"
                description="Atur tarif SPP per cabang atau kelas, tarif default, serta template tagihan satu kali."
            >
                <template #actions>
                    <Button as-child variant="outline">
                        <Link href="/payments">Buka ledger pembayaran</Link>
                    </Button>
                    <Button class="gap-2" @click="openCreateRule">
                        <Plus class="size-4" />
                        Buat aturan
                    </Button>
                </template>
            </PageSection>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.75fr)]">
                <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-6">
                    <div class="mb-5 flex items-start gap-3">
                        <span class="rounded-xl bg-primary/10 p-2 text-primary"><CalendarClock class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">Jadwal SPP otomatis</h2>
                            <p class="text-sm text-muted-foreground">
                                Tarif default dipakai ketika atlet tidak cocok dengan aturan cabang atau kelas mana pun.
                            </p>
                        </div>
                    </div>

                    <form class="grid gap-4" @submit.prevent="saveSchedule">
                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <FormInputField
                                id="billing-invoice-day"
                                v-model="scheduleForm.invoice_day"
                                label="Tanggal penerbitan"
                                type="number"
                                min="1"
                                max="28"
                                required
                                :error="scheduleForm.errors.invoice_day"
                            />
                            <FormInputField
                                id="billing-invoice-time"
                                v-model="scheduleForm.invoice_time"
                                label="Waktu proses"
                                type="time"
                                required
                                :error="scheduleForm.errors.invoice_time"
                            />
                            <FormInputField
                                id="billing-default-amount"
                                v-model="scheduleForm.default_amount"
                                label="Tarif default"
                                type="number"
                                min="1"
                                step="1000"
                                required
                                :error="scheduleForm.errors.default_amount"
                            />
                        </div>

                        <label class="flex items-start gap-3 rounded-xl border bg-muted/20 p-3 text-sm">
                            <input v-model="scheduleForm.is_active" type="checkbox" class="mt-1 size-4 rounded border-input" />
                            <span>
                                <strong class="block">Aktifkan penerbitan otomatis</strong>
                                <span class="text-muted-foreground">Scheduler akan memeriksa pengaturan ini setiap hari.</span>
                            </span>
                        </label>

                        <Button type="submit" class="w-full sm:w-fit" :disabled="scheduleForm.processing">Simpan jadwal</Button>
                    </form>
                </section>

                <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-6">
                    <div class="mb-5 flex items-start gap-3">
                        <span class="rounded-xl bg-primary/10 p-2 text-primary"><Send class="size-5" /></span>
                        <div>
                            <h2 class="font-semibold">Terbitkan SPP sekarang</h2>
                            <p class="text-sm text-muted-foreground">
                                Aman dijalankan ulang. Tagihan bulan dan atlet yang sama tidak akan dibuat dua kali.
                            </p>
                        </div>
                    </div>
                    <form class="grid gap-4" @submit.prevent="generateMonthly">
                        <FormInputField
                            id="billing-generation-month"
                            v-model="monthlyGenerationForm.month"
                            label="Bulan tagihan"
                            type="month"
                            required
                            :error="monthlyGenerationForm.errors.month"
                        />
                        <Button type="submit" :disabled="monthlyGenerationForm.processing">Terbitkan tagihan bulanan</Button>
                    </form>
                </section>
            </div>

            <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-6">
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Daftar aturan harga</h2>
                        <p class="text-sm text-muted-foreground">
                            Prioritas SPP: cabang + kelas, kelas, cabang, aturan global, lalu tarif default.
                        </p>
                    </div>
                    <Button variant="outline" class="gap-2" @click="openCreateRule"><Plus class="size-4" /> Tambah aturan</Button>
                </div>

                <div v-if="props.rules.length" class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    <article
                        v-for="rule in props.rules"
                        :key="rule.id"
                        class="flex min-w-0 flex-col rounded-2xl border bg-background p-4 transition hover:border-primary/40 hover:shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">{{ kindLabel(rule.charge_kind) }}</span>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="rule.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                                    >
                                        {{ rule.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </div>
                                <h3 class="mt-3 break-words text-base font-semibold">{{ rule.name }}</h3>
                                <p class="mt-1 break-words text-sm text-muted-foreground">{{ rule.scope }}</p>
                            </div>
                            <ReceiptText class="size-5 shrink-0 text-muted-foreground" />
                        </div>

                        <p class="mt-5 text-2xl font-bold tracking-tight">{{ rupiah(rule.amount) }}</p>
                        <dl class="mt-4 grid gap-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Kategori</dt><dd class="font-medium">{{ rule.payment_type }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Jatuh tempo</dt><dd class="font-medium">{{ rule.due_days }} hari</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Periode</dt><dd class="text-right font-medium">{{ periodLabel(rule) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Tagihan terbit</dt><dd class="font-medium">{{ rule.payments_count }}</dd></div>
                        </dl>
                        <p v-if="rule.notes" class="mt-4 rounded-xl bg-muted/40 p-3 text-sm text-muted-foreground">{{ rule.notes }}</p>

                        <div class="mt-auto grid gap-2 pt-5 sm:grid-cols-2">
                            <Button
                                v-if="rule.charge_kind === 'ONE_TIME' && rule.is_active"
                                type="button"
                                class="gap-2 sm:col-span-2"
                                @click="openIssueModal(rule)"
                            >
                                <CircleDollarSign class="size-4" /> Terbitkan tagihan
                            </Button>
                            <Button type="button" variant="outline" class="gap-2" @click="openEditRule(rule)"><PencilLine class="size-4" /> Ubah</Button>
                            <Button type="button" variant="destructive" class="gap-2" @click="archiveRule(rule)"><Trash2 class="size-4" /> Arsipkan</Button>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-2xl border border-dashed p-8 text-center">
                    <ReceiptText class="mx-auto size-8 text-muted-foreground" />
                    <h3 class="mt-3 font-semibold">Belum ada aturan khusus</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Semua atlet masih memakai tarif default.</p>
                    <Button class="mt-4" @click="openCreateRule">Buat aturan pertama</Button>
                </div>
            </section>
        </div>

        <FormModal :open="ruleModalOpen" max-width-class="max-w-3xl" @close="closeRuleModal">
            <form class="grid min-w-0 gap-5" @submit.prevent="submitRule">
                <PageSection
                    :title="editingRuleId ? 'Ubah aturan tagihan' : 'Buat aturan tagihan'"
                    description="Kosongkan cabang dan kelas untuk membuat aturan global."
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInputField id="billing-rule-name" v-model="ruleForm.name" label="Nama aturan" required :error="ruleForm.errors.name" />
                    <FormSelectField id="billing-rule-kind" v-model="ruleForm.charge_kind" label="Jenis tagihan" :options="chargeKindOptions" required :error="ruleForm.errors.charge_kind" />
                    <FormSelectField
                        id="billing-rule-payment-type"
                        v-model="ruleForm.payment_type"
                        label="Kategori pembayaran"
                        :options="paymentTypeOptions"
                        :disabled="ruleForm.charge_kind === 'MONTHLY'"
                        required
                        :error="ruleForm.errors.payment_type"
                    />
                    <FormInputField id="billing-rule-amount" v-model="ruleForm.amount" label="Nominal" type="number" min="1" step="1000" required :error="ruleForm.errors.amount" />
                    <FormSelectField id="billing-rule-branch" v-model="ruleForm.branch_id" label="Cabang (opsional)" :options="props.branches" placeholder="Semua cabang" :error="ruleForm.errors.branch_id" />
                    <FormSelectField id="billing-rule-group" v-model="ruleForm.group_id" label="Kelas latihan (opsional)" :options="filteredGroupOptions" placeholder="Semua kelas" :error="ruleForm.errors.group_id" />
                    <FormInputField id="billing-rule-due-days" v-model="ruleForm.due_days" label="Batas pembayaran (hari)" type="number" min="0" max="365" required :error="ruleForm.errors.due_days" />
                    <div class="hidden sm:block" />
                    <FormInputField id="billing-rule-effective-from" v-model="ruleForm.effective_from" label="Berlaku mulai" type="date" :error="ruleForm.errors.effective_from" />
                    <FormInputField id="billing-rule-effective-until" v-model="ruleForm.effective_until" label="Berlaku sampai" type="date" :min="ruleForm.effective_from || undefined" :error="ruleForm.errors.effective_until" />
                </div>
                <div class="grid gap-2">
                    <label for="billing-rule-notes" class="text-sm font-medium">Catatan</label>
                    <textarea id="billing-rule-notes" v-model="ruleForm.notes" rows="3" class="rounded-xl border border-input bg-background px-3 py-2 text-sm" />
                    <p v-if="ruleForm.errors.notes" class="text-sm text-destructive">{{ ruleForm.errors.notes }}</p>
                </div>
                <label class="flex items-start gap-3 rounded-xl border bg-muted/20 p-3 text-sm">
                    <input v-model="ruleForm.is_active" type="checkbox" class="mt-1 size-4 rounded border-input" />
                    <span><strong class="block">Aturan aktif</strong><span class="text-muted-foreground">Hanya aturan aktif yang dipakai saat penerbitan.</span></span>
                </label>
                <div class="grid gap-2 sm:flex sm:justify-end">
                    <Button type="button" variant="outline" @click="closeRuleModal">Batal</Button>
                    <Button type="submit" :disabled="ruleForm.processing">Simpan aturan</Button>
                </div>
            </form>
        </FormModal>

        <FormModal :open="issueModalOpen" max-width-class="max-w-xl" @close="closeIssueModal">
            <form class="grid gap-5" @submit.prevent="issueOneTimeCharge">
                <PageSection title="Terbitkan tagihan satu kali" :description="issuingRule ? `${issuingRule.name} — ${issuingRule.scope}` : ''" />
                <div v-if="issuingRule" class="rounded-xl border bg-muted/30 p-4">
                    <p class="text-sm text-muted-foreground">Nominal per atlet</p>
                    <p class="mt-1 text-2xl font-bold">{{ rupiah(issuingRule.amount) }}</p>
                    <p class="mt-2 text-sm text-muted-foreground">Menjalankan ulang aturan pada tanggal terbit yang sama tidak membuat duplikat.</p>
                </div>
                <FormInputField id="one-time-issue-date" v-model="issueForm.issue_date" label="Tanggal terbit" type="date" required :error="issueForm.errors.issue_date" />
                <div class="grid gap-2 sm:flex sm:justify-end">
                    <Button type="button" variant="outline" @click="closeIssueModal">Batal</Button>
                    <Button type="submit" :disabled="issueForm.processing">Terbitkan tagihan</Button>
                </div>
            </form>
        </FormModal>
    </AppLayout>
</template>
