<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormModal from '@/components/shared/FormModal.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type TrainingGroupRecord = {
    id: number;
    name: string;
    description?: string | null;
    is_active: boolean;
    classes_count: number;
    athletes_count: number;
};

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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: props.title, href: '/admin/groups' },
];

const search = ref('');
const showForm = ref(false);
const editingId = ref<number | null>(null);

const form = useForm({
    name: '',
    description: '',
    is_active: true,
});

const filteredGroups = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.groups;

    return props.groups.filter((group) =>
        [group.name, group.description, group.is_active ? 'aktif' : 'nonaktif']
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(keyword),
    );
});

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

function openEdit(group: TrainingGroupRecord) {
    editingId.value = group.id;
    form.clearErrors();
    form.name = group.name;
    form.description = group.description ?? '';
    form.is_active = group.is_active;
    showForm.value = true;
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

function deleteGroup(group: TrainingGroupRecord) {
    if (window.confirm(`Hapus/nonaktifkan grup ${group.name}?`)) {
        router.delete(`/admin/training-groups/${group.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-black tracking-wide text-red-500 uppercase">Group Management</p>
                        <h1 class="text-3xl font-black">{{ props.title }}</h1>
                        <p class="mt-1 max-w-3xl text-sm text-muted-foreground">{{ props.subtitle }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-bold" @click="router.reload()">
                            <RefreshCcw class="mr-2 size-4" /> Refresh
                        </button>
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground" @click="openCreate">
                            <Plus class="mr-2 size-4" /> Tambah Grup
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-black">Daftar Grup</h2>
                        <p class="text-sm text-muted-foreground">Kelas hanya dapat diikuti atlet dari grup yang sama.</p>
                    </div>
                    <input v-model="search" class="h-10 rounded-lg border bg-background px-3 text-sm md:w-80" placeholder="Cari grup..." />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Grup</th>
                                <th class="px-3 py-3 font-black">Deskripsi</th>
                                <th class="px-3 py-3 font-black">Kelas</th>
                                <th class="px-3 py-3 font-black">Atlet</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredGroups.length === 0">
                                <td colspan="6" class="h-32 px-3 text-center text-muted-foreground">Belum ada grup.</td>
                            </tr>
                            <tr v-for="group in filteredGroups" :key="group.id" class="border-b hover:bg-muted/40">
                                <td class="px-3 py-4 font-black">{{ group.name }}</td>
                                <td class="max-w-[360px] px-3 py-4 text-muted-foreground">{{ group.description || '-' }}</td>
                                <td class="px-3 py-4">{{ group.classes_count }}</td>
                                <td class="px-3 py-4">{{ group.athletes_count }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-black" :class="group.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                                        {{ group.is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded border px-2 py-1" @click="openEdit(group)"><Pencil class="size-4" /></button>
                                        <button type="button" class="rounded border px-2 py-1 text-red-600" @click="deleteGroup(group)"><Trash2 class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <FormModal :open="showForm" max-width-class="max-w-xl" @close="closeForm">
                <form class="grid gap-4" @submit.prevent="saveGroup">
                    <div>
                        <h2 class="text-xl font-black">{{ editingId ? 'Edit Grup' : 'Tambah Grup' }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Grup ini akan dipakai sebagai kategori wajib saat membuat kelas.</p>
                    </div>

                    <label class="grid gap-1 text-sm font-semibold">
                        Nama Grup *
                        <input v-model="form.name" class="h-10 rounded-lg border bg-background px-3 text-sm" placeholder="Contoh: Junior, Senior, Prestasi" />
                        <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
                    </label>

                    <label class="grid gap-1 text-sm font-semibold">
                        Deskripsi
                        <textarea v-model="form.description" class="min-h-24 rounded-lg border bg-background px-3 py-2 text-sm" placeholder="Keterangan grup"></textarea>
                        <span v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</span>
                    </label>

                    <label class="flex items-center gap-2 rounded-lg border bg-background px-3 py-2 text-sm font-semibold">
                        <input v-model="form.is_active" type="checkbox" /> Aktif
                    </label>

                    <div class="flex gap-2">
                        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Grup' }}
                        </button>
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm font-bold" @click="closeForm">Batal</button>
                    </div>
                </form>
            </FormModal>
        </div>
    </AppLayout>
</template>
