<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as championshipsIndex, show as championshipShow } from '@/routes/championships';
import { store as championshipEventStore } from '@/routes/championships/events';
import { store as championshipRegistrationStore } from '@/routes/championships/registrations';
import { index as paymentsIndex } from '@/routes/payments';
import type { BreadcrumbItem } from '@/types';
import type { Metric, SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        isAdmin: boolean;
        isAthlete?: boolean;
        canRegister: boolean;
        metrics: Metric[];
        rows: TableRow[];
        athletes: SelectOption[];
        events: SelectOption[];
        pendingPayments: { payment_id: number; athlete: string; amount: number; remaining: number }[];
    }>(),
    {
        isAthlete: false,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Championships', href: championshipsIndex.url() },
];

const columns: TableColumn[] = [
    { key: 'event', label: 'Championship' },
    { key: 'date', label: 'Date' },
    { key: 'location', label: 'Location' },
    { key: 'slots', label: 'Slots', align: 'right' },
];

const categoryOptions = [
    { value: 'KYORUGI', label: 'Kyorugi' },
    { value: 'POOMSAE', label: 'Poomsae' },
    { value: 'FREESTYLE', label: 'Freestyle' },
    { value: 'UNKNOWN', label: 'Unknown' },
];

const form = useForm({
    athlete_id: '',
    event_id: '',
    category: 'KYORUGI',
    classification: '',
    class_name: '',
    division: '',
    team_contingent: 'rhino fighter',
});
const showRegistrationForm = ref(false);
const showEventForm = ref(false);
const showPaymentPrompt = ref(false);

const eventForm = useForm({
    name: '',
    date: '',
    location: '',
    gmaps_url: '',
    entry_fee: '',
    max_slots: '24',
    level: 'LOCAL',
});

function submit() {
    form.post(championshipRegistrationStore.url(), {
        onSuccess: () => {
            form.reset();
            form.category = 'KYORUGI';
            form.team_contingent = 'rhino fighter';
            showRegistrationForm.value = false;
            if (props.pendingPayments.length > 0) showPaymentPrompt.value = true;
        },
    });
}

function submitEvent() {
    eventForm.post(championshipEventStore.url(), {
        onSuccess: () => {
            eventForm.reset();
            showEventForm.value = false;
        },
    });
}

function openPaymentPrompt() {
    if (props.pendingPayments.length === 0) return;
    showPaymentPrompt.value = true;
}

function openRegistrationForEvent(row: TableRow) {
    form.event_id = String(row.event_id ?? '');
    showRegistrationForm.value = true;
}

onMounted(() => {
    if (props.isAthlete && !form.athlete_id && props.athletes.length === 1)
        form.athlete_id = String(props.athletes[0].value);
    if (props.pendingPayments.length > 0) openPaymentPrompt();
});
</script>

