<script setup lang="ts">
import FileUploadField from '@/components/forms/FileUploadField.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import { Button } from '@/components/ui/button';
import { documentFileAccept, medalOptions } from '@/pages/profiles/profileOptions';
import { achievementColumns, achievementRows } from '@/pages/profiles/profileTables';
import type { ProfileAchievement } from '@/pages/profiles/types';
import type { TableRow } from '@/types/management';
import { useForm } from '@inertiajs/vue3';
import { FileText, PencilLine } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    achievements: ProfileAchievement[];
    canManage: boolean;
    storeUrl: string;
    updateUrl: (id: number | string) => string;
}>();

const rows = computed(() => achievementRows(props.achievements));
const editingAchievement = ref<ProfileAchievement | null>(null);

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

const achievementEditForm = useForm({
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

function addAchievement() {
    achievementForm.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            achievementForm.reset();
            achievementForm.medal = 'NONE';
        },
        onError: (errors) => console.error('Achievement Errors:', errors),
    });
}

function openAchievementEdit(row: TableRow) {
    const achievement = props.achievements.find((item) => String(item.id) === String(row.id));

    if (!achievement) return;

    editingAchievement.value = achievement;
    achievementEditForm.championship_name = achievement.championship_name ?? '';
    achievementEditForm.medal = achievement.medal ?? 'NONE';
    achievementEditForm.location = achievement.location ?? '';
    achievementEditForm.event_date = achievement.event_date ?? '';
    achievementEditForm.class_name = achievement.class_name ?? '';
    achievementEditForm.division = achievement.division ?? '';
    achievementEditForm.category = achievement.category ?? '';
    achievementEditForm.notes = achievement.notes ?? '';
    achievementEditForm.file = null;
    achievementEditForm.clearErrors();
}

function closeAchievementEdit() {
    editingAchievement.value = null;
    achievementEditForm.reset();
    achievementEditForm.clearErrors();
}

function saveAchievementEdit() {
    if (!editingAchievement.value) return;

    achievementEditForm
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(props.updateUrl(editingAchievement.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeAchievementEdit();
            },
            onFinish: () => {
                achievementEditForm.transform((data) => data);
            },
        });
}
</script>

<template>
    <div class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
        <h4 class="mb-3 flex items-center gap-2 font-semibold">
            <FileText class="h-4 w-4 text-muted-foreground" />
            Achievements
        </h4>
        <DataTable
            title="Achievements"
            description="View all achievements for this user."
            :columns="achievementColumns"
            :rows="rows"
            action-label="Manage"
            empty-text="No achievements found."
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
            <template v-if="props.canManage" #row-actions="{ row }">
                <Button type="button" variant="outline" size="sm" class="gap-2" @click="openAchievementEdit(row)">
                    <PencilLine class="h-3.5 w-3.5" />
                    Edit
                </Button>
            </template>
        </DataTable>
        <div v-if="props.canManage" class="mt-6 border-t border-border pt-4">
            <h5 class="mb-2 font-medium">Add Achievement</h5>
            <form class="grid gap-3" @submit.prevent="addAchievement">
                <div class="grid gap-2 md:grid-cols-2">
                    <FormInputField
                        id="ach-name"
                        v-model="achievementForm.championship_name"
                        label="Championship name"
                        required
                        :error="achievementForm.errors.championship_name"
                    />
                    <FormSelectField
                        id="ach-medal"
                        v-model="achievementForm.medal"
                        label="Medal"
                        :options="medalOptions"
                    />
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <FormInputField
                        id="ach-location"
                        v-model="achievementForm.location"
                        label="Location"
                        :error="achievementForm.errors.location"
                    />
                    <FormInputField
                        id="ach-date"
                        v-model="achievementForm.event_date"
                        label="Date"
                        type="date"
                        :error="achievementForm.errors.event_date"
                    />
                </div>
                <div class="grid gap-2 md:grid-cols-3">
                    <FormInputField id="ach-class" v-model="achievementForm.class_name" label="Class name" />
                    <FormInputField id="ach-division" v-model="achievementForm.division" label="Division" />
                    <FormInputField id="ach-category" v-model="achievementForm.category" label="Category" />
                </div>
                <FormInputField id="ach-notes" v-model="achievementForm.notes" label="Notes" />
                <FileUploadField
                    id="achievement-file"
                    v-model="achievementForm.file"
                    label="Supporting File"
                    :accept="documentFileAccept"
                    :error="achievementForm.errors.file"
                />
                <div>
                    <Button type="submit" :disabled="achievementForm.processing">Add Achievement</Button>
                </div>
            </form>
        </div>

        <FormModal :open="Boolean(editingAchievement)" max-width-class="max-w-4xl" @close="closeAchievementEdit">
            <form class="grid gap-4" @submit.prevent="saveAchievementEdit">
                <div>
                    <h3 class="text-lg font-semibold">Edit Achievement</h3>
                    <p class="text-sm text-muted-foreground">
                        Update the achievement details or replace the supporting file.
                    </p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <FormInputField
                        id="ach-edit-name"
                        v-model="achievementEditForm.championship_name"
                        label="Championship name"
                        required
                        :error="achievementEditForm.errors.championship_name"
                    />
                    <FormSelectField
                        id="ach-edit-medal"
                        v-model="achievementEditForm.medal"
                        label="Medal"
                        :options="medalOptions"
                        :error="achievementEditForm.errors.medal"
                    />
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <FormInputField
                        id="ach-edit-location"
                        v-model="achievementEditForm.location"
                        label="Location"
                        :error="achievementEditForm.errors.location"
                    />
                    <FormInputField
                        id="ach-edit-date"
                        v-model="achievementEditForm.event_date"
                        label="Date"
                        type="date"
                        :error="achievementEditForm.errors.event_date"
                    />
                </div>
                <div class="grid gap-3 md:grid-cols-3">
                    <FormInputField
                        id="ach-edit-class"
                        v-model="achievementEditForm.class_name"
                        label="Class name"
                        :error="achievementEditForm.errors.class_name"
                    />
                    <FormInputField
                        id="ach-edit-division"
                        v-model="achievementEditForm.division"
                        label="Division"
                        :error="achievementEditForm.errors.division"
                    />
                    <FormInputField
                        id="ach-edit-category"
                        v-model="achievementEditForm.category"
                        label="Category"
                        :error="achievementEditForm.errors.category"
                    />
                </div>
                <FormInputField
                    id="ach-edit-notes"
                    v-model="achievementEditForm.notes"
                    label="Notes"
                    :error="achievementEditForm.errors.notes"
                />
                <FileUploadField
                    id="achievement-edit-file"
                    v-model="achievementEditForm.file"
                    label="Replace Supporting File"
                    :accept="documentFileAccept"
                    :error="achievementEditForm.errors.file"
                    :current-file-name="editingAchievement?.fileName"
                    :current-file-url="editingAchievement?.fileUrl"
                />

                <div class="flex flex-col justify-end gap-2 sm:flex-row">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" @click="closeAchievementEdit"
                        >Cancel</Button
                    >
                    <Button type="submit" class="w-full sm:w-auto" :disabled="achievementEditForm.processing"
                        >Save Achievement</Button
                    >
                </div>
            </form>
        </FormModal>
    </div>
</template>
