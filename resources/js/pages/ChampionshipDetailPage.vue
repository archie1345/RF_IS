<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    exportMethod as championshipExport,
    index as championshipsIndex,
    show as championshipShow,
} from '@/routes/championships';
import { store as championshipCoachStore } from '@/routes/championships/coaches';
import {
    update as championshipRegistrationUpdate,
    result as championshipRegistrationResult,
} from '@/routes/championships/registrations';
import { show as userShow } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{
    isAdmin: boolean;
    canManageCoaches: boolean;
    canRecordResult: boolean;
    event: {
        id: number;
        name: string;
        date: string;
        location: string;
        gmaps_url?: string | null;
        entry_fee: number;
        status: string;
    };
    athleteRows: TableRow[];
    coachRows: TableRow[];
    coachOptions: SelectOption[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Championships', href: championshipsIndex.url() },
    { title: props.event.name, href: championshipShow.url(props.event.id) },
];

const athleteColumns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'classification', label: 'Klasifikasi' },
    { key: 'division', label: 'Divisi' },
    { key: 'class_name', label: 'Class' },
    { key: 'category', label: 'Kategori' },
    { key: 'team_contingent', label: 'Tim/Kontingen' },
    { key: 'status', label: 'Status' },
];
const coachColumns: TableColumn[] = [
    { key: 'coach', label: 'Coach' },
    { key: 'role', label: 'Role' },
];
const categoryOptions = [
    { value: 'KYORUGI', label: 'Kyorugi' },
    { value: 'POOMSAE', label: 'Poomsae' },
    { value: 'FREESTYLE', label: 'Freestyle' },
    { value: 'UNKNOWN', label: 'Unknown' },
];

const showCoachForm = ref(false);
const showResultForm = ref(false);
const showRegistrationEditForm = ref(false);
const activeRegistrationId = ref<number | null>(null);
const coachForm = useForm({ coach_id: '', role: '' });
const resultForm = useForm({ medal: 'NONE', class_name: '', division: '', category: '' });
const editForm = useForm({
    category: 'KYORUGI',
    classification: '',
    class_name: '',
    division: '',
    team_contingent: 'rhino fighter',
});

function addCoach() {
    coachForm.post(championshipCoachStore.url(props.event.id), {
        onSuccess: () => {
            coachForm.reset();
            showCoachForm.value = false;
        },
    });
}

function openResultForm(row: TableRow) {
    const registrationId = routeId(row.id);
    if (!registrationId) return;
    activeRegistrationId.value = registrationId;
    resultForm.reset();
    resultForm.medal = 'NONE';
    showResultForm.value = true;
}

function openRegistrationEdit(row: TableRow) {
    const registrationId = routeId(row.id);
    if (!registrationId) return;
    activeRegistrationId.value = registrationId;
    editForm.category = String(row.category ?? 'KYORUGI');
    editForm.classification = row.classification === '-' ? '' : String(row.classification ?? '');
    editForm.class_name = row.class_name === '-' ? '' : String(row.class_name ?? '');
    editForm.division = row.division === '-' ? '' : String(row.division ?? '');
    editForm.team_contingent = String(row.team_contingent ?? 'rhino fighter');
    showRegistrationEditForm.value = true;
}

function athleteProfileUrl(row: TableRow): string | null {
    const userId = routeId(row.athlete_user_id);

    return userId === null ? null : userShow.url(userId);
}

function saveRegistrationEdit() {
    if (!activeRegistrationId.value) return;
    editForm.put(championshipRegistrationUpdate.url(activeRegistrationId.value), {
        onSuccess: () => {
            showRegistrationEditForm.value = false;
            activeRegistrationId.value = null;
        },
    });
}

