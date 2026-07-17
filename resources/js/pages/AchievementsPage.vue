<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as achievementsIndex, store as achievementsStore } from '@/routes/achievements';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

const props = defineProps<{ achievements: TableRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: 'Achievements', href: achievementsIndex.url() },
];

const achievementColumns: TableColumn[] = [
    { key: 'championship_name', label: 'Championship' },
    { key: 'medal', label: 'Medal' },
    { key: 'location', label: 'Location' },
    { key: 'event_date', label: 'Date' },
    { key: 'class_name', label: 'Class' },
    { key: 'division', label: 'Division' },
    { key: 'category', label: 'Category' },
    { key: 'file_name', label: 'File' },
];
const showAchievementModal = ref(false);

const achievementForm = useForm({
    championship_name: '',
    medal: 'NONE',
    location: '',
    event_date: '',
    class_name: '',
    division: '',
    category: '',
    notes: '',
    file: null as File | null,
});

function onAchievementFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    achievementForm.file = target.files?.[0] ?? null;
}

function addAchievement() {
    achievementForm.post(achievementsStore.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showAchievementModal.value = false;
            achievementForm.reset();
            achievementForm.medal = 'NONE';
        },
    });
}
</script>

<template>
    <Head title="Achievements" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection title="My Achievements" description="Dedicated page for event and medal achievements.">
                <template #actions>
                    <Button type="button" @click="showAchievementModal = true">Add achievement</Button>
                </template>
            </PageSection>
            <DataTable
                title="Achievements"
                description="Your event and medal achievements."
                :columns="achievementColumns"
                :rows="props.achievements"
            >
                <template #cell="{ row, column, value }">
                    <a
                        v-if="column.key === 'file_name' && row.file_url"
                        :href="String(row.file_url)"
                        target="_blank"
                        class="text-sm font-medium underline underline-offset-4"
                    >
                        {{ value }}
                    </a>
                    <span v-else>{{ value ?? '-' }}</span>
                </template>
            </DataTable>
        </div>
        <FormModal :open="showAchievementModal" max-width-class="max-w-2xl" @close="showAchievementModal = false">
            <PageSection
                title="Add achievement"
                description="Record past achievements manually with details and optional file."
            >
                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="addAchievement">
                    <FormInputField
                        id="ach-name"
                        v-model="achievementForm.championship_name"
                        label="Championship name"
                        :error="achievementForm.errors.championship_name"
                    />
                    <FormSelectField
                        id="ach-medal"
                        v-model="achievementForm.medal"
                        label="Medal"
                        :options="[
                            { value: 'GOLD', label: 'Gold' },
                            { value: 'SILVER', label: 'Silver' },
                            { value: 'BRONZE', label: 'Bronze' },
                            { value: 'NONE', label: 'None' },
                        ]"
                        :error="achievementForm.errors.medal"
                    />
                    <FormInputField
                        id="ach-location"
                        v-model="achievementForm.location"
                        label="Location"
                        :error="achievementForm.errors.location"
                    />
                    <FormInputField
                        id="ach-date"
                        v-model="achievementForm.event_date"
                        type="date"
                        label="Date"
                        :error="achievementForm.errors.event_date"
                    />
                    <FormInputField
                        id="ach-class"
                        v-model="achievementForm.class_name"
                        label="Class"
                        :error="achievementForm.errors.class_name"
                    />
                    <FormInputField
                        id="ach-division"
                        v-model="achievementForm.division"
                        label="Division"
                        :error="achievementForm.errors.division"
                    />
                    <FormInputField
                        id="ach-category"
                        v-model="achievementForm.category"
                        label="Category"
                        :error="achievementForm.errors.category"
                    />
                    <FormInputField
                        id="ach-notes"
                        v-model="achievementForm.notes"
                        label="Notes"
                        :error="achievementForm.errors.notes"
                    />
                    <div class="grid gap-2 md:col-span-2">
                        <label class="text-sm font-medium">Attach file (optional)</label>
                        <input
                            type="file"
                            class="h-10 rounded-lg border border-input px-3 py-2 text-sm"
                            @change="onAchievementFileChange"
                        />
                        <p v-if="!achievementForm.errors.file" class="text-xs leading-5 text-muted-foreground">
                            Attach a certificate, result sheet, medal photo, or PDF.
                        </p>
                        <p v-if="achievementForm.errors.file" class="text-sm text-destructive">
                            {{ achievementForm.errors.file }}
                        </p>
                    </div>
                    <div class="flex gap-3 md:col-span-2">
                        <Button type="submit" :disabled="achievementForm.processing">Save</Button>
                        <Button type="button" variant="outline" @click="showAchievementModal = false">Cancel</Button>
                    </div>
                </form>
            </PageSection>
        </FormModal>
    </AppLayout>
</template>
