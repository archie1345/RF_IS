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
import AppLayout from '@/layouts/AppLayout.vue';
import { routeId } from '@/lib/routeIds';
import { dashboard } from '@/routes';
import {
    destroy as announcementDestroy,
    index as announcementsIndex,
    store as announcementsStore,
    update as announcementUpdate,
} from '@/routes/announcements';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{ isAdmin: boolean; rows: TableRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Pengumuman', href: announcementsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'title', label: 'Judul' },
    { key: 'message', label: 'Isi' },
    { key: 'target', label: 'Penerima' },
    { key: 'status', label: 'Status' },
    { key: 'author', label: 'Pembuat' },
    { key: 'published', label: 'Terbit' },
];

const targetRoleOptions = [
    { value: 'ALL', label: 'Semua pengguna' },
    { value: 'ADMIN', label: 'Admin saja' },
    { value: 'COACH', label: 'Pelatih saja' },
    { value: 'PARENT', label: 'Orang tua saja' },
    { value: 'ATHLETE', label: 'Atlet saja' },
];

const activeOptions = [
    { value: '1', label: 'Aktif dan terlihat' },
    { value: '0', label: 'Disembunyikan' },
];

const showForm = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({
    title: '',
    message: '',
    target_role: 'ALL',
    publish_at: '',
    expire_at: '',
    is_active: '1',
});

function resetForm(): void {
    form.reset();
    form.clearErrors();
    form.target_role = 'ALL';
    form.is_active = '1';
    editingId.value = null;
}

function openCreate(): void {
    resetForm();
    showForm.value = true;
}

function openEdit(row: TableRow): void {
    const id = routeId(row.announcement_id ?? row.id);
    if (id === null) return;

    form.clearErrors();
    editingId.value = id;
    form.title = String(row.title ?? '');
    form.message = String(row.message ?? '');
    form.target_role = String(row.target_role ?? 'ALL');
    form.publish_at = String(row.publish_at_value ?? '');
    form.expire_at = String(row.expire_at_value ?? '');
    form.is_active = row.is_active === false ? '0' : '1';
    showForm.value = true;
}

function closeForm(): void {
    showForm.value = false;
    resetForm();
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        is_active: data.is_active === '1',
    }));

    const options = {
        preserveScroll: true,
        onSuccess: closeForm,
    };

    if (editingId.value !== null) {
        form.put(announcementUpdate.url(editingId.value), options);
        return;
    }

    form.post(announcementsStore.url(), options);
}

function removeAnnouncement(row: TableRow): void {
    const id = routeId(row.announcement_id ?? row.id);
    if (id === null || !window.confirm(`Hapus pengumuman “${String(row.title ?? '')}”?`)) return;

    router.delete(announcementDestroy.url(id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengumuman" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                title="Pengumuman"
                description="Informasi penting klub, perubahan jadwal, dan pengingat untuk setiap kelompok pengguna."
            >
                <template v-if="props.isAdmin" #actions>
                    <Button type="button" @click="openCreate">Buat pengumuman</Button>
                </template>
            </PageSection>

            <DataTable
                title="Daftar pengumuman"
                :description="props.isAdmin ? 'Admin dapat membuat, mengubah, menyembunyikan, dan menghapus pengumuman.' : 'Pengumuman yang berlaku untuk akun ini.'"
                :columns="columns"
                :rows="props.rows"
                action-label="Tindakan"
                empty-text="Belum ada pengumuman."
                searchable
            >
                <template v-if="props.isAdmin" #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" @click="openEdit(row)">Ubah</Button>
                        <Button type="button" size="sm" variant="destructive" @click="removeAnnouncement(row)">
                            Hapus
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal :open="showForm && props.isAdmin" max-width-class="max-w-2xl" @close="closeForm">
            <PageSection
                :title="editingId === null ? 'Buat pengumuman' : 'Ubah pengumuman'"
                description="Pilih penerima dengan jelas. Jadwal terbit dan tanggal berakhir boleh dikosongkan."
            >
                <form class="grid min-w-0 gap-4" @submit.prevent="submit">
                    <FormInputField
                        id="announcement-title"
                        v-model="form.title"
                        label="Judul"
                        placeholder="Contoh: Perubahan jadwal latihan"
                        required
                        :error="form.errors.title"
                    />
                    <FormSelectField
                        id="announcement-target"
                        v-model="form.target_role"
                        label="Penerima"
                        :options="targetRoleOptions"
                        required
                        :error="form.errors.target_role"
                    />
                    <div class="grid min-w-0 gap-2">
                        <label for="announcement-message" class="text-sm font-medium">Isi pengumuman</label>
                        <textarea
                            id="announcement-message"
                            v-model="form.message"
                            rows="6"
                            required
                            placeholder="Tuliskan informasi dengan singkat dan jelas."
                            class="min-w-0 rounded-xl border border-input bg-background px-4 py-3 text-sm leading-6 shadow-sm focus:ring-2 focus:ring-ring/25 focus:outline-none"
                        />
                        <p v-if="form.errors.message" class="text-sm text-destructive">{{ form.errors.message }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInputField
                            id="announcement-publish"
                            v-model="form.publish_at"
                            label="Terbit pada"
                            type="datetime-local"
                            help="Kosongkan untuk terbit sekarang."
                            :error="form.errors.publish_at"
                        />
                        <FormInputField
                            id="announcement-expire"
                            v-model="form.expire_at"
                            label="Berakhir pada"
                            type="datetime-local"
                            help="Kosongkan agar tetap terlihat."
                            :error="form.errors.expire_at"
                        />
                    </div>
                    <FormSelectField
                        id="announcement-active"
                        v-model="form.is_active"
                        label="Visibilitas"
                        :options="activeOptions"
                        :error="form.errors.is_active"
                    />
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                        <Button type="button" variant="outline" @click="closeForm">Batal</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan pengumuman' }}
                        </Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
