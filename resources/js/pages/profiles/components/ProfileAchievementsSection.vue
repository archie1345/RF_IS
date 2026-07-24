<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { FileText, PencilLine, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FormFileField from '@/components/forms/FormFileField.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import ActionButtonsRow from '@/components/shared/ActionButtonsRow.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import { documentFileAccept, medalOptions } from '@/pages/profiles/profileOptions';
import { achievementColumns, achievementRows } from '@/pages/profiles/profileTables';
import type { ProfileAchievement } from '@/pages/profiles/types';
import type { TableRow } from '@/types/resource-table';

const props = defineProps<{
    achievements: ProfileAchievement[];
    canManage: boolean;
    storeUrl: string;
    updateUrl: (id: number | string) => string;
}>();
const popup = useAppPopup();

const rows = computed(() =>
    achievementRows(props.achievements).map((row) => {
        const achievement = props.achievements.find((item) => String(item.id) === String(row.id));

        return {
            ...row,
            is_auto_recorded: achievement?.is_auto_recorded ?? false,
        };
    }),
);
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

function addAchievement(): void {
    achievementForm.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            achievementForm.reset();
            achievementForm.medal = 'NONE';
        },
    });
}

function findAchievement(row: TableRow): ProfileAchievement | undefined {
    return props.achievements.find((item) => String(item.id) === String(row.id));
}