function saveResult() {
    if (!activeRegistrationId.value) return;
    resultForm.post(championshipRegistrationResult.url(activeRegistrationId.value), {
        onSuccess: () => {
            showResultForm.value = false;
            activeRegistrationId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Championship - ${props.event.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                eyebrow="Championship Detail"
                :title="props.event.name"
                description="Participants overview for this championship."
            >
                <template #actions>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child type="button" variant="outline"
                            ><Link :href="championshipsIndex.url()">Back to championships</Link></Button
                        >
                        <Button v-if="props.canManageCoaches" type="button" @click="showCoachForm = true"
                            >Add coach join</Button
                        >
                        <Button v-if="props.canManageCoaches" as-child type="button" variant="outline"
                            ><a :href="championshipExport.url(props.event.id)">Export CSV</a></Button
                        >
                    </div>
                </template>
                <div class="grid gap-2 text-sm text-muted-foreground">
                    <p><span class="font-medium text-foreground">Date:</span> {{ props.event.date }}</p>
                    <p><span class="font-medium text-foreground">Location:</span> {{ props.event.location }}</p>
                    <p>
                        <span class="font-medium text-foreground">Entry fee:</span> Rp
                        {{ props.event.entry_fee.toLocaleString() }}
                    </p>
                    <p>
                        <span class="font-medium text-foreground">Maps:</span>
                        <a
                            v-if="props.event.gmaps_url"
                            :href="props.event.gmaps_url"
                            target="_blank"
                            class="underline underline-offset-2"
                            >Open location</a
                        ><span v-else>-</span>
                    </p>
                </div>
            </PageSection>

            <DataTable
                title="Athletes joined"
                description="Edit athlete championship data, open athlete profile, or record results."
                :columns="athleteColumns"
                :rows="props.athleteRows"
                action-label="Actions"
                searchable
            >
                <template #row-actions="{ row }">
                    <ActionButtonsRow>
                        <Button
                            v-if="props.canRecordResult"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openRegistrationEdit(row)"
                            >Edit entry</Button
                        >
                        <Button v-if="athleteProfileUrl(row)" as-child type="button" size="sm" variant="outline"
                            ><Link :href="athleteProfileUrl(row) ?? '#'">Edit athlete</Link></Button
                        >
                        <Button
                            v-if="props.canRecordResult"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openResultForm(row)"
                            >Record result</Button
                        >
                    </ActionButtonsRow>
                </template>
            </DataTable>
            <DataTable
                title="Coaches joined"
                description="Coaches assigned to this championship."
                :columns="coachColumns"
                :rows="props.coachRows"
                searchable
            />
        </div>

        <FormModal
            :open="showCoachForm && props.canManageCoaches"
            max-width-class="max-w-xl"
            @close="showCoachForm = false"
        >
            <PageSection title="Add coach to championship" description="Assign an active coach to this event.">
                <form class="grid gap-4" @submit.prevent="addCoach">
                    <FormSelectField
                        v-if="props.isAdmin"
                        id="coach-event"
                        v-model="coachForm.coach_id"
                        label="Coach"
                        :options="props.coachOptions"
                        :error="coachForm.errors.coach_id"
                    />
                    <FormInputField
                        id="coach-role"
                        v-model="coachForm.role"
                        label="Role"
                        placeholder="Head coach / Assistant"
                        :error="coachForm.errors.role"
                    />
                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" class="w-full sm:w-auto" :disabled="coachForm.processing"
                            >Add coach</Button
                        ><Button type="button" class="w-full sm:w-auto" variant="outline" @click="showCoachForm = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="showRegistrationEditForm && props.canRecordResult"
            max-width-class="max-w-xl"
            @close="showRegistrationEditForm = false"
        >
            <PageSection
                title="Edit athlete championship entry"
                description="Update classification, class, division, category, and team/contingent for export."
            >
                <form class="grid gap-4" @submit.prevent="saveRegistrationEdit">
                    <FormSelectField
                        id="edit-category"
                        v-model="editForm.category"
                        label="Class/Kategori"
                        :options="categoryOptions"
                        :error="editForm.errors.category"
                    />
                    <FormInputField
                        id="edit-classification"
                        v-model="editForm.classification"
                        label="Klasifikasi"
                        :error="editForm.errors.classification"
                    />
                    <FormInputField
                        id="edit-class-name"
                        v-model="editForm.class_name"
                        label="Class"
                        :error="editForm.errors.class_name"
                    />
                    <FormInputField
                        id="edit-division"
                        v-model="editForm.division"
                        label="Divisi"
                        :error="editForm.errors.division"
                    />
                    <FormInputField
                        id="edit-team"
                        v-model="editForm.team_contingent"
                        label="Tim/Kontingen"
                        :error="editForm.errors.team_contingent"
                    />
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="editForm.processing">Save entry</Button
                        ><Button type="button" variant="outline" @click="showRegistrationEditForm = false"
                            >Cancel</Button
                        >
                    </div>
                </form>
            </PageSection>
        </FormModal>

        <FormModal
            :open="showResultForm && props.canRecordResult"
            max-width-class="max-w-xl"
            @close="showResultForm = false"
        >
            <PageSection
                title="Record result"
                description="This will auto-create or update achievement and medal counter for this athlete."
            >
                <form class="grid gap-4" @submit.prevent="saveResult">
                    <FormSelectField
                        id="result-medal"
                        v-model="resultForm.medal"
                        label="Medal"
                        :options="[
                            { value: 'GOLD', label: 'Gold' },
                            { value: 'SILVER', label: 'Silver' },
                            { value: 'BRONZE', label: 'Bronze' },
                            { value: 'NONE', label: 'None' },
                        ]"
                        :error="resultForm.errors.medal"
                    />
                    <FormInputField
                        id="result-class"
                        v-model="resultForm.class_name"
                        label="Class"
                        :error="resultForm.errors.class_name"
                    />
                    <FormInputField
                        id="result-division"
                        v-model="resultForm.division"
                        label="Division"
                        :error="resultForm.errors.division"
                    />
                    <FormInputField
                        id="result-category"
                        v-model="resultForm.category"
                        label="Category"
                        :error="resultForm.errors.category"
                    />
                    <div class="flex gap-3">
                        <Button type="submit" :disabled="resultForm.processing">Save result</Button
                        ><Button type="button" variant="outline" @click="showResultForm = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
