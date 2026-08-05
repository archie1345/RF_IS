<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import { dashboard } from '@/routes';
import {
    exportMethod as championshipExport,
    index as championshipsIndex,
    show as championshipShow,
} from '@/routes/championships';
import { destroy as championshipCoachDestroy, store as championshipCoachStore } from '@/routes/championships/coaches';
import {
    destroy as championshipRegistrationDestroy,
    result as championshipRegistrationResult,
    update as championshipRegistrationUpdate,
} from '@/routes/championships/registrations';
import { show as userShow } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    isAdmin: boolean;
    isAthlete: boolean;
    canManageCoaches: boolean;
    canRecordResult: boolean;
    canDeleteRegistration: boolean;
    canAddRegistration: boolean;
    event: {
        id: number;
        name: string;
        date: string;
        location: string;
        gmaps_url?: string | null;
        entry_fee: number;
        status: string;
        registration_deadline: string;
        registration_open: boolean;
    };
    athleteRows: TableRow[];
    coachRows: TableRow[];
    coachOptions: SelectOption[];
    registrationAthleteOptions: SelectOption[];
}>();

const popup = useAppPopup();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Kejuaraan & UKT', href: championshipsIndex.url() },
    { title: props.event.name, href: championshipShow.url(props.event.id) },
];

const athleteColumns: TableColumn[] = [
    { key: 'athlete', label: 'Atlet' },
    { key: 'classification', label: 'Klasifikasi' },
    { key: 'division', label: 'Divisi' },
    { key: 'class_name', label: 'Kelas' },
    { key: 'category', label: 'Kategori' },
    { key: 'team_contingent', label: 'Kontingen' },
    { key: 'status', label: 'Status' },
];
const coachColumns: TableColumn[] = [
    { key: 'coach', label: 'Pelatih' },
    { key: 'role', label: 'Peran' },
];
const categoryOptions = [
    { value: 'KYORUGI', label: 'Kyorugi' },
    { value: 'POOMSAE', label: 'Poomsae' },
    { value: 'FREESTYLE', label: 'Freestyle' },
    { value: 'UNKNOWN', label: 'Belum ditentukan' },
];
const medalOptions = [
    { value: 'GOLD', label: 'Emas' },
    { value: 'SILVER', label: 'Perak' },
    { value: 'BRONZE', label: 'Perunggu' },
    { value: 'NONE', label: 'Tanpa medali' },
];

const participantModalOpen = ref(false);
const coachModalOpen = ref(false);
const resultModalOpen = ref(false);
const editModalOpen = ref(false);
const activeRegistrationId = ref<number | null>(null);
const editingOwnRegistration = ref(false);

const participantForm = useForm({
    athlete_id: '',
    category: 'KYORUGI',
    classification: '',
    class_name: '',
    division: '',
    team_contingent: 'Rhino Fighter',
    create_payment: false,
});
const coachForm = useForm({ coach_id: '', role: '' });
const resultForm = useForm({ medal: 'NONE', class_name: '', division: '', category: '' });
const editForm = useForm({
    category: 'KYORUGI',
    classification: '',
    class_name: '',
    division: '',
    team_contingent: 'Rhino Fighter',
    registration: '',
});

const canAddNow = computed(() => props.canAddRegistration && props.registrationAthleteOptions.length > 0);

function openParticipantModal(): void {
    participantForm.reset();
    participantForm.category = 'KYORUGI';
    participantForm.team_contingent = 'Rhino Fighter';
    participantForm.create_payment = !props.isAdmin;
    participantForm.clearErrors();
    participantModalOpen.value = true;
}

function submitParticipant(): void {
    participantForm.post(`/championships/${props.event.id}/participants`, {
        preserveScroll: true,
        onSuccess: () => {
            participantModalOpen.value = false;
            participantForm.reset();
        },
    });
}

function addCoach(): void {
    coachForm.post(championshipCoachStore.url(props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            coachForm.reset();
            coachModalOpen.value = false;
        },
    });
}