function openAchievementEdit(row: TableRow): void {
    const achievement = findAchievement(row);
    if (!achievement || achievement.is_auto_recorded) return;

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

function closeAchievementEdit(): void {
    editingAchievement.value = null;
    achievementEditForm.reset();
    achievementEditForm.clearErrors();
}

function saveAchievementEdit(): void {
    if (!editingAchievement.value || editingAchievement.value.is_auto_recorded) return;

    achievementEditForm
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(props.updateUrl(editingAchievement.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeAchievementEdit,
            onFinish: () => achievementEditForm.transform((data) => data),
        });
}

async function removeAchievement(row: TableRow): Promise<void> {
    const achievement = findAchievement(row);
    if (!achievement || achievement.is_auto_recorded) return;

    const confirmed = await popup.confirm({
        title: 'Hapus prestasi?',
        message: `Prestasi “${achievement.championship_name}” dan berkas pendukungnya akan dihapus dari profil.`,
        tone: 'danger',
        confirmLabel: 'Hapus prestasi',
    });
    if (!confirmed) return;

    router.delete(props.updateUrl(achievement.id), { preserveScroll: true });
}
</script>

<template>
    <div class="min-w-0 rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
        <h4 class="mb-3 flex items-center gap-2 font-semibold">
            <FileText class="h-4 w-4 text-muted-foreground" />
            Prestasi
        </h4>
        <DataTable
            title="Prestasi"
            description="Prestasi manual dapat dikelola di sini. Prestasi otomatis mengikuti hasil kejuaraan."
            :columns="achievementColumns"
            :rows="rows"
            action-label="Tindakan"
            empty-text="Belum ada prestasi."
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
                <span v-else-if="column.key === 'championship_name' && row.is_auto_recorded">
                    {{ value }}
                    <span class="ml-1 rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                        Otomatis
                    </span>
                </span>
                <span v-else>{{ value ?? '-' }}</span>
            </template>
            <template v-if="props.canManage" #row-actions="{ row }">
                <ActionButtonsRow v-if="row.is_auto_recorded !== true">
                    <Button type="button" variant="outline" size="sm" class="gap-2" @click="openAchievementEdit(row)">
                        <PencilLine class="h-3.5 w-3.5" />
                        Ubah
                    </Button>
                    <Button type="button" variant="destructive" size="sm" class="gap-2" @click="removeAchievement(row)">
                        <Trash2 class="h-3.5 w-3.5" />
                        Hapus
                    </Button>
                </ActionButtonsRow>
                <span v-else class="text-xs text-muted-foreground">Dikelola dari hasil kejuaraan</span>
            </template>
        </DataTable>

        <div v-if="props.canManage" class="mt-6 border-t border-border pt-4">
            <h5 class="mb-2 font-medium">Tambah prestasi manual</h5>
            <form class="grid min-w-0 gap-3" @submit.prevent="addAchievement">
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="ach-name"
                        v-model="achievementForm.championship_name"
                        label="Nama kejuaraan atau prestasi"
                        required
                        :error="achievementForm.errors.championship_name"
                    />
                    <FormSelectField
                        id="ach-medal"
                        v-model="achievementForm.medal"
                        label="Medali"
                        :options="medalOptions"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="ach-location"
                        v-model="achievementForm.location"
                        label="Lokasi"
                        :error="achievementForm.errors.location"
                    />
                    <FormInputField
                        id="ach-date"
                        v-model="achievementForm.event_date"
                        label="Tanggal"
                        type="date"
                        :error="achievementForm.errors.event_date"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <FormInputField id="ach-class" v-model="achievementForm.class_name" label="Kelas" />
                    <FormInputField id="ach-division" v-model="achievementForm.division" label="Divisi" />
                    <FormInputField id="ach-category" v-model="achievementForm.category" label="Kategori" />
                </div>
                <FormInputField id="ach-notes" v-model="achievementForm.notes" label="Catatan" />
                <FormFileField
                    id="achievement-file"
                    v-model="achievementForm.file"
                    label="Berkas pendukung"
                    :accept="documentFileAccept"
                    :error="achievementForm.errors.file"
                />
                <Button type="submit" class="w-full sm:w-fit" :disabled="achievementForm.processing">
                    Tambah prestasi
                </Button>
            </form>
        </div>

        <FormModal :open="Boolean(editingAchievement)" max-width-class="max-w-2xl" @close="closeAchievementEdit">
            <form class="grid min-w-0 gap-4" @submit.prevent="saveAchievementEdit">
                <div>
                    <h3 class="text-lg font-semibold">Ubah prestasi</h3>
                    <p class="text-sm text-muted-foreground">Perbarui detail atau ganti berkas pendukung.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="ach-edit-name"
                        v-model="achievementEditForm.championship_name"
                        label="Nama kejuaraan atau prestasi"
                        required
                        :error="achievementEditForm.errors.championship_name"
                    />
                    <FormSelectField
                        id="ach-edit-medal"
                        v-model="achievementEditForm.medal"
                        label="Medali"
                        :options="medalOptions"
                        :error="achievementEditForm.errors.medal"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="ach-edit-location"
                        v-model="achievementEditForm.location"
                        label="Lokasi"
                        :error="achievementEditForm.errors.location"
                    />
                    <FormInputField
                        id="ach-edit-date"
                        v-model="achievementEditForm.event_date"
                        label="Tanggal"
                        type="date"
                        :error="achievementEditForm.errors.event_date"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <FormInputField
                        id="ach-edit-class"
                        v-model="achievementEditForm.class_name"
                        label="Kelas"
                        :error="achievementEditForm.errors.class_name"
                    />
                    <FormInputField
                        id="ach-edit-division"
                        v-model="achievementEditForm.division"
                        label="Divisi"
                        :error="achievementEditForm.errors.division"
                    />
                    <FormInputField
                        id="ach-edit-category"
                        v-model="achievementEditForm.category"
                        label="Kategori"
                        :error="achievementEditForm.errors.category"
                    />
                </div>
                <FormInputField
                    id="ach-edit-notes"
                    v-model="achievementEditForm.notes"
                    label="Catatan"
                    :error="achievementEditForm.errors.notes"
                />
                <FormFileField
                    id="achievement-edit-file"
                    v-model="achievementEditForm.file"
                    label="Ganti berkas pendukung"
                    :accept="documentFileAccept"
                    :error="achievementEditForm.errors.file"
                    :current-file-name="editingAchievement?.fileName"
                    :current-file-url="editingAchievement?.fileUrl"
                />
                <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                    <Button type="button" variant="outline" @click="closeAchievementEdit">Batal</Button>
                    <Button type="submit" :disabled="achievementEditForm.processing">Simpan prestasi</Button>
                </div>
            </form>
        </FormModal>
    </div>
</template>
