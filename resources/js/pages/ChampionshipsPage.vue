<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import { dashboard } from '@/routes';
import { index as championshipsIndex, show as championshipShow } from '@/routes/championships';
import {
    destroy as championshipEventDestroy,
    store as championshipEventStore,
    update as championshipEventUpdate,
} from '@/routes/championships/events';
import {
    store as championshipRegistrationStore,
    update as championshipRegistrationUpdate,
} from '@/routes/championships/registrations';
import { index as paymentsIndex } from '@/routes/payments';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

type ExistingRegistration = {
    registration_id: number;
    category: string;
    classification: string;
    class_name: string;
    division: string;
    team_contingent: string;
};

const props = withDefaults(
    defineProps<{
        isAdmin: boolean;
        isAthlete?: boolean;
        canRegister: boolean;
        metrics: Metric[];
        rows: TableRow[];
        athletes: SelectOption[];
        events: SelectOption[];
        pendingPayments: { payment_id: number; athlete: string; amount: number; remaining: number }[];
    }>(),
    {
        isAthlete: false,
    },
);
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Kejuaraan & UKT', href: championshipsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'event', label: 'Kejuaraan' },
    { key: 'date', label: 'Tanggal' },
    { key: 'registration_deadline', label: 'Batas pendaftaran' },
    { key: 'location', label: 'Lokasi' },
    { key: 'status', label: 'Status' },
    { key: 'slots', label: 'Peserta', align: 'right' },
];

const categoryOptions = [
    { value: 'KYORUGI', label: 'Kyorugi' },
    { value: 'POOMSAE', label: 'Poomsae' },
    { value: 'FREESTYLE', label: 'Freestyle' },
    { value: 'UNKNOWN', label: 'Belum ditentukan' },
];

const levelOptions = [
    { value: 'LOCAL', label: 'Lokal' },
    { value: 'REGIONAL', label: 'Regional' },
    { value: 'NATIONAL', label: 'Nasional' },
    { value: 'INTERNATIONAL', label: 'Internasional' },
];

const statusOptions = [
    { value: 'SCHEDULED', label: 'Pendaftaran dibuka' },
    { value: 'ONGOING', label: 'Sedang berlangsung' },
    { value: 'COMPLETED', label: 'Selesai' },
    { value: 'CANCELED', label: 'Dibatalkan' },
];

const registrationForm = useForm({
    athlete_id: '',
    event_id: '',
    category: 'KYORUGI',
    classification: '',
    class_name: '',
    division: '',
    team_contingent: 'Rhino Fighter',
    registration: '',
});

const eventForm = useForm({
    name: '',
    date: '',
    registration_deadline: '',
    location: '',
    gmaps_url: '',
    entry_fee: '',
    max_slots: '24',
    level: 'LOCAL',
    status: 'SCHEDULED',
    event: '',
});

const showRegistrationForm = ref(false);
const showEventForm = ref(false);
const showPaymentPrompt = ref(false);
const editingEventId = ref<number | null>(null);
const editingRegistrationId = ref<number | null>(null);
const registrationEventLabel = ref('');

function existingRegistration(row: TableRow): ExistingRegistration | null {
    const value = row.my_registration;
    if (!value || typeof value !== 'object') return null;
    return value as ExistingRegistration;
}

function resetRegistrationForm(): void {
    registrationForm.reset();
    registrationForm.clearErrors();
    registrationForm.category = 'KYORUGI';
    registrationForm.team_contingent = 'Rhino Fighter';
    editingRegistrationId.value = null;
    registrationEventLabel.value = '';
    if (props.isAthlete && props.athletes.length === 1) {
        registrationForm.athlete_id = String(props.athletes[0].value);
    }
}

function closeRegistrationForm(): void {
    showRegistrationForm.value = false;
    resetRegistrationForm();
}

function submitRegistration(): void {
    const registrationId = editingRegistrationId.value;
    const wasEditing = registrationId !== null;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeRegistrationForm();
            if (!wasEditing && props.pendingPayments.length > 0) showPaymentPrompt.value = true;
        },
    };

    if (registrationId !== null) {
        registrationForm.put(championshipRegistrationUpdate.url(registrationId), options);
        return;
    }

    registrationForm.post(championshipRegistrationStore.url(), options);
}

