<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { MapPin, Pencil, RefreshCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormModal from '@/components/shared/FormModal.vue';
import LeafletLocationMap from '@/components/shared/LeafletLocationMap.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
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
    { title: 'Dashboard', href: '/dashboard' },
    { title: props.title, href: '/admin/locations' },
];

const editingLocationId = ref<number | null>(null);
const showLocationForm = ref(false);
const search = ref('');
const googleLookupLoading = ref(false);
const googleLookupMessage = ref('');

const form = useForm({
    name: '',
    location: '',
    address: '',
    city: '',
    province: '',
    latitude: '',
    longitude: '',
    google_maps_url: '',
    attendance_radius_meters: 100,
    timezone: 'Asia/Jakarta',
    is_active: true,
});

const filteredLocations = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.locations;

    return props.locations.filter((location) =>
        [location.name, location.location, location.address, location.city, location.province]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(keyword),
    );
});

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
    form.google_maps_url = '';
    form.attendance_radius_meters = 100;
    form.timezone = 'Asia/Jakarta';
    form.is_active = true;
    googleLookupMessage.value = '';
}

function openCreateLocation() {
    resetForm();
    showLocationForm.value = true;
}

function closeLocationForm() {
    showLocationForm.value = false;
    resetForm();
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
    form.google_maps_url = location.google_maps_url ?? '';
    form.attendance_radius_meters = location.attendance_radius_meters ?? 100;
    form.timezone = location.timezone ?? 'Asia/Jakarta';
    form.is_active = location.is_active;
    googleLookupMessage.value = '';
    showLocationForm.value = true;
}