function openRegistrationEdit(row: TableRow): void {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null) return;

    activeRegistrationId.value = registrationId;
    editingOwnRegistration.value = row.is_own_registration === true;
    editForm.category = String(row.category ?? 'KYORUGI');
    editForm.classification = row.classification === '-' ? '' : String(row.classification ?? '');
    editForm.class_name = row.class_name === '-' ? '' : String(row.class_name ?? '');
    editForm.division = row.division === '-' ? '' : String(row.division ?? '');
    editForm.team_contingent = String(row.team_contingent ?? 'Rhino Fighter');
    editForm.registration = '';
    editForm.clearErrors();
    editModalOpen.value = true;
}

function saveRegistrationEdit(): void {
    if (activeRegistrationId.value === null) return;
    editForm.put(championshipRegistrationUpdate.url(activeRegistrationId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editModalOpen.value = false;
            activeRegistrationId.value = null;
        },
    });
}

function openResultForm(row: TableRow): void {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null) return;

    activeRegistrationId.value = registrationId;
    resultForm.reset();
    resultForm.medal = 'NONE';
    resultForm.class_name = row.class_name === '-' ? '' : String(row.class_name ?? '');
    resultForm.division = row.division === '-' ? '' : String(row.division ?? '');
    resultForm.category = String(row.category ?? '');
    resultModalOpen.value = true;
}

function saveResult(): void {
    if (activeRegistrationId.value === null) return;
    resultForm.post(championshipRegistrationResult.url(activeRegistrationId.value), {
        preserveScroll: true,
        onSuccess: () => {
            resultModalOpen.value = false;
            activeRegistrationId.value = null;
        },
    });
}

function athleteProfileUrl(row: TableRow): string | null {
    const userId = routeId(row.athlete_user_id);
    return userId === null ? null : userShow.url(userId);
}

async function removeRegistration(row: TableRow): Promise<void> {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null) return;
    const confirmed = await popup.confirm({
        title: 'Hapus peserta?',
        message: `Entri ${String(row.athlete ?? '')} akan dihapus. Riwayat pembayaran dapat memblokir tindakan ini.`,
        tone: 'danger',
        confirmLabel: 'Hapus peserta',
    });
    if (confirmed) router.delete(championshipRegistrationDestroy.url(registrationId), { preserveScroll: true });
}

