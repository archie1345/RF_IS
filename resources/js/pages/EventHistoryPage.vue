<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ExternalLink, PencilLine, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as championshipsIndex } from '@/routes/championships';
import { result as championshipRegistrationResult } from '@/routes/championships/registrations';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

type HistoricalRegistration = {
    id: number;
    athlete: string;
    classification: string;
    entry_category: string;
    entry_class_name: string;
    entry_division: string;
    team_contingent: string;
    status: string;
    result_medal: string;
    result_class_name: string;
    result_division: string;
    result_category: string;
    has_result: boolean;
};

type HistoricalEvent = {
    id: number;
    name: string;
    date: string;
    date_raw: string;
    organizer: string;
    location: string;
    level: string;
    status: string;
    participants_count: number;
    results_count: number;
    detail_url: string;
    registrations: HistoricalRegistration[];
};

const props = defineProps<{ events: HistoricalEvent[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Kejuaraan & UKT', href: championshipsIndex.url() },
    { title: 'Riwayat Event & UKT', href: '/admin/events/history' },
];

const search = ref('');
const expandedEventIds = ref<number[]>([]);
const showResultForm = ref(false);
const activeEvent = ref<HistoricalEvent | null>(null);
const activeRegistration = ref<HistoricalRegistration | null>(null);

const resultForm = useForm({
    medal: 'NONE',
    class_name: '',
    division: '',
    category: '',
});

const resultColumns: TableColumn[] = [
    { key: 'athlete', label: 'Atlet' },
    { key: 'entry', label: 'Entri' },
    { key: 'contingent', label: 'Kontingen' },
    { key: 'medal', label: 'Hasil' },
    { key: 'result_detail', label: 'Detail hasil' },
    { key: 'status', label: 'Status' },
];

const medalOptions = [
    { value: 'GOLD', label: 'Emas' },
    { value: 'SILVER', label: 'Perak' },
    { value: 'BRONZE', label: 'Perunggu' },
    { value: 'NONE', label: 'Tanpa medali / Tidak lulus' },
];

const filteredEvents = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.events;

    return props.events.filter((event) =>
        [event.name, event.date, event.organizer, event.location, event.level, event.status]
            .join(' ')
            .toLowerCase()
            .includes(keyword),
    );
});

function toggleEvent(eventId: number): void {
    expandedEventIds.value = expandedEventIds.value.includes(eventId)
        ? expandedEventIds.value.filter((id) => id !== eventId)
        : [...expandedEventIds.value, eventId];
}

function isExpanded(eventId: number): boolean {
    return expandedEventIds.value.includes(eventId);
}

function resultRows(event: HistoricalEvent): TableRow[] {
    return event.registrations.map((registration) => ({
        id: registration.id,
        registration_id: registration.id,
        athlete: registration.athlete,
        entry: [registration.classification, registration.entry_category, registration.entry_class_name, registration.entry_division]
            .filter((value) => value && value !== '-')
            .join(' · ') || '-',
        contingent: registration.team_contingent,
        medal: medalLabel(registration.result_medal, registration.has_result),
        result_detail: [registration.result_category, registration.result_class_name, registration.result_division]
            .filter((value) => value && value !== '-')
            .join(' · ') || '-',
        status: registration.status,
        registration,
        event,
    }));
}

function medalLabel(medal: string, hasResult: boolean): string {
    if (!hasResult) return 'Belum dicatat';

    return ({
        GOLD: 'Emas',
        SILVER: 'Perak',
        BRONZE: 'Perunggu',
        NONE: 'Tanpa medali / Tidak lulus',
    } as Record<string, string>)[medal] ?? medal;
}

function rowHasResult(row: TableRow): boolean {
    const registration = row.registration as HistoricalRegistration | undefined;
    return registration?.has_result === true;
}

function openResult(event: HistoricalEvent, registration: HistoricalRegistration): void {
    activeEvent.value = event;
    activeRegistration.value = registration;
    resultForm.medal = registration.result_medal || 'NONE';
    resultForm.class_name = registration.result_class_name || '';
    resultForm.division = registration.result_division || '';
    resultForm.category = registration.result_category || '';
    resultForm.clearErrors();
    showResultForm.value = true;
}

function openResultFromRow(row: TableRow): void {
    const event = row.event as HistoricalEvent | undefined;
    const registration = row.registration as HistoricalRegistration | undefined;
    if (event && registration) openResult(event, registration);
}

function saveResult(): void {
    if (!activeRegistration.value) return;

    resultForm.post(championshipRegistrationResult.url(activeRegistration.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showResultForm.value = false;
            activeEvent.value = null;
            activeRegistration.value = null;
        },
    });
}
</script>