function resetEventForm(): void {
    eventForm.reset();
    eventForm.clearErrors();
    eventForm.max_slots = '24';
    eventForm.level = 'LOCAL';
    eventForm.status = 'SCHEDULED';
    eventForm.event = '';
    editingEventId.value = null;
}

function openCreateEvent(): void {
    resetEventForm();
    showEventForm.value = true;
}

function openEditEvent(row: TableRow): void {
    const eventId = routeId(row.event_id ?? row.id);
    if (eventId === null) return;

    eventForm.clearErrors();
    editingEventId.value = eventId;
    eventForm.name = String(row.event ?? '');
    eventForm.date = String(row.date_value ?? '');
    eventForm.registration_deadline = String(row.registration_deadline_value ?? '');
    eventForm.location = String(row.location ?? '');
    eventForm.gmaps_url = String(row.gmaps_url ?? '');
    eventForm.entry_fee = String(row.entry_fee ?? '0');
    eventForm.max_slots = String(row.max_slots ?? '24');
    eventForm.level = String(row.level ?? 'LOCAL');
    eventForm.status = String(row.status_value ?? row.status ?? 'SCHEDULED');
    eventForm.event = '';
    showEventForm.value = true;
}

function closeEventForm(): void {
    showEventForm.value = false;
    resetEventForm();
}

function submitEvent(): void {
    const options = {
        preserveScroll: true,
        onSuccess: closeEventForm,
    };

    if (editingEventId.value !== null) {
        eventForm.put(championshipEventUpdate.url(editingEventId.value), options);
        return;
    }

    eventForm.post(championshipEventStore.url(), options);
}

async function removeEvent(row: TableRow): Promise<void> {
    const eventId = routeId(row.event_id ?? row.id);
    if (eventId === null) return;

    const registrationCount = Number(row.registrations_count ?? 0);
    if (registrationCount > 0) {
        await popup.warning(
            'Kejuaraan tidak dapat dihapus',
            'Event ini sudah memiliki riwayat peserta. Ubah status menjadi Dibatalkan agar data pendaftaran, pembayaran, dan hasil tetap dapat ditelusuri.',
        );
        openEditEvent(row);
        return;
    }

    const confirmed = await popup.confirm({
        title: 'Hapus kejuaraan?',
        message: `Kejuaraan “${String(row.event ?? '')}” akan dihapus. Tindakan ini hanya tersedia untuk event tanpa peserta.`,
        tone: 'danger',
        confirmLabel: 'Hapus kejuaraan',
    });
    if (!confirmed) return;

    router.delete(championshipEventDestroy.url(eventId), { preserveScroll: true });
}

function openPaymentPrompt(): void {
    if (props.pendingPayments.length === 0) return;
    showPaymentPrompt.value = true;
}

function openRegistrationForEvent(row: TableRow): void {
    if (row.registration_open !== true) return;

    resetRegistrationForm();
    registrationForm.event_id = String(row.event_id ?? '');
    registrationEventLabel.value = String(row.event ?? '');

    const registration = existingRegistration(row);
    if (registration) {
        const registrationId = routeId(registration.registration_id);
        if (registrationId === null) return;

        editingRegistrationId.value = registrationId;
        registrationForm.category = registration.category || 'KYORUGI';
        registrationForm.classification = registration.classification || '';
        registrationForm.class_name = registration.class_name || '';
        registrationForm.division = registration.division || '';
        registrationForm.team_contingent = registration.team_contingent || 'Rhino Fighter';
    }

    showRegistrationForm.value = true;
}

function championshipUrl(row: TableRow): string | null {
    const eventId = routeId(row.event_id);
    return eventId === null ? null : championshipShow.url(eventId);
}

onMounted(() => {
    resetRegistrationForm();
    if (props.pendingPayments.length > 0) openPaymentPrompt();
});
</script>