<template>
    <Head title="Championships" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Championship module"
                title="Championships"
                description="Register for open events. Any championship fee appears as a bill in the Payment Center for athletes and parents only."
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="props.pendingPayments.length > 0"
                            type="button"
                            variant="outline"
                            @click="openPaymentPrompt"
                            >Open unpaid bill</Button
                        >
                        <Button v-if="props.isAdmin" type="button" @click="showEventForm = true"
                            >Add championship/event</Button
                        >
                    </div>
                </template>
            </PageSection>

            <DataTable
                title="Championship list"
                description="Open events with registration actions beside each row."
                :columns="columns"
                :rows="props.rows"
                action-label="Register / details"
                empty-text="No championships are open yet."
                searchable
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            v-if="props.canRegister"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openRegistrationForEvent(row)"
                            >Register</Button
                        >
                        <Button as-child type="button" size="sm" variant="outline"
                            ><Link :href="championshipShow.url(String(row.event_id))">View participants</Link></Button
                        >
                    </ActionButtonsRow>
                </template>
            </DataTable>
        </div>

        <FormModal :open="showRegistrationForm" max-width-class="max-w-2xl" @close="showRegistrationForm = false">
            <PageSection
                title="Register for championship"
                description="Choose athlete, classification, class, and division. Team defaults to rhino fighter but admin can change it."
            >
                <form class="grid gap-4" @submit.prevent="submit">
                    <FormSelectField
                        v-if="props.athletes.length > 1"
                        id="event-athlete"
                        v-model="form.athlete_id"
                        label="Athlete"
                        :options="props.athletes"
                        placeholder="Select athlete"
                        required
                        :error="form.errors.athlete_id"
                    />
                    <div v-else-if="props.athletes.length === 1" class="grid gap-2">
                        <label class="text-sm font-medium">Athlete</label>
                        <input
                            :value="props.athletes[0].label"
                            disabled
                            class="h-10 rounded-md border border-input bg-muted px-3 py-2 text-sm"
                        />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormSelectField
                            id="event-name"
                            v-model="form.event_id"
                            label="Championship"
                            :options="props.events"
                            placeholder="Select event"
                            required
                            :error="form.errors.event_id"
                        />
                        <FormSelectField
                            id="event-category"
                            v-model="form.category"
                            label="Class/Kategori"
                            :options="categoryOptions"
                            required
                            :error="form.errors.category"
                        />
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <FormInputField
                            id="event-classification"
                            v-model="form.classification"
                            label="Klasifikasi"
                            placeholder="Cadet / Junior / Senior"
                            :error="form.errors.classification"
                        />
                        <FormInputField
                            id="event-class-name"
                            v-model="form.class_name"
                            label="Class"
                            placeholder="Under 45 kg / Individual"
                            :error="form.errors.class_name"
                        />
                        <FormInputField
                            id="event-division"
                            v-model="form.division"
                            label="Divisi"
                            placeholder="Putra / Putri / Weight division"
                            :error="form.errors.division"
                        />
                    </div>
                    <FormInputField
                        id="event-team"
                        v-model="form.team_contingent"
                        label="Tim/Kontingen"
                        placeholder="rhino fighter"
                        help="Default: rhino fighter"
                        :error="form.errors.team_contingent"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing"
                            >Submit registration</Button
                        >
                        <Button
                            type="button"
                            class="w-full sm:w-auto"
                            variant="outline"
                            @click="showRegistrationForm = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showEventForm" max-width-class="max-w-xl" @close="showEventForm = false">
            <PageSection
                title="Add championship"
                description="Create a public event. Max slots already has a safe default and can be changed if needed."
            >
                <form class="grid gap-4" @submit.prevent="submitEvent">
                    <FormInputField
                        id="event-new-name"
                        v-model="eventForm.name"
                        label="Championship name"
                        required
                        :error="eventForm.errors.name"
                    />
                    <FormInputField
                        id="event-new-date"
                        v-model="eventForm.date"
                        label="Event date"
                        type="date"
                        required
                        :error="eventForm.errors.date"
                    />
                    <FormInputField
                        id="event-new-location"
                        v-model="eventForm.location"
                        label="Place"
                        placeholder="Example: GOR Jakarta Selatan"
                        required
                        :error="eventForm.errors.location"
                    />
                    <FormInputField
                        id="event-new-gmaps"
                        v-model="eventForm.gmaps_url"
                        label="Google Maps link"
                        type="url"
                        placeholder="https://maps.google.com/..."
                        :error="eventForm.errors.gmaps_url"
                    />
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormInputField
                            id="event-new-price"
                            v-model="eventForm.entry_fee"
                            label="Entry fee"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="1000"
                            required
                            :error="eventForm.errors.entry_fee"
                        />
                        <FormInputField
                            id="event-new-slots"
                            v-model="eventForm.max_slots"
                            label="Maximum athletes"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            step="1"
                            help="Default is 24."
                            :error="eventForm.errors.max_slots"
                        />
                    </div>
                    <FormSelectField
                        id="event-new-level"
                        v-model="eventForm.level"
                        label="Level"
                        :options="[
                            { value: 'LOCAL', label: 'Local' },
                            { value: 'REGIONAL', label: 'Regional' },
                            { value: 'NATIONAL', label: 'National' },
                            { value: 'INTERNATIONAL', label: 'International' },
                        ]"
                        :error="eventForm.errors.level"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="eventForm.processing"
                            >Save event</Button
                        >
                        <Button type="button" class="w-full sm:w-auto" variant="outline" @click="showEventForm = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal :open="showPaymentPrompt" max-width-class="max-w-xl" @close="showPaymentPrompt = false">
            <PageSection
                title="Championship bill ready"
                description="The bill is now in the Payment Center. Pay using the admin instructions, then upload the receipt there for review."
            >
                <div class="grid gap-2 text-sm" v-if="props.pendingPayments.length > 0">
                    <p><span class="font-medium">Athlete:</span> {{ props.pendingPayments[0].athlete }}</p>
                    <p>
                        <span class="font-medium">Amount:</span> Rp
                        {{ props.pendingPayments[0].amount.toLocaleString() }}
                    </p>
                    <p>
                        <span class="font-medium">Remaining:</span> Rp
                        {{ props.pendingPayments[0].remaining.toLocaleString() }}
                    </p>
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <Button as-child class="w-full sm:w-auto"
                        ><Link :href="paymentsIndex.url()">Open Payment Center</Link></Button
                    >
                    <Button type="button" class="w-full sm:w-auto" variant="outline" @click="showPaymentPrompt = false"
                        >Later</Button
                    >
                </div>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
