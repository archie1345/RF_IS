<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
    destroy as achievementsDestroy,
    index as achievementsIndex,
    store as achievementsStore,
    update as achievementsUpdate,
} from '@/routes/achievements';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    achievements: TableRow[];
    canCreate: boolean;
    pageTitle: string;
    pageDescription: string;
}>();
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Prestasi & Sertifikat', href: achievementsIndex.url() },
];

const achievementColumns: TableColumn[] = [
    { key: 'subject', label: 'Nama' },
    { key: 'championship_name', label: 'Kejuaraan' },
    { key: 'medal', label: 'Medali' },
    { key: 'location', label: 'Lokasi' },
    { key: 'event_date', label: 'Tanggal' },
    { key: 'class_name', label: 'Kelas' },
    { key: 'division', label: 'Divisi' },
    { key: 'category', label: 'Kategori' },
    { key: 'file_name', label: 'Berkas' },
];

const medalOptions = [
    { value: 'GOLD', label: 'Emas' },
    { value: 'SILVER', label: 'Perak' },
    { value: 'BRONZE', label: 'Perunggu' },
    { value: 'NONE', label: 'Tanpa medali' },
];

const showAchievementModal = ref(false);
const editingId = ref<number | null>(null);
const achievementForm = useForm({
    championship_name: '',
    medal: 'NONE',
    location: '',
    event_date: '',
    class_name: '',
    division: '',
    category: '',
    notes: '',
    file: null as File | null,
});

function resetForm(): void {
    achievementForm.reset();
    achievementForm.clearErrors();
    achievementForm.transform((data) => data);
    achievementForm.medal = 'NONE';
    editingId.value = null;
}

function openCreate(): void {
    resetForm();
    showAchievementModal.value = true;
}

function openEdit(row: TableRow): void {
    if (row.can_manage !== true) return;
    const id = routeId(row.achievement_id ?? row.id);
    if (id === null) return;

    achievementForm.clearErrors();
    editingId.value = id;
    achievementForm.championship_name = String(row.championship_name ?? '');
    achievementForm.medal = String(row.medal ?? 'NONE');
    achievementForm.location = row.location === '-' ? '' : String(row.location ?? '');
    achievementForm.event_date = row.event_date === '-' ? '' : String(row.event_date ?? '');
    achievementForm.class_name = row.class_name === '-' ? '' : String(row.class_name ?? '');
    achievementForm.division = row.division === '-' ? '' : String(row.division ?? '');
    achievementForm.category = row.category === '-' ? '' : String(row.category ?? '');
    achievementForm.notes = String(row.notes ?? '');
    achievementForm.file = null;
    showAchievementModal.value = true;
}

function closeForm(): void {
    showAchievementModal.value = false;
    resetForm();
}

function onAchievementFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    achievementForm.file = target.files?.[0] ?? null;
}

function saveAchievement(): void {
    if (!props.canCreate) return;

    if (editingId.value !== null) {
        achievementForm
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(achievementsUpdate.url(editingId.value), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: closeForm,
                onFinish: () => achievementForm.transform((data) => data),
            });
        return;
    }

    achievementForm.post(achievementsStore.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeForm,
    });
}