function setCoordinates(payload: { latitude: string; longitude: string }) {
    form.latitude = payload.latitude;
    form.longitude = payload.longitude;
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function parseCoordinatesFromGoogleMapsUrl(url: string): { latitude: string; longitude: string } | null {
    const decoded = decodeURIComponent(url);
    const patterns = [
        /@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
        /[?&](?:q|query)=(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/,
        /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
    ];

    for (const pattern of patterns) {
        const match = decoded.match(pattern);
        if (match) {
            return {
                latitude: Number(match[1]).toFixed(7),
                longitude: Number(match[2]).toFixed(7),
            };
        }
    }

    return null;
}

function applyGoogleMapsDetails(details: Record<string, string | null | undefined>) {
    if (details.google_maps_url) form.google_maps_url = details.google_maps_url;
    if (details.latitude) form.latitude = details.latitude;
    if (details.longitude) form.longitude = details.longitude;
    if (details.name && !form.name) form.name = details.name;
    if (details.address) form.address = details.address;
    if (details.city) form.city = details.city;
    if (details.province) form.province = details.province;
}

async function autofillFromGoogleMaps() {
    googleLookupMessage.value = '';
    const url = form.google_maps_url.trim();

    if (!url) {
        googleLookupMessage.value = 'Paste link Google Maps dulu.';
        return;
    }

    const parsed = parseCoordinatesFromGoogleMapsUrl(url);
    if (parsed) {
        setCoordinates(parsed);
        googleLookupMessage.value = 'Koordinat berhasil dibaca dari link.';
    }

    googleLookupLoading.value = true;

    try {
        const response = await fetch('/admin/branches/google-maps-lookup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ google_maps_url: url }),
        });

        if (!response.ok) {
            throw new Error('Lookup gagal.');
        }

        const details = await response.json();
        applyGoogleMapsDetails(details);
        googleLookupMessage.value = details.address
            ? 'Detail lokasi berhasil diisi dari Google Maps.'
            : 'Koordinat berhasil diisi. Tambahkan GOOGLE_MAPS_API_KEY untuk auto-fill alamat lengkap.';
    } catch {
        googleLookupMessage.value = parsed
            ? 'Koordinat sudah diisi dari link, tapi detail alamat belum tersedia.'
            : 'Link belum bisa dibaca otomatis. Isi koordinat manual atau klik peta.';
    } finally {
        googleLookupLoading.value = false;
    }
}

function saveLocation() {
    const options = { preserveScroll: true, onSuccess: closeLocationForm };
    if (editingLocationId.value) form.put(`/admin/branches/${editingLocationId.value}`, options);
    else form.post('/admin/branches', options);
}

function deleteLocation(location: LocationRecord) {
    if (window.confirm(`Delete/deactivate lokasi ${location.name}?`)) {
        router.delete(`/admin/branches/${location.id}`, { preserveScroll: true });
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

            <section class="rounded-2xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-black">Daftar Lokasi</h2>
                        <button
                            type="button"
                            class="rounded-lg bg-primary px-3 py-2 text-sm font-bold text-primary-foreground"
                            @click="openCreateLocation"
                        >
                            Tambah Lokasi
                        </button>
                    </div>
                    <input
                        v-model="search"
                        class="h-10 rounded-lg border bg-background px-3 text-sm md:w-72"
                        placeholder="Cari lokasi..."
                    />
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b text-left">
                                <th class="px-3 py-3 font-black">Lokasi</th>
                                <th class="px-3 py-3 font-black">Alamat</th>
                                <th class="px-3 py-3 font-black">Koordinat</th>
                                <th class="px-3 py-3 font-black">Kelas / Atlet</th>
                                <th class="px-3 py-3 font-black">Radius</th>
                                <th class="px-3 py-3 font-black">Status</th>
                                <th class="px-3 py-3 font-black">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="filteredLocations.length === 0">
                                <td colspan="7" class="h-32 px-3 text-center text-muted-foreground">
                                    Belum ada lokasi.
                                </td>
                            </tr>
                            <tr
                                v-for="location in filteredLocations"
                                :key="location.id"
                                class="border-b hover:bg-muted/40"
                            >
                                <td class="max-w-[220px] px-3 py-4">
                                    <p class="truncate font-black">{{ location.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        <MapPin class="mr-1 inline size-3" />{{ location.location ?? '-' }}
                                    </p>
                                </td>
                                <td class="max-w-[260px] px-3 py-4">
                                    <p class="truncate">{{ location.address ?? '-' }}</p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ location.city }} {{ location.province }}
                                    </p>
                                </td>
                                <td class="px-3 py-4 text-xs text-muted-foreground">
                                    <span v-if="location.latitude && location.longitude">
                                        {{ location.latitude }}, {{ location.longitude }}
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-3 py-4">
                                    {{ location.groups_count }} kelas · {{ location.athletes_count }} atlet
                                </td>
                                <td class="px-3 py-4">{{ location.attendance_radius_meters }}m</td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-black"
                                        :class="
                                            location.is_active
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                        >{{ location.is_active ? 'AKTIF' : 'NONAKTIF' }}</span
                                    >
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded border px-2 py-1"
                                            @click="editLocation(location)"
                                        >
                                            <Pencil class="size-4" /></button
                                        ><button
                                            type="button"
                                            class="rounded border px-2 py-1 text-red-600"
                                            @click="deleteLocation(location)"
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

            <FormModal :open="showLocationForm" max-width-class="max-w-4xl" @close="closeLocationForm">
                <form class="grid gap-4" @submit.prevent="saveLocation">
                    <h2 class="text-xl font-black">{{ editingLocationId ? 'Edit Lokasi' : 'Tambah Lokasi' }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Paste link Google Maps untuk auto-fill, atau klik peta / isi koordinat manual.
                    </p>

                    <label class="grid gap-1 text-sm font-semibold">
                        Google Maps Link
                        <div class="flex flex-col gap-2 md:flex-row">
                            <input
                                v-model="form.google_maps_url"
                                class="h-10 flex-1 rounded-lg border bg-background px-3 text-sm"
                                placeholder="https://maps.app.goo.gl/... atau https://www.google.com/maps/..."
                                @change="autofillFromGoogleMaps"
                            />
                            <button
                                type="button"
                                class="rounded-lg border px-4 py-2 text-sm font-bold"
                                :disabled="googleLookupLoading"
                                @click="autofillFromGoogleMaps"
                            >
                                {{ googleLookupLoading ? 'Membaca...' : 'Auto-fill' }}
                            </button>
                        </div>
                        <span v-if="googleLookupMessage" class="text-xs text-muted-foreground">{{ googleLookupMessage }}</span>
                        <span v-if="form.errors.google_maps_url" class="text-xs text-destructive">{{ form.errors.google_maps_url }}</span>
                    </label>

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
                            <span v-if="form.errors.address" class="text-xs text-destructive">{{ form.errors.address }}</span>
                        </label>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">
                                Kota *
                                <input v-model="form.city" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                                <span v-if="form.errors.city" class="text-xs text-destructive">{{ form.errors.city }}</span>
                            </label>
                            <label class="grid gap-1 text-sm font-semibold">
                                Provinsi *
                                <input v-model="form.province" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                                <span v-if="form.errors.province" class="text-xs text-destructive">{{ form.errors.province }}</span>
                            </label>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold">
                                Latitude
                                <input v-model="form.latitude" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                            </label>
                            <label class="grid gap-1 text-sm font-semibold">
                                Longitude
                                <input v-model="form.longitude" class="h-10 rounded-lg border bg-background px-3 text-sm" />
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
                                <input v-model="form.timezone" class="h-10 rounded-lg border bg-background px-3 text-sm" />
                            </label>
                        </div>

                        <label class="flex h-10 items-center gap-2 rounded-lg border bg-background px-3 text-sm font-semibold">
                            <input v-model="form.is_active" type="checkbox" /> Aktif
                        </label>

                        <div class="flex gap-2">
                            <button
                                class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Saving...' : 'Save Lokasi' }}
                            </button>
                            <button type="button" class="rounded-lg border px-4 py-2 text-sm font-bold" @click="closeLocationForm">
                                Batal
                            </button>
                        </div>
                    </div>
                </form>
            </FormModal>
        </div>
    </AppLayout>
</template>