<template>
    <Head title="Riwayat Event & UKT" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-6 p-3 sm:p-4 md:p-6">
            <PageSection
                eyebrow="Arsip kompetisi"
                title="Riwayat Event & UKT"
                description="Lihat kembali event yang telah selesai dan koreksi hasil peserta tanpa menghapus atau membuat ulang riwayat event."
            >
                <template #actions>
                    <Button as-child type="button" variant="outline">
                        <Link :href="championshipsIndex.url()">Kelola event aktif</Link>
                    </Button>
                </template>

                <div class="relative max-w-xl">
                    <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="search"
                        type="search"
                        class="h-10 w-full rounded-lg border bg-background pr-3 pl-10 text-sm"
                        placeholder="Cari event, tanggal, lokasi, penyelenggara, atau level..."
                    />
                </div>
            </PageSection>

            <div v-if="filteredEvents.length" class="grid gap-4">
                <article
                    v-for="event in filteredEvents"
                    :key="event.id"
                    class="overflow-hidden rounded-xl border bg-card shadow-sm"
                >
                    <div class="grid gap-4 p-4 md:grid-cols-[1fr_auto] md:items-center md:p-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-lg font-bold">{{ event.name }}</h2>
                                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold">{{ event.status }}</span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ event.date }} · {{ event.location }} · {{ event.level }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">Penyelenggara: {{ event.organizer }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-muted px-3 py-1">{{ event.participants_count }} peserta</span>
                                <span class="rounded-full bg-muted px-3 py-1">
                                    {{ event.results_count }} / {{ event.participants_count }} hasil tercatat
                                </span>
                            </div>
                        </div>

                        <ActionButtonsRow>
                            <Button as-child type="button" size="sm" variant="outline">
                                <Link :href="event.detail_url">
                                    Detail lengkap <ExternalLink class="ml-2 size-4" />
                                </Link>
                            </Button>
                            <Button type="button" size="sm" @click="toggleEvent(event.id)">
                                {{ isExpanded(event.id) ? 'Tutup hasil' : 'Lihat & edit hasil' }}
                                <ChevronUp v-if="isExpanded(event.id)" class="ml-2 size-4" />
                                <ChevronDown v-else class="ml-2 size-4" />
                            </Button>
                        </ActionButtonsRow>
                    </div>

                    <div v-if="isExpanded(event.id)" class="border-t bg-muted/20 p-3 sm:p-4 md:p-5">
                        <DataTable
                            title="Hasil peserta"
                            description="Hasil lama tetap dapat dikoreksi. Perubahan juga memperbarui prestasi otomatis atlet."
                            :columns="resultColumns"
                            :rows="resultRows(event)"
                            action-label="Tindakan"
                            empty-text="Event ini belum memiliki peserta."
                            searchable
                            search-placeholder="Cari atlet, kelas, divisi, kategori, atau hasil..."
                        >
                            <template #row-actions="{ row }">
                                <Button type="button" size="sm" variant="outline" @click="openResultFromRow(row)">
                                    <PencilLine class="mr-2 size-4" />
                                    {{ rowHasResult(row) ? 'Ubah hasil' : 'Catat hasil' }}
                                </Button>
                            </template>
                        </DataTable>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground">
                Tidak ada riwayat event atau UKT yang cocok.
            </div>
        </div>

        <FormModal :open="showResultForm" max-width-class="max-w-xl" @close="showResultForm = false">
            <PageSection
                :title="activeRegistration?.has_result ? 'Ubah hasil peserta' : 'Catat hasil peserta'"
                :description="`${activeRegistration?.athlete ?? 'Peserta'} · ${activeEvent?.name ?? 'Event'}`"
            >
                <form class="grid gap-4" @submit.prevent="saveResult">
                    <FormSelectField
                        id="history-result-medal"
                        v-model="resultForm.medal"
                        label="Hasil / Medali"
                        :options="medalOptions"
                        :error="resultForm.errors.medal"
                    />
                    <FormInputField
                        id="history-result-category"
                        v-model="resultForm.category"
                        label="Kategori hasil"
                        placeholder="Contoh: Kyorugi / Poomsae / UKT Geup"
                        :error="resultForm.errors.category"
                    />
                    <FormInputField
                        id="history-result-class"
                        v-model="resultForm.class_name"
                        label="Kelas / Tingkat"
                        placeholder="Contoh: U-68 kg / Geup 5"
                        :error="resultForm.errors.class_name"
                    />
                    <FormInputField
                        id="history-result-division"
                        v-model="resultForm.division"
                        label="Divisi"
                        :error="resultForm.errors.division"
                    />
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" @click="showResultForm = false">Batal</Button>
                        <Button type="submit" :disabled="resultForm.processing">
                            {{ resultForm.processing ? 'Menyimpan...' : 'Simpan perubahan hasil' }}
                        </Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