async function removeAchievement(row: TableRow): Promise<void> {
    if (row.can_manage !== true) return;
    const id = routeId(row.achievement_id ?? row.id);
    if (id === null) return;

    const confirmed = await popup.confirm({
        title: 'Hapus prestasi?',
        message: `Prestasi “${String(row.championship_name ?? '')}” akan dihapus beserta lampiran manualnya.`,
        tone: 'danger',
        confirmLabel: 'Hapus prestasi',
    });
    if (!confirmed) return;

    router.delete(achievementsDestroy.url(id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="props.pageTitle" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection :title="props.pageTitle" :description="props.pageDescription">
                <template v-if="props.canCreate" #actions>
                    <Button type="button" @click="openCreate">Tambah prestasi</Button>
                </template>
            </PageSection>

            <DataTable
                :title="props.pageTitle"
                :description="props.pageDescription"
                :columns="achievementColumns"
                :rows="props.achievements"
                action-label="Tindakan"
                empty-text="Belum ada catatan prestasi untuk konteks akun ini."
                searchable
                search-placeholder="Cari nama, kejuaraan, medali, kelas, atau kategori..."
            >
                <template #cell="{ row, column, value }">
                    <a
                        v-if="column.key === 'file_name' && row.file_url"
                        :href="String(row.file_url)"
                        target="_blank"
                        class="text-sm font-medium underline underline-offset-4"
                    >
                        {{ value }}
                    </a>
                    <span v-else-if="column.key === 'championship_name' && row.is_auto_recorded">
                        {{ value }}
                        <span class="ml-1 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                            >Otomatis</span
                        >
                    </span>
                    <span v-else>{{ value ?? '-' }}</span>
                </template>
                <template #row-actions="{ row }">
                    <ActionButtonsRow v-if="row.can_manage === true">
                        <Button type="button" size="sm" variant="outline" @click="openEdit(row)">Ubah</Button>
                        <Button type="button" size="sm" variant="destructive" @click="removeAchievement(row)">
                            Hapus
                        </Button>
                    </ActionButtonsRow>
                    <span v-else class="text-xs text-muted-foreground">
                        {{ row.is_auto_recorded ? 'Dikelola dari hasil kejuaraan' : 'Hanya dapat dilihat' }}
                    </span>
                </template>
            </DataTable>
        </div>

        <FormModal :open="showAchievementModal && props.canCreate" max-width-class="max-w-2xl" @close="closeForm">
            <PageSection
                :title="editingId === null ? 'Tambah prestasi' : 'Ubah prestasi'"
                description="Catat prestasi manual dan lampirkan sertifikat, lembar hasil, foto medali, atau PDF bila tersedia."
            >
                <form class="grid min-w-0 gap-4 sm:grid-cols-2" @submit.prevent="saveAchievement">
                    <FormInputField
                        id="achievement-name"
                        v-model="achievementForm.championship_name"
                        label="Nama kejuaraan atau prestasi"
                        required
                        :error="achievementForm.errors.championship_name"
                    />
                    <FormSelectField
                        id="achievement-medal"
                        v-model="achievementForm.medal"
                        label="Medali"
                        :options="medalOptions"
                        :error="achievementForm.errors.medal"
                    />
                    <FormInputField
                        id="achievement-location"
                        v-model="achievementForm.location"
                        label="Lokasi"
                        :error="achievementForm.errors.location"
                    />
                    <FormInputField
                        id="achievement-date"
                        v-model="achievementForm.event_date"
                        type="date"
                        label="Tanggal"
                        :error="achievementForm.errors.event_date"
                    />
                    <FormInputField
                        id="achievement-class"
                        v-model="achievementForm.class_name"
                        label="Kelas"
                        :error="achievementForm.errors.class_name"
                    />
                    <FormInputField
                        id="achievement-division"
                        v-model="achievementForm.division"
                        label="Divisi"
                        :error="achievementForm.errors.division"
                    />
                    <FormInputField
                        id="achievement-category"
                        v-model="achievementForm.category"
                        label="Kategori"
                        :error="achievementForm.errors.category"
                    />
                    <FormInputField
                        id="achievement-notes"
                        v-model="achievementForm.notes"
                        label="Catatan"
                        :error="achievementForm.errors.notes"
                    />
                    <div class="grid min-w-0 gap-2 sm:col-span-2">
                        <label for="achievement-file" class="text-sm font-medium">Lampiran opsional</label>
                        <input
                            id="achievement-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            class="min-h-11 max-w-full min-w-0 rounded-lg border border-input px-3 py-2 text-sm file:max-w-full"
                            @change="onAchievementFileChange"
                        />
                        <p class="text-xs leading-5 text-muted-foreground">
                            Berkas baru akan menggantikan lampiran lama ketika data diperbarui.
                        </p>
                        <p v-if="achievementForm.errors.file" class="text-sm text-destructive">
                            {{ achievementForm.errors.file }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:col-span-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="closeForm">Batal</Button>
                        <Button type="submit" :disabled="achievementForm.processing">Simpan prestasi</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