<template>
    <Head title="Kejuaraan & UKT" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Kompetisi"
                title="Kejuaraan & UKT"
                description="Lihat agenda kompetisi, daftar peserta, biaya pendaftaran, dan hasil pertandingan dalam satu tempat."
            >
                <template #actions>
                    <Button
                        v-if="props.pendingPayments.length > 0"
                        type="button"
                        variant="outline"
                        @click="openPaymentPrompt"
                    >
                        Lihat tagihan pendaftaran
                    </Button>
                    <Button v-if="props.isAdmin" type="button" @click="openCreateEvent">Tambah kejuaraan</Button>
                </template>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard v-for="metric in props.metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <DataTable
                title="Daftar kejuaraan"
                description="Atlet yang sudah terdaftar dapat mengubah data pertandingan sendiri sampai batas waktu yang ditetapkan admin."
                :columns="columns"
                :rows="props.rows"
                action-label="Tindakan"
                empty-text="Belum ada kejuaraan atau UKT."
                searchable
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            v-if="props.isAthlete && row.my_registration && row.can_edit_registration === true"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openRegistrationForEvent(row)"
                        >
                            Ubah pendaftaran
                        </Button>
                        <Button
                            v-else-if="props.canRegister && !row.my_registration && row.registration_open === true"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openRegistrationForEvent(row)"
                        >
                            Daftar
                        </Button>
                        <Button v-if="championshipUrl(row)" as-child type="button" size="sm" variant="outline">
                            <Link :href="championshipUrl(row) ?? '#'">Detail</Link>
                        </Button>
                        <Button
                            v-if="props.isAdmin"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openEditEvent(row)"
                        >
                            Ubah
                        </Button>
                        <Button
                            v-if="props.isAdmin"
                            type="button"
                            size="sm"
                            variant="destructive"
                            @click="removeEvent(row)"
                        >
                            Hapus
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal :open="showRegistrationForm" max-width-class="max-w-2xl" @close="closeRegistrationForm">
            <PageSection
                :title="editingRegistrationId === null ? 'Daftar kejuaraan' : 'Ubah pendaftaran kejuaraan'"
                :description="
                    editingRegistrationId === null
                        ? 'Pilih atlet dan isi klasifikasi pertandingan. Tagihan pendaftaran akan dibuat otomatis.'
                        : `Perbarui data pertandingan untuk ${registrationEventLabel} sebelum batas pendaftaran.`
                "
            >
                <form class="grid min-w-0 gap-4" @submit.prevent="submitRegistration">
                    <FormSelectField
                        v-if="props.athletes.length > 1"
                        id="event-athlete"
                        v-model="registrationForm.athlete_id"
                        label="Atlet"
                        :options="props.athletes"
                        placeholder="Pilih atlet"
                        required
                        :disabled="editingRegistrationId !== null"
                        :error="registrationForm.errors.athlete_id"
                    />
                    <div v-else-if="props.athletes.length === 1" class="grid min-w-0 gap-2">
                        <label class="text-sm font-medium">Atlet</label>
                        <input
                            :value="props.athletes[0].label"
                            disabled
                            class="h-11 min-w-0 rounded-md border border-input bg-muted px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormSelectField
                            id="event-name"
                            v-model="registrationForm.event_id"
                            label="Kejuaraan"
                            :options="props.events"
                            placeholder="Pilih kejuaraan"
                            required
                            :disabled="editingRegistrationId !== null"
                            :error="registrationForm.errors.event_id"
                        />
                        <FormSelectField
                            id="event-category"
                            v-model="registrationForm.category"
                            label="Kategori"
                            :options="categoryOptions"
                            required
                            :error="registrationForm.errors.category"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <FormInputField
                            id="event-classification"
                            v-model="registrationForm.classification"
                            label="Klasifikasi"
                            placeholder="Cadet / Junior / Senior"
                            :error="registrationForm.errors.classification"
                        />
                        <FormInputField
                            id="event-class-name"
                            v-model="registrationForm.class_name"
                            label="Kelas"
                            placeholder="Under 45 kg / Individual"
                            :error="registrationForm.errors.class_name"
                        />
                        <FormInputField
                            id="event-division"
                            v-model="registrationForm.division"
                            label="Divisi"
                            placeholder="Putra / Putri"
                            :error="registrationForm.errors.division"
                        />
                    </div>
                    <FormInputField
                        id="event-team"
                        v-model="registrationForm.team_contingent"
                        label="Tim atau kontingen"
                        placeholder="Rhino Fighter"
                        :error="registrationForm.errors.team_contingent"
                    />
                    <p v-if="registrationForm.errors.registration" class="text-sm text-destructive">
                        {{ registrationForm.errors.registration }}
                    </p>
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="closeRegistrationForm">Batal</Button>
                        <Button type="submit" :disabled="registrationForm.processing">
                            {{ editingRegistrationId === null ? 'Kirim pendaftaran' : 'Simpan perubahan' }}
                        </Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showEventForm && props.isAdmin" max-width-class="max-w-2xl" @close="closeEventForm">
            <PageSection
                :title="editingEventId === null ? 'Tambah kejuaraan' : 'Ubah kejuaraan'"
                description="Atur tanggal, batas pendaftaran, lokasi, biaya, kapasitas, tingkat, dan status agenda."
            >
                <form class="grid min-w-0 gap-4" @submit.prevent="submitEvent">
                    <FormInputField
                        id="event-new-name"
                        v-model="eventForm.name"
                        label="Nama kejuaraan"
                        required
                        :error="eventForm.errors.name"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInputField
                            id="event-new-date"
                            v-model="eventForm.date"
                            label="Tanggal"
                            type="date"
                            required
                            :error="eventForm.errors.date"
                        />
                        <FormInputField
                            id="event-registration-deadline"
                            v-model="eventForm.registration_deadline"
                            label="Batas pendaftaran"
                            type="datetime-local"
                            :error="eventForm.errors.registration_deadline"
                            help="Jika dikosongkan, batas otomatis menjadi akhir hari pada tanggal kejuaraan."
                        />
                    </div>
                    <FormSelectField
                        id="event-new-status"
                        v-model="eventForm.status"
                        label="Status"
                        :options="statusOptions"
                        required
                        :error="eventForm.errors.status"
                    />
                    <FormInputField
                        id="event-new-location"
                        v-model="eventForm.location"
                        label="Lokasi"
                        placeholder="Contoh: GOR Ken Arok"
                        required
                        :error="eventForm.errors.location"
                    />
                    <FormInputField
                        id="event-new-gmaps"
                        v-model="eventForm.gmaps_url"
                        label="Tautan Google Maps"
                        type="url"
                        placeholder="https://maps.google.com/..."
                        :error="eventForm.errors.gmaps_url"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInputField
                            id="event-new-price"
                            v-model="eventForm.entry_fee"
                            label="Biaya pendaftaran"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="1000"
                            required
                            :error="eventForm.errors.entry_fee"
                        />
                        <FormInputField
                            id="event-new-slots"
                            v-model="eventForm.max_slots"
                            label="Kapasitas atlet"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            step="1"
                            required
                            :error="eventForm.errors.max_slots"
                        />
                    </div>
                    <FormSelectField
                        id="event-new-level"
                        v-model="eventForm.level"
                        label="Tingkat kompetisi"
                        :options="levelOptions"
                        :error="eventForm.errors.level"
                    />
                    <p v-if="eventForm.errors.event" class="text-sm text-destructive">{{ eventForm.errors.event }}</p>
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="closeEventForm">Batal</Button>
                        <Button type="submit" :disabled="eventForm.processing">Simpan kejuaraan</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showPaymentPrompt" max-width-class="max-w-xl" @close="showPaymentPrompt = false">
            <PageSection
                title="Tagihan pendaftaran tersedia"
                description="Buka halaman pembayaran untuk melihat petunjuk dan mengunggah bukti pembayaran."
            >
                <div
                    v-if="props.pendingPayments.length > 0"
                    class="grid gap-2 rounded-xl border bg-muted/30 p-4 text-sm"
                >
                    <p><span class="font-medium">Atlet:</span> {{ props.pendingPayments[0].athlete }}</p>
                    <p>
                        <span class="font-medium">Jumlah:</span>
                        Rp {{ props.pendingPayments[0].amount.toLocaleString('id-ID') }}
                    </p>
                    <p>
                        <span class="font-medium">Sisa:</span>
                        Rp {{ props.pendingPayments[0].remaining.toLocaleString('id-ID') }}
                    </p>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                    <Button type="button" variant="outline" @click="showPaymentPrompt = false">Nanti</Button>
                    <Button as-child><Link :href="paymentsIndex.url()">Buka pembayaran</Link></Button>
                </div>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
