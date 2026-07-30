<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Megaphone, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AnnouncementCard from '@/components/announcements/AnnouncementCard.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
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
import type { TableBadgeCell, TableRow } from '@/types/resource-table';

const props = defineProps<{ isAdmin: boolean; rows: TableRow[] }>();
const popup = useAppPopup();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Pengumuman', href: announcementsIndex.url() },
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

const searchQuery = ref('');
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

function statusText(row: TableRow): string {
    const status = row.status;

    if (status && typeof status === 'object' && 'text' in status) {
        return String((status as TableBadgeCell).text);
    }

    return String(status ?? '');
}

const filteredRows = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return props.rows;

    return props.rows.filter((row) =>
        [row.title, row.message, row.target, row.author, row.published, statusText(row)].some((value) =>
            String(value ?? '')
                .toLowerCase()
                .includes(query),
        ),
    );
});

const metrics = computed(() => [
    {
        label: 'Total pengumuman',
        value: String(props.rows.length),
        detail: props.isAdmin ? 'Semua pengumuman yang tersimpan' : 'Pengumuman yang tersedia untuk peran ini',
        tone: 'info' as const,
    },
    {
        label: 'Sedang diterbitkan',
        value: String(props.rows.filter((row) => statusText(row) === 'Diterbitkan').length),
        detail: 'Aktif dan berada dalam periode publikasi',
        tone: 'success' as const,
    },
    {
        label: 'Terjadwal',
        value: String(props.rows.filter((row) => statusText(row) === 'Terjadwal').length),
        detail: props.isAdmin ? 'Akan terbit otomatis sesuai jadwal' : 'Belum tersedia untuk akun ini',
        tone: 'neutral' as const,
    },
]);

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

async function removeAnnouncement(row: TableRow): Promise<void> {
    const id = routeId(row.announcement_id ?? row.id);
    if (id === null) return;

    const confirmed = await popup.confirm({
        title: 'Hapus pengumuman?',
        message: `Pengumuman “${String(row.title ?? '')}” akan dihapus dan tidak lagi terlihat di dashboard pengguna.`,
        tone: 'danger',
        confirmLabel: 'Hapus pengumuman',
    });
    if (!confirmed) return;

    router.delete(announcementDestroy.url(id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengumuman" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Pusat informasi"
                title="Pengumuman"
                description="Informasi penting klub, perubahan jadwal, pengingat pembayaran, event, dan pemberitahuan untuk setiap peran."
            >
                <template v-if="props.isAdmin" #actions>
                    <Button type="button" @click="openCreate">
                        <Megaphone class="mr-2 size-4" />Buat pengumuman
                    </Button>
                </template>

                <div class="grid gap-4 md:grid-cols-3">
                    <StatCard v-for="metric in metrics" :key="metric.label" v-bind="metric" />
                </div>
            </PageSection>

            <PageSection
                title="Daftar pengumuman"
                :description="
                    props.isAdmin
                        ? 'Kelola pengumuman aktif, terjadwal, disembunyikan, dan kedaluwarsa dalam tampilan kartu.'
                        : 'Pengumuman yang berlaku untuk peran aktif Anda.'
                "
            >
                <template #actions>
                    <label class="relative block w-full sm:w-72">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari pengumuman..."
                            class="h-10 w-full rounded-xl border bg-background pr-3 pl-10 text-sm transition outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"
                        />
                    </label>
                </template>

                <div v-if="filteredRows.length" class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    <AnnouncementCard
                        v-for="row in filteredRows"
                        :key="String(row.id)"
                        :announcement="row"
                        :editable="props.isAdmin"
                        @edit="openEdit"
                        @remove="removeAnnouncement"
                    />
                </div>

                <div v-else class="rounded-2xl border border-dashed p-10 text-center">
                    <Megaphone class="mx-auto size-9 text-muted-foreground" />
                    <p class="mt-3 font-semibold">Tidak ada pengumuman yang cocok</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            searchQuery
                                ? 'Ubah kata pencarian atau hapus filter.'
                                : 'Belum ada pengumuman yang tersedia.'
                        }}
                    </p>
                </div>
            </PageSection>
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