async function removeCoach(row: TableRow): Promise<void> {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null) return;
    const confirmed = await popup.confirm({
        title: 'Hapus penugasan pelatih?',
        message: `Penugasan ${String(row.coach ?? '')} akan dihapus dari event ini.`,
        tone: 'danger',
        confirmLabel: 'Hapus penugasan',
    });
    if (confirmed) router.delete(championshipCoachDestroy.url(registrationId), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Kejuaraan - ${props.event.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Detail kejuaraan"
                :title="props.event.name"
                description="Daftar peserta dapat dilihat oleh atlet. Perubahan, hasil, pelatih, dan penambahan peserta mengikuti hak akses masing-masing."
            >
                <template #actions>
                    <Button v-if="canAddNow" type="button" class="gap-2" @click="openParticipantModal">
                        <Plus class="size-4" /> Tambah atlet
                    </Button>
                    <Button
                        v-if="props.canManageCoaches"
                        type="button"
                        variant="outline"
                        @click="coachModalOpen = true"
                    >
                        Tambah pelatih
                    </Button>
                    <Button v-if="props.canManageCoaches" as-child type="button" variant="outline">
                        <a :href="championshipExport.url(props.event.id)">Ekspor CSV</a>
                    </Button>
                    <Button v-if="props.isAdmin" as-child type="button" variant="outline" class="gap-2">
                        <a :href="`/championships/${props.event.id}/photos`"><Download class="size-4" /> Foto 3×4</a>
                    </Button>
                    <Button as-child type="button" variant="ghost">
                        <Link :href="championshipsIndex.url()">Kembali</Link>
                    </Button>
                </template>

                <dl class="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2 xl:grid-cols-5">
                    <div>
                        <dt class="text-xs text-muted-foreground">Tanggal</dt>
                        <dd class="mt-1 font-semibold">{{ props.event.date }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Batas pendaftaran</dt>
                        <dd class="mt-1 font-semibold">{{ props.event.registration_deadline }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Lokasi</dt>
                        <dd class="mt-1 font-semibold">{{ props.event.location }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Biaya masuk</dt>
                        <dd class="mt-1 font-semibold">Rp {{ props.event.entry_fee.toLocaleString('id-ID') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-1 font-semibold">{{ props.event.status }}</dd>
                    </div>
                    <div v-if="props.event.gmaps_url" class="sm:col-span-2 xl:col-span-5">
                        <a
                            :href="props.event.gmaps_url"
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-primary hover:underline"
                            >Buka lokasi di peta</a
                        >
                    </div>
                </dl>
            </PageSection>

            <DataTable
                title="Peserta terdaftar"
                description="Atlet lain hanya dapat dilihat. Atlet mengubah entri sendiri sebelum batas waktu; staf mengelola sesuai penugasan."
                :columns="athleteColumns"
                :rows="props.athleteRows"
                searchable
                filterable
                search-placeholder="Cari atlet, kelas, kategori, atau kontingen"
                empty-text="Belum ada atlet yang terdaftar."
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            v-if="row.can_edit_registration === true"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openRegistrationEdit(row)"
                        >
                            {{ row.is_own_registration === true ? 'Ubah pendaftaran' : 'Ubah entri' }}
                        </Button>
                        <Button
                            v-if="props.isAdmin && athleteProfileUrl(row)"
                            as-child
                            type="button"
                            size="sm"
                            variant="outline"
                        >
                            <Link :href="athleteProfileUrl(row) ?? '#'">Profil</Link>
                        </Button>
                        <Button
                            v-if="props.canRecordResult"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openResultForm(row)"
                        >
                            Catat hasil
                        </Button>
                        <Button
                            v-if="props.canDeleteRegistration"
                            type="button"
                            size="sm"
                            variant="destructive"
                            @click="removeRegistration(row)"
                        >
                            Hapus
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>

            <DataTable
                v-if="props.canManageCoaches || props.coachRows.length > 0"
                title="Pelatih pendamping"
                description="Pelatih yang ditugaskan untuk mendampingi event ini."
                :columns="coachColumns"
                :rows="props.coachRows"
                empty-text="Belum ada pelatih yang ditugaskan."
            >
                <template v-if="props.isAdmin" #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="destructive" @click="removeCoach(row)">Hapus</Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal :open="participantModalOpen" max-width-class="max-w-2xl" @close="participantModalOpen = false">
            <PageSection
                title="Tambah atlet ke daftar peserta"
                :description="
                    props.isAdmin && !props.event.registration_open
                        ? 'Admin dapat mencatat atlet yang terlewat meskipun event sudah berjalan atau selesai.'
                        : 'Pilih atlet yang berada dalam akses kepelatihan Anda dan isi data pertandingan.'
                "
            >
                <form class="grid gap-4" @submit.prevent="submitParticipant">
                    <FormSelectField
                        id="managed-event-athlete"
                        v-model="participantForm.athlete_id"
                        label="Atlet"
                        :options="props.registrationAthleteOptions"
                        placeholder="Pilih atlet"
                        required
                        :error="participantForm.errors.athlete_id"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormSelectField
                            id="managed-event-category"
                            v-model="participantForm.category"
                            label="Kategori"
                            :options="categoryOptions"
                            required
                            :error="participantForm.errors.category"
                        />
                        <FormInputField
                            id="managed-event-classification"
                            v-model="participantForm.classification"
                            label="Klasifikasi"
                            :error="participantForm.errors.classification"
                        />
                        <FormInputField
                            id="managed-event-class"
                            v-model="participantForm.class_name"
                            label="Kelas"
                            :error="participantForm.errors.class_name"
                        />
                        <FormInputField
                            id="managed-event-division"
                            v-model="participantForm.division"
                            label="Divisi"
                            :error="participantForm.errors.division"
                        />
                    </div>
                    <FormInputField
                        id="managed-event-team"
                        v-model="participantForm.team_contingent"
                        label="Tim/Kontingen"
                        :error="participantForm.errors.team_contingent"
                    />
                    <label class="flex items-start gap-3 rounded-lg border p-3 text-sm">
                        <input v-model="participantForm.create_payment" type="checkbox" class="mt-1 size-4" />
                        <span
                            ><strong class="block">Buat tagihan biaya masuk</strong
                            ><span class="text-muted-foreground"
                                >Matikan untuk pencatatan peserta lama atau event pasca-pelaksanaan.</span
                            ></span
                        >
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="participantModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="participantForm.processing">Tambahkan atlet</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="coachModalOpen && props.canManageCoaches"
            max-width-class="max-w-xl"
            @close="coachModalOpen = false"
        >
            <PageSection title="Tambah pelatih" description="Tugaskan pelatih untuk mendampingi kejuaraan ini.">
                <form class="grid gap-4" @submit.prevent="addCoach">
                    <FormSelectField
                        v-if="props.isAdmin"
                        id="championship-coach"
                        v-model="coachForm.coach_id"
                        label="Pelatih"
                        :options="props.coachOptions"
                        placeholder="Pilih pelatih"
                        :error="coachForm.errors.coach_id"
                    />
                    <FormInputField
                        id="championship-coach-role"
                        v-model="coachForm.role"
                        label="Peran"
                        placeholder="Pelatih utama / Pendamping"
                        :error="coachForm.errors.role"
                    />
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="coachModalOpen = false">Batal</Button
                        ><Button type="submit" :disabled="coachForm.processing">Tambahkan</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="editModalOpen" max-width-class="max-w-xl" @close="editModalOpen = false">
            <PageSection
                :title="editingOwnRegistration ? 'Ubah pendaftaran saya' : 'Ubah entri peserta'"
                :description="
                    editingOwnRegistration
                        ? `Dapat diperbarui sampai ${props.event.registration_deadline}.`
                        : 'Perbarui data pertandingan peserta.'
                "
            >
                <form class="grid gap-4" @submit.prevent="saveRegistrationEdit">
                    <FormSelectField
                        id="edit-category"
                        v-model="editForm.category"
                        label="Kategori"
                        :options="categoryOptions"
                        :error="editForm.errors.category"
                    />
                    <FormInputField
                        id="edit-classification"
                        v-model="editForm.classification"
                        label="Klasifikasi"
                        :error="editForm.errors.classification"
                    />
                    <FormInputField
                        id="edit-class-name"
                        v-model="editForm.class_name"
                        label="Kelas"
                        :error="editForm.errors.class_name"
                    />
                    <FormInputField
                        id="edit-division"
                        v-model="editForm.division"
                        label="Divisi"
                        :error="editForm.errors.division"
                    />
                    <FormInputField
                        id="edit-team"
                        v-model="editForm.team_contingent"
                        label="Tim/Kontingen"
                        :error="editForm.errors.team_contingent"
                    />
                    <p v-if="editForm.errors.registration" class="text-sm text-destructive">
                        {{ editForm.errors.registration }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="editModalOpen = false">Batal</Button
                        ><Button type="submit" :disabled="editForm.processing">Simpan</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="resultModalOpen && props.canRecordResult"
            max-width-class="max-w-xl"
            @close="resultModalOpen = false"
        >
            <PageSection
                title="Catat hasil pertandingan"
                description="Hasil ini membuat atau memperbarui prestasi atlet."
            >
                <form class="grid gap-4" @submit.prevent="saveResult">
                    <FormSelectField
                        id="result-medal"
                        v-model="resultForm.medal"
                        label="Medali"
                        :options="medalOptions"
                        :error="resultForm.errors.medal"
                    />
                    <FormInputField
                        id="result-class"
                        v-model="resultForm.class_name"
                        label="Kelas"
                        :error="resultForm.errors.class_name"
                    />
                    <FormInputField
                        id="result-division"
                        v-model="resultForm.division"
                        label="Divisi"
                        :error="resultForm.errors.division"
                    />
                    <FormInputField
                        id="result-category"
                        v-model="resultForm.category"
                        label="Kategori"
                        :error="resultForm.errors.category"
                    />
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="resultModalOpen = false">Batal</Button
                        ><Button type="submit" :disabled="resultForm.processing">Simpan hasil</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
