<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Pencil, RefreshCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Option = { value: number | string; label: string };
type ClassRecord = {
    id: number;
    name: string;
    class_type: string;
    branch_id?: number | null;
    branch: string;
    coach_id?: string | null;
    coach: string;
    day_of_week?: number | null;
    day_label: string;
    start_time: string;
    end_time: string;
    min_belt?: string | null;
    description?: string | null;
    athletes_count: number;
    is_active: boolean;
    weekly_schedule_id?: number | null;
    weekly_schedule_status: string;
};

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        classes?: ClassRecord[];
        branchOptions?: Option[];
        coachOptions?: Option[];
        beltOptions?: Option[];
    }>(),
    {
        title: 'Kelas Latihan',
        subtitle: 'Master data kelas. Jadwal mingguan otomatis sinkron dari data kelas.',
        classes: () => [],
        branchOptions: () => [],
        coachOptions: () => [],
        beltOptions: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: '/admin/classes' },
];

const dayOptions = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
    { value: 7, label: 'Minggu' },
];

const editingClassId = ref<number | null>(null);
const search = ref('');

const form = useForm({
    name: '',
    class_type: 'General',
    branch_id: '',
    coach_id: '',
    day_of_week: 1,
    start_time: '16:00',
    end_time: '18:00',
    min_belt: '',
    description: '',
    is_active: true,
});

const filteredClasses = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.classes;

    return props.classes.filter((item) =>
        [item.name, item.class_type, item.branch, item.coach, item.day_label, item.min_belt]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(keyword),
    );
});

function resetForm() {
    editingClassId.value = null;
    form.clearErrors();
    form.name = '';
    form.class_type = 'General';
    form.branch_id = '';
    form.coach_id = '';
    form.day_of_week = 1;
    form.start_time = '16:00';
    form.end_time = '18:00';
    form.min_belt = props.beltOptions[0]?.value ? String(props.beltOptions[0].value) : '';
    form.description = '';
    form.is_active = true;
}

function editClass(item: ClassRecord) {
    editingClassId.value = item.id;
    form.clearErrors();
    form.name = item.name;
    form.class_type = item.class_type;
    form.branch_id = item.branch_id ? String(item.branch_id) : '';
    form.coach_id = item.coach_id ?? '';
    form.day_of_week = item.day_of_week ?? 1;
    form.start_time = item.start_time || '16:00';
    form.end_time = item.end_time || '18:00';
    form.min_belt = item.min_belt ?? '';
    form.description = item.description ?? '';
    form.is_active = item.is_active;
}

function saveClass() {
    const options = { preserveScroll: true, onSuccess: resetForm };
    if (editingClassId.value) form.put(`/admin/groups/${editingClassId.value}`, options);
    else form.post('/admin/groups', options);
}

