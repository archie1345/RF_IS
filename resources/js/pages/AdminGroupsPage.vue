<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCcw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableFilter, TableRow } from '@/types/resource-table';
import type { TrainingGroupRecord } from './AdminGroupsPage.types';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        groups?: TrainingGroupRecord[];
    }>(),
    {
        title: 'Manajemen Grup',
        subtitle: 'Kelola kategori grup atlet untuk membatasi kelas dan presensi.',
        groups: () => [],
    },
);
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: props.title, href: '/admin/groups' },
];

const groupTableColumns: TableColumn[] = [
    { key: 'name', label: 'Grup' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'classes_count', label: 'Kelas', align: 'right' },
    { key: 'athletes_count', label: 'Atlet', align: 'right' },
    { key: 'status', label: 'Status' },
];

const groupTableFilters: TableFilter[] = [
    {
        key: 'status',
        label: 'Status',
        type: 'select',
        columnKey: 'status',
        placeholder: 'Semua status',
        options: [
            { value: 'AKTIF', label: 'Aktif' },
            { value: 'NONAKTIF', label: 'Nonaktif' },
        ],
    },
];

const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    description: '',
    is_active: true,
});

const groupTableRows = computed<TableRow[]>(() =>
    props.groups.map((group) => ({
        id: String(group.id),
        group_id: group.id,
        name: group.name,
        description: group.description || '-',
        classes_count: group.classes_count,
        athletes_count: group.athletes_count,
        status: {
            kind: 'badge',
            text: group.is_active ? 'AKTIF' : 'NONAKTIF',
            tone: group.is_active ? 'success' : 'neutral',
        },
    })),
);

function resetForm() {
    editingId.value = null;
    form.clearErrors();
    form.name = '';
    form.description = '';
    form.is_active = true;
}

function openCreate() {
    resetForm();
    showForm.value = true;
}

function groupFromRow(row: TableRow): TrainingGroupRecord | null {
    const id = Number(row.group_id ?? row.id);

    return props.groups.find((group) => Number(group.id) === id) ?? null;
}

function openEdit(group: TrainingGroupRecord) {
    editingId.value = group.id;
    form.clearErrors();
    form.name = group.name;
    form.description = group.description ?? '';
    form.is_active = group.is_active;
    showForm.value = true;
}

function openEditFromRow(row: TableRow) {
    const group = groupFromRow(row);
    if (group) openEdit(group);
}

function closeForm() {
    showForm.value = false;
    resetForm();
}

function saveGroup() {
    const options = { preserveScroll: true, onSuccess: closeForm };

    if (editingId.value) {
        form.put(`/admin/training-groups/${editingId.value}`, options);
        return;
    }

    form.post('/admin/training-groups', options);
}

async function deleteGroup(group: TrainingGroupRecord): Promise<void> {
    const confirmed = await popup.confirm({
        title: 'Hapus atau nonaktifkan grup?',
        message: `Grup “${group.name}” akan dihapus bila belum digunakan. Grup yang sudah dipakai akan dinonaktifkan agar riwayat kelas dan atlet tetap aman.`,
        tone: 'danger',
        confirmLabel: 'Lanjutkan',
    });
    if (!confirmed) return;

    router.delete(`/admin/training-groups/${group.id}`, { preserveScroll: true });
}

function deleteGroupFromRow(row: TableRow) {
    const group = groupFromRow(row);
    if (group) void deleteGroup(group);
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle" eyebrow="Group Management">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" class="gap-2" @click="router.reload()">
                            <RefreshCcw class="size-4" />
                            Refresh
                        </Button>
                        <Button type="button" class="gap-2" @click="openCreate">
                            <Plus class="size-4" />
                            Tambah Grup
                        </Button>
                    </div>
                </template>
            </PageSection>

            <DataTable
                title="Daftar Grup"
                description="Kelas hanya dapat diikuti atlet dari grup yang sama. Filter dan pagination memakai tabel bersama."
                :columns="groupTableColumns"
                :rows="groupTableRows"
                :filters="groupTableFilters"
                filterable
                searchable
                search-placeholder="Cari grup, deskripsi, status..."
                empty-text="Belum ada grup."
                action-label="Aksi"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" @click="openEditFromRow(row)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button type="button" size="sm" variant="destructive" @click="deleteGroupFromRow(row)">
                            <Trash2 class="size-4" />
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>

            <FormModal :open="showForm" max-width-class="max-w-xl" @close="closeForm">
                <form class="grid gap-4" @submit.prevent="saveGroup">
                    <div>
                        <h2 class="text-xl font-black">{{ editingId ? 'Edit Grup' : 'Tambah Grup' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Grup ini akan dipakai sebagai kategori wajib saat membuat kelas.
                        </p>
                    </div>

                    <label class="grid gap-1 text-sm font-semibold">
                        Nama Grup *
                        <input
                            v-model="form.name"
                            class="h-10 rounded-lg border bg-background px-3 text-sm"
                            placeholder="Contoh: Junior, Senior, Prestasi"
                        />
                        <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Deskripsi
                        <textarea
                            v-model="form.description"
                            class="min-h-24 rounded-lg border bg-background px-3 py-2 text-sm"
                            placeholder="Keterangan grup"
                        ></textarea>
                        <span v-if="form.errors.description" class="text-xs text-destructive">{{
                            form.errors.description
                        }}</span>
                    </label>

                    <label
                        class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm font-semibold"
                    >
                        <input v-model="form.is_active" type="checkbox" />
                        Aktif
                    </label>

                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Grup' }}
                        </Button>
                        <Button type="button" variant="outline" @click="closeForm">Batal</Button>
                    </div>
                </form>
            </FormModal>
        </div>
    </AppLayout>
</template>
