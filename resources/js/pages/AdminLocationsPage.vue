<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import LeafletLocationMap from '@/components/shared/LeafletLocationMap.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { locations as adminLocations } from '@/routes/admin';
import { destroy as branchDestroy, store as branchStore, update as branchUpdate } from '@/routes/admin/branches';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableFilter, TableRow } from '@/types/resource-table';
import type { LocationRecord } from '@/types/training';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        locations?: LocationRecord[];
    }>(),
    {
        title: 'Lokasi Latihan',
        subtitle: 'Master data dojang / lokasi latihan Rhino Fighter.',
        locations: () => [],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: props.title, href: adminLocations.url() },
];

const locationTableColumns: TableColumn[] = [
    { key: 'name', label: 'Lokasi' },
    { key: 'address', label: 'Alamat' },
    { key: 'coordinates', label: 'Koordinat' },
    { key: 'usage', label: 'Kelas / Atlet' },
    { key: 'radius', label: 'Radius' },
    { key: 'status', label: 'Status' },
];

const locationTableFilters: TableFilter[] = [
    {
        key: 'location',
        label: 'Lokasi',
        type: 'text',
        placeholder: 'Cari lokasi/alamat...',
        accessor: (row) => [row.name, row.location_label, row.address, row.area].filter(Boolean).join(' '),
    },
    { key: 'area', label: 'Kota / Provinsi', type: 'select', columnKey: 'area', placeholder: 'Semua area' },
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

const editingLocationId = ref<number | null>(null);
const showLocationForm = ref(false);

const form = useForm({
    name: '',
    location: '',
    address: '',
    city: '',
    province: '',
    latitude: '',
    longitude: '',
    attendance_radius_meters: 100,
    timezone: 'Asia/Jakarta',
    is_active: false,
});

const locationTableRows = computed<TableRow[]>(() =>
    props.locations.map((location) => ({
        id: String(location.id),
        location_id: location.id,
        name: location.name,
        location_label: location.location ?? '-',
        address: location.address ?? '-',
        area: [location.city, location.province].filter(Boolean).join(' ') || '-',
        coordinates:
            location.latitude !== null && location.latitude !== undefined && location.longitude !== null && location.longitude !== undefined
                ? `${location.latitude}, ${location.longitude}`
                : '-',
        usage: `${location.groups_count ?? 0} kelas · ${location.athletes_count ?? 0} atlet`,
        radius: `${location.attendance_radius_meters ?? 100}m`,
        timezone: location.timezone ?? 'Asia/Jakarta',
        status: {
            kind: 'badge',
            text: location.is_active ? 'AKTIF' : 'NONAKTIF',
            tone: location.is_active ? 'success' : 'neutral',
        },
    })),
);

const locationCanBeActive = computed(() => {
    return Boolean(
        form.name.trim() &&
            form.address.trim() &&
            form.city.trim() &&
            form.province.trim() &&
            isValidCoordinate(form.latitude) &&
            isValidCoordinate(form.longitude),
    );
});

const activationHint = computed(() => {
    if (locationCanBeActive.value) return 'Data lokasi lengkap. Lokasi bisa diaktifkan.';

    return 'Lengkapi nama, alamat, kota, provinsi, latitude, dan longitude sebelum lokasi bisa aktif.';
});

function isValidCoordinate(value: string | number | null | undefined): boolean {
    if (value === null || value === undefined || value === '') return false;

    return Number.isFinite(Number(value));
}

function enforceActivationRules() {
    if (form.is_active && !locationCanBeActive.value) {
        form.is_active = false;
    }
}

function resetForm() {
    editingLocationId.value = null;
    form.clearErrors();
    form.name = '';
    form.location = '';
    form.address = '';
    form.city = '';
    form.province = '';
    form.latitude = '';
    form.longitude = '';
    form.attendance_radius_meters = 100;
    form.timezone = 'Asia/Jakarta';
    form.is_active = false;
}

function openCreateLocation() {
    resetForm();
    showLocationForm.value = true;
}

function closeLocationForm() {
    showLocationForm.value = false;
    resetForm();
}

function locationFromRow(row: TableRow): LocationRecord | null {
    const id = Number(row.location_id ?? row.id);

    return props.locations.find((location) => Number(location.id) === id) ?? null;
}

function editLocation(location: LocationRecord) {
    editingLocationId.value = location.id;
    form.clearErrors();
    form.name = location.name;
    form.location = location.location ?? '';
    form.address = location.address ?? '';
    form.city = location.city ?? '';
    form.province = location.province ?? '';
    form.latitude = location.latitude === null || location.latitude === undefined ? '' : String(location.latitude);
    form.longitude = location.longitude === null || location.longitude === undefined ? '' : String(location.longitude);
    form.attendance_radius_meters = location.attendance_radius_meters ?? 100;
    form.timezone = location.timezone ?? 'Asia/Jakarta';
    form.is_active = location.is_active;
    enforceActivationRules();
    showLocationForm.value = true;
}

function editLocationFromRow(row: TableRow) {
    const location = locationFromRow(row);
    if (location) editLocation(location);
}

function setCoordinates(payload: { latitude: string; longitude: string }) {
    form.latitude = payload.latitude;
    form.longitude = payload.longitude;
}

function saveLocation() {
    enforceActivationRules();
    const options = { preserveScroll: true, onSuccess: closeLocationForm };
    if (editingLocationId.value) form.put(branchUpdate.url(editingLocationId.value), options);
    else form.post(branchStore.url(), options);
}

function deleteLocation(location: LocationRecord) {
    if (window.confirm(`Delete/deactivate lokasi ${location.name}?`)) {
        router.delete(branchDestroy.url(location.id), { preserveScroll: true });
    }
}

function deleteLocationFromRow(row: TableRow) {
    const location = locationFromRow(row);
    if (location) deleteLocation(location);
}

watch(
    () => [form.is_active, form.name, form.address, form.city, form.province, form.latitude, form.longitude],
    enforceActivationRules,
);
</script>

<template>
    <Head :title="props.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="props.title" :description="props.subtitle">
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" class="gap-2" @click="router.reload()">
                            <RefreshCcw class="size-4" />
                            Refresh
                        </Button>
                        <Button type="button" class="gap-2" @click="openCreateLocation">
                            <Plus class="size-4" />
                            Tambah Lokasi
                        </Button>
                    </div>
                </template>
            </PageSection>

            <DataTable
                title="Daftar Lokasi"
                description="Kelola dojang/lokasi latihan memakai tabel bersama agar filter, pagination, dan desain konsisten."
                :columns="locationTableColumns"
                :rows="locationTableRows"
                :filters="locationTableFilters"
                filterable
                searchable
                search-placeholder="Cari lokasi, alamat, kota, provinsi..."
                empty-text="Belum ada lokasi."
                action-label="Aksi"
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button type="button" size="sm" variant="outline" @click="editLocationFromRow(row)">
                            <Pencil class="size-4" />
                        </Button>
                        <Button type="button" size="sm" variant="destructive" @click="deleteLocationFromRow(row)">
                            <Trash2 class="size-4" />
                        </Button>
                    </ActionButtonsRow>
                </template>
            </DataTable>

            <FormModal :open="showLocationForm" max-width-class="max-w-4xl" @close="closeLocationForm">
                <form class="grid gap-4" @submit.prevent="saveLocation">
                    <h2 class="text-xl font-black">{{ editingLocationId ? 'Edit Lokasi' : 'Tambah Lokasi' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Klik peta untuk memilih koordinat, atau isi latitude dan longitude manual.
                    </p>

                    <LeafletLocationMap
                        :latitude="form.latitude"
                        :longitude="form.longitude"
                        :marker-label="form.name || 'Lokasi latihan'"
                        editable
                        @change="setCoordinates"
                    />

                    <div class="mt-2 grid gap-3">
                        <label class="grid gap-1 text-sm font-semibold">
                            Nama Lokasi *
                            <input
                                v-model="form.name"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Contoh: Central Dojang"
                            />
                            <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Label Lokasi
                            <input
                                v-model="form.location"
                                class="h-10 rounded-lg border bg-background px-3 text-sm"
                                placeholder="Contoh: Rhino Fighter"
                            />
                        </label>

                        <label class="grid gap-1 text-sm font-semibold">
                            Alamat *
                            <textarea
                                v-model="form.address"
                                class="min-h-20 rounded-lg border bg-background px-3 py-2 text-sm"
                                placeholder="Alamat lengkap"
                            ></textarea>
                            <span v-if="form.errors.address" class="text-xs text-destructive">{{
                                form.errors.address
                            }}</span>
                        </label>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">
                                Kota *
                                <input v-model="form.city" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                                <span v-if="form.errors.city" class="text-xs text-destructive">{{
                                    form.errors.city
                                }}</span>
                            </label>
                            <label class="grid gap-1 text-sm font-semibold">
                                Provinsi *
                                <input
                                    v-model="form.province"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                                />
                                <span v-if="form.errors.province" class="text-xs text-destructive">{{
                                    form.errors.province
                                }}</span>
                            </label>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">
                                Latitude *
                                <input
                                    v-model="form.latitude"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                                />
                            </label>
                            <label class="grid gap-1 text-sm font-semibold">
                                Longitude *
                                <input
                                    v-model="form.longitude"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                                />
                            </label>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">
                                Radius Absensi
                                <input
                                    v-model="form.attendance_radius_meters"
                                    type="number"
                                    min="10"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                                />
                            </label>
                            <label class="grid gap-1 text-sm font-semibold">
                                Timezone
                                <input
                                    v-model="form.timezone"
                                    class="h-10 rounded-lg border bg-background px-3 text-sm"
                                />
                            </label>
                        </div>

                        <label
                            class="grid gap-1 rounded-lg border bg-background px-3 py-2 text-sm font-semibold"
                            :class="!locationCanBeActive ? 'opacity-80' : ''"
                        >
                            <span class="flex h-7 items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    :disabled="!locationCanBeActive"
                                    class="disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                Aktif
                            </span>
                            <span
                                class="text-xs"
                                :class="locationCanBeActive ? 'text-green-600' : 'text-muted-foreground'"
                            >
                                {{ activationHint }}
                            </span>
                        </label>

                        <div class="flex gap-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save Lokasi' }}
                            </Button>
                            <Button type="button" variant="outline" @click="closeLocationForm">Batal</Button>
                        </div>
                    </div>
                </form>
            </FormModal>
        </div>
    </AppLayout>
</template>
