<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { ref } from 'vue';
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
import {
    destroy as championshipCoachDestroy,
    store as championshipCoachStore,
} from '@/routes/championships/coaches';
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
    { key: 'team_contingent', label: 'Tim/Kontingen' },
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

const showCoachForm = ref(false);
const showResultForm = ref(false);
const showRegistrationEditForm = ref(false);
const activeRegistrationId = ref<number | null>(null);
const editingOwnRegistration = ref(false);
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

function addCoach(): void {
    coachForm.post(championshipCoachStore.url(props.event.id), {
        onSuccess: () => {
            coachForm.reset();
            showCoachForm.value = false;
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
    showResultForm.value = true;
}

function openRegistrationEdit(row: TableRow): void {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null || row.can_edit_registration !== true) return;

    activeRegistrationId.value = registrationId;
    editingOwnRegistration.value = row.is_own_registration === true;
    editForm.clearErrors();
    editForm.category = String(row.category ?? 'KYORUGI');
    editForm.classification = row.classification === '-' ? '' : String(row.classification ?? '');
    editForm.class_name = row.class_name === '-' ? '' : String(row.class_name ?? '');
    editForm.division = row.division === '-' ? '' : String(row.division ?? '');
    editForm.team_contingent = String(row.team_contingent ?? 'Rhino Fighter');
    showRegistrationEditForm.value = true;
}

function athleteProfileUrl(row: TableRow): string | null {
    const userId = routeId(row.athlete_user_id);
    return userId === null ? null : userShow.url(userId);
}

function saveRegistrationEdit(): void {
    if (activeRegistrationId.value === null) return;

    editForm.put(championshipRegistrationUpdate.url(activeRegistrationId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showRegistrationEditForm.value = false;
            activeRegistrationId.value = null;
            editingOwnRegistration.value = false;
        },
    });
}

function saveResult(): void {
    if (activeRegistrationId.value === null) return;

    resultForm.post(championshipRegistrationResult.url(activeRegistrationId.value), {
        onSuccess: () => {
            showResultForm.value = false;
            activeRegistrationId.value = null;
        },
    });
}

async function removeRegistration(row: TableRow): Promise<void> {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null) return;

    const confirmed = await popup.confirm({
        title: 'Hapus pendaftaran peserta?',
        message: `Pendaftaran ${String(row.athlete ?? '')} akan dihapus dari event ini. Tagihan atau riwayat terkait dapat memblokir tindakan ini.`,
        tone: 'danger',
        confirmLabel: 'Hapus pendaftaran',
    });
    if (!confirmed) return;

    router.delete(championshipRegistrationDestroy.url(registrationId), { preserveScroll: true });
}