function deleteClass(item: ClassRecord) {
    if (window.confirm(`Delete/deactivate kelas ${item.name}?`)) {
        router.delete(`/admin/groups/${item.id}`, { preserveScroll: true });
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
                        <p class="text-xs font-black tracking-wide text-brand-coral uppercase">Master Data</p>
                        <h1 class="text-3xl font-black">{{ props.title }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ props.subtitle }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg border px-4 text-sm font-bold"
                        @click="router.reload()"
                    >
                        <RefreshCcw class="mr-2 size-4" /> Refresh
                    </button>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[420px_1fr]">
                <form class="rounded-2xl border bg-card p-5 shadow-sm" @submit.prevent="saveClass">
                    <h2 class="text-xl font-black">{{ editingClassId ? 'Edit Kelas' : 'Tambah Kelas' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Data ini otomatis membuat / memperbarui jadwal mingguan terkait kelas.
                    </p>

                    <div class="mt-5 grid gap-3">
                        <label class="grid gap-1 text-sm font-semibold"
                            >Nama Kelas *<input
                                v-model="form.name"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Contoh: Junior Sparring"
                            /><span v-if="form.errors.name" class="text-xs text-destructive">{{
                                form.errors.name
                            }}</span></label
                        >
                        <label class="grid gap-1 text-sm font-semibold"
                            >Tipe Kelas *<input
                                v-model="form.class_type"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="General / Prestasi / Private"
                            /><span v-if="form.errors.class_type" class="text-xs text-destructive">{{
                                form.errors.class_type
                            }}</span></label
                        >
                        <label class="grid gap-1 text-sm font-semibold"
                            >Lokasi<select
                                v-model="form.branch_id"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option value="">Pilih lokasi</option>
                                <option
                                    v-for="option in props.branchOptions"
                                    :key="String(option.value)"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-semibold"
                            >Coach<select
                                v-model="form.coach_id"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option value="">Pilih coach</option>
                                <option
                                    v-for="option in props.coachOptions"
                                    :key="String(option.value)"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-semibold"
                            >Hari<select
                                v-model="form.day_of_week"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option v-for="day in dayOptions" :key="day.value" :value="day.value">
                                    {{ day.label }}
                                </option>
                            </select></label
                        >
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold"
                                >Mulai<input
                                    v-model="form.start_time"
                                    type="time"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                            /></label>
                            <label class="grid gap-1 text-sm font-semibold"
                                >Selesai<input
                                    v-model="form.end_time"
                                    type="time"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                            /></label>
                        </div>
                        <label class="grid gap-1 text-sm font-semibold"
                            >Minimal Sabuk<select
                                v-model="form.min_belt"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                            >
                                <option value="">Tanpa minimal</option>
                                <option
                                    v-for="option in props.beltOptions"
                                    :key="String(option.value)"
                                    :value="String(option.value)"
                                >
                                    {{ option.label }}
                                </option>
                            </select></label
                        >
                        <label class="grid gap-1 text-sm font-semibold"
                            >Deskripsi<textarea
                                v-model="form.description"
                                class="min-h-20 rounded-lg border bg-background px-3 py-2 text-sm"
                                placeholder="Deskripsi kelas"
                            ></textarea>
                        </label>
                        <label
                            class="flex h-10 items-center gap-2 rounded-lg border bg-background px-3 text-sm font-semibold"
                            ><input v-model="form.is_active" type="checkbox" /> Aktif</label
                        >
                        <div class="flex gap-2">
                            <button
                                class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Saving...' : 'Save Kelas' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border px-4 py-2 text-sm font-bold"
                                @click="resetForm"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </form>

                <section class="rounded-2xl border bg-card p-5 shadow-sm">
                    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <h2 class="text-xl font-black">Daftar Kelas</h2>
                        <input
                            v-model="search"
                            class="h-10 rounded-lg border bg-background px-3 text-sm md:w-72"
                            placeholder="Cari kelas..."
                        />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] text-sm">
                            <thead>
                                <tr class="border-b text-left">
                                    <th class="px-3 py-3 font-black">Kelas</th>
                                    <th class="px-3 py-3 font-black">Lokasi</th>
                                    <th class="px-3 py-3 font-black">Coach</th>
                                    <th class="px-3 py-3 font-black">Jadwal</th>
                                    <th class="px-3 py-3 font-black">Peserta</th>
                                    <th class="px-3 py-3 font-black">Schedule</th>
                                    <th class="px-3 py-3 font-black">Status</th>
                                    <th class="px-3 py-3 font-black">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredClasses.length === 0">
                                    <td colspan="8" class="h-32 px-3 text-center text-muted-foreground">
                                        Belum ada kelas.
                                    </td>
                                </tr>
                                <tr v-for="item in filteredClasses" :key="item.id" class="border-b hover:bg-muted/40">
                                    <td class="px-3 py-4">
                                        <p class="font-black">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ item.class_type }} · min {{ item.min_belt || '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-4">{{ item.branch }}</td>
                                    <td class="px-3 py-4">{{ item.coach }}</td>
                                    <td class="px-3 py-4">
                                        <CalendarDays class="mr-1 inline size-3" />{{ item.day_label }}
                                        <p class="text-xs text-muted-foreground">
                                            {{ item.start_time }} - {{ item.end_time }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-4">{{ item.athletes_count }} atlet</td>
                                    <td class="px-3 py-4">{{ item.weekly_schedule_status }}</td>
                                    <td class="px-3 py-4">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-black"
                                            :class="
                                                item.is_active
                                                    ? 'bg-brand-lime/20 text-brand-lime'
                                                    : 'bg-brand-slate/10 text-brand-slate'
                                            "
                                            >{{ item.is_active ? 'AKTIF' : 'NONAKTIF' }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                class="rounded border px-2 py-1"
                                                @click="editClass(item)"
                                            >
                                                <Pencil class="size-4" /></button
                                            ><button
                                                type="button"
                                                class="rounded border px-2 py-1 text-brand-coral"
                                                @click="deleteClass(item)"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </div>
    </AppLayout>
</template>