async function removeCoach(row: TableRow): Promise<void> {
    const registrationId = routeId(row.registration_id ?? row.id);
    if (registrationId === null) return;

    const confirmed = await popup.confirm({
        title: 'Hapus penugasan pelatih?',
        message: `Penugasan ${String(row.coach ?? '')} pada event ini akan dihapus.`,
        tone: 'danger',
        confirmLabel: 'Hapus penugasan',
    });
    if (!confirmed) return;

    router.delete(championshipCoachDestroy.url(registrationId), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Kejuaraan - ${props.event.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Detail kejuaraan"
                :title="props.event.name"
                description="Lihat peserta, pelatih pendamping, hasil pertandingan, dan data kejuaraan."
            >
                <template #actions>
                    <Button as-child type="button" variant="outline">
                        <Link :href="championshipsIndex.url()">Kembali</Link>
                    </Button>
                    <Button v-if="props.canManageCoaches" type="button" @click="showCoachForm = true">
                        Tambah pelatih
                    </Button>
                    <Button v-if="props.canManageCoaches" as-child type="button" variant="outline">
                        <a :href="championshipExport.url(props.event.id)">Ekspor CSV</a>
                    </Button>
                    <Button v-if="props.isAdmin" as-child type="button" variant="outline" class="gap-2">
                        <a :href="`/championships/${props.event.id}/photos`">
                            <Download class="size-4" />
                            Unduh foto 3×4
                        </a>
                    </Button>
                </template>

                <div class="grid gap-3 rounded-xl border bg-card p-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <p class="text-xs text-muted-foreground">Tanggal</p>
                        <p class="mt-1 font-semibold">{{ props.event.date }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Batas pendaftaran</p>
                        <p class="mt-1 font-semibold">{{ props.event.registration_deadline }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ props.event.registration_open ? 'Masih dapat diubah' : 'Sudah ditutup' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Lokasi</p>
                        <p class="mt-1 break-words font-semibold">{{ props.event.location }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Biaya masuk</p>
                        <p class="mt-1 font-semibold">Rp {{ props.event.entry_fee.toLocaleString('id-ID') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Status</p>
                        <p class="mt-1 font-semibold">{{ props.event.status }}</p>
                    </div>
                    <div v-if="props.event.gmaps_url" class="sm:col-span-2 lg:col-span-5">
                        <a
                            :href="props.event.gmaps_url"
                            target="_blank"
                            rel="noreferrer"
                            class="font-medium text-primary underline underline-offset-4"
                        >
                            Buka lokasi di peta
                        </a>
                    </div>
                </div>
            </PageSection>

            <DataTable
                title="Peserta terdaftar"
                description="Atlet dapat melihat peserta lain. Hanya entri milik sendiri yang dapat diubah sebelum batas pendaftaran; admin dan pelatih tetap memiliki tindakan pengelolaan."
                :columns="athleteColumns"
                :rows="props.athleteRows"
                action-label="Tindakan"
                empty-text="Belum ada atlet yang terdaftar."
                searchable
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
                            <Link :href="athleteProfileUrl(row) ?? '#'">Profil atlet</Link>
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
                title="Pelatih pendamping"
                description="Pelatih yang ditugaskan untuk mendampingi kejuaraan ini."
                :columns="coachColumns"
                :rows="props.coachRows"
                action-label="Tindakan"
                empty-text="Belum ada pelatih yang ditugaskan."
                searchable
            >
                <template v-if="props.isAdmin" #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="destructive" @click="removeCoach(row)">Hapus</Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal
            :open="showCoachForm && props.canManageCoaches"
            max-width-class="max-w-xl"
            @close="showCoachForm = false"
        >
            <PageSection title="Tambah pelatih" description="Tugaskan pelatih aktif untuk mendampingi kejuaraan ini.">
                <form class="grid min-w-0 gap-4" @submit.prevent="addCoach">
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
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="showCoachForm = false">Batal</Button>
                        <Button type="submit" :disabled="coachForm.processing">Tambahkan</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="showRegistrationEditForm"
            max-width-class="max-w-xl"
            @close="showRegistrationEditForm = false"
        >
            <PageSection
                :title="editingOwnRegistration ? 'Ubah pendaftaran saya' : 'Ubah entri peserta'"
                :description="
                    editingOwnRegistration
                        ? `Data ini dapat diperbarui sampai ${props.event.registration_deadline}.`
                        : 'Perbarui klasifikasi, kelas, divisi, kategori, dan kontingen peserta.'
                "
            >
                <form class="grid min-w-0 gap-4" @submit.prevent="saveRegistrationEdit">
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
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="showRegistrationEditForm = false">Batal</Button>
                        <Button type="submit" :disabled="editForm.processing">Simpan entri</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="showResultForm && props.canRecordResult"
            max-width-class="max-w-xl"
            @close="showResultForm = false"
        >
            <PageSection
                title="Catat hasil pertandingan"
                description="Hasil ini otomatis membuat atau memperbarui prestasi atlet."
            >
                <form class="grid min-w-0 gap-4" @submit.prevent="saveResult">
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
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="showResultForm = false">Batal</Button>
                        <Button type="submit" :disabled="resultForm.processing">Simpan hasil</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
