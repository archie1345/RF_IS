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
import { certificationTypeOptions, documentFileAccept } from '@/pages/profiles/profileOptions';
import { certificationColumns, certificationRows } from '@/pages/profiles/profileTables';
import type { ProfileCertification } from '@/pages/profiles/types';
import type { TableRow } from '@/types/resource-table';

const props = defineProps<{
    certifications: ProfileCertification[];
    canManage: boolean;
    storeUrl: string;
    updateUrl: (id: number | string) => string;
}>();

const rows = computed(() => certificationRows(props.certifications));
const editingCertification = ref<ProfileCertification | null>(null);

const certForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
    notes: '',
    file: null as File | null,
});

const certificationEditForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
    notes: '',
    file: null as File | null,
});

function addCertification(): void {
    certForm.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            certForm.reset();
            certForm.cert_type = 'BELT';
        },
    });
}

function findCertification(row: TableRow): ProfileCertification | undefined {
    return props.certifications.find((item) => String(item.id) === String(row.id));
}

function openCertificationEdit(row: TableRow): void {
    const certification = findCertification(row);
    if (!certification) return;

    editingCertification.value = certification;
    certificationEditForm.cert_type = certification.cert_type ?? 'BELT';
    certificationEditForm.title = certification.title ?? '';
    certificationEditForm.issuer = certification.issuer ?? '';
    certificationEditForm.certified_at = certification.certified_at ?? '';
    certificationEditForm.expires_at = certification.expires_at ?? '';
    certificationEditForm.notes = certification.notes ?? '';
    certificationEditForm.file = null;
    certificationEditForm.clearErrors();
}

function closeCertificationEdit(): void {
    editingCertification.value = null;
    certificationEditForm.reset();
    certificationEditForm.clearErrors();
}

function saveCertificationEdit(): void {
    if (!editingCertification.value) return;

    certificationEditForm
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(props.updateUrl(editingCertification.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeCertificationEdit,
            onFinish: () => certificationEditForm.transform((data) => data),
        });
}

function removeCertification(row: TableRow): void {
    const certification = findCertification(row);
    if (!certification || !window.confirm(`Hapus sertifikasi “${certification.title}”?`)) return;

    router.delete(props.updateUrl(certification.id), { preserveScroll: true });
}
</script>

<template>
    <div class="min-w-0 rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
        <h4 class="mb-3 flex items-center gap-2 font-semibold">
            <FileText class="h-4 w-4 text-muted-foreground" />
            Sertifikasi
        </h4>
        <DataTable
            title="Sertifikasi"
            description="Daftar sertifikasi sabuk, pelatih, atau wasit untuk pengguna ini."
            :columns="certificationColumns"
            :rows="rows"
            action-label="Tindakan"
            empty-text="Belum ada sertifikasi."
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
                <ActionButtonsRow>
                    <Button type="button" variant="outline" size="sm" class="gap-2" @click="openCertificationEdit(row)">
                        <PencilLine class="h-3.5 w-3.5" />
                        Ubah
                    </Button>
                    <Button type="button" variant="destructive" size="sm" class="gap-2" @click="removeCertification(row)">
                        <Trash2 class="h-3.5 w-3.5" />
                        Hapus
                    </Button>
                </ActionButtonsRow>
            </template>
        </DataTable>

        <div v-if="props.canManage" class="mt-6 border-t border-border pt-4">
            <h5 class="mb-2 font-medium">Tambah sertifikasi</h5>
            <form class="grid min-w-0 gap-3" @submit.prevent="addCertification">
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormSelectField
                        id="cert-type"
                        v-model="certForm.cert_type"
                        label="Jenis"
                        :options="certificationTypeOptions"
                    />
                    <FormInputField
                        id="cert-title"
                        v-model="certForm.title"
                        label="Nama sertifikasi"
                        required
                        :error="certForm.errors.title"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="cert-issuer"
                        v-model="certForm.issuer"
                        label="Penerbit"
                        :error="certForm.errors.issuer"
                    />
                    <FormInputField id="cert-date" v-model="certForm.certified_at" label="Tanggal sertifikasi" type="date" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField id="cert-expires" v-model="certForm.expires_at" label="Berlaku sampai" type="date" />
                    <FormInputField
                        id="cert-notes"
                        v-model="certForm.notes"
                        label="Catatan"
                        :error="certForm.errors.notes"
                    />
                </div>
                <FormFileField
                    id="cert-file"
                    v-model="certForm.file"
                    label="Berkas sertifikat"
                    :accept="documentFileAccept"
                    :error="certForm.errors.file"
                />
                <Button type="submit" class="w-full sm:w-fit" :disabled="certForm.processing">Tambah sertifikasi</Button>
            </form>
        </div>

        <FormModal :open="Boolean(editingCertification)" max-width-class="max-w-2xl" @close="closeCertificationEdit">
            <form class="grid min-w-0 gap-4" @submit.prevent="saveCertificationEdit">
                <div>
                    <h3 class="text-lg font-semibold">Ubah sertifikasi</h3>
                    <p class="text-sm text-muted-foreground">Perbarui detail atau ganti berkas sertifikat.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormSelectField
                        id="cert-edit-type"
                        v-model="certificationEditForm.cert_type"
                        label="Jenis"
                        :options="certificationTypeOptions"
                        :error="certificationEditForm.errors.cert_type"
                    />
                    <FormInputField
                        id="cert-edit-title"
                        v-model="certificationEditForm.title"
                        label="Nama sertifikasi"
                        required
                        :error="certificationEditForm.errors.title"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="cert-edit-issuer"
                        v-model="certificationEditForm.issuer"
                        label="Penerbit"
                        :error="certificationEditForm.errors.issuer"
                    />
                    <FormInputField
                        id="cert-edit-date"
                        v-model="certificationEditForm.certified_at"
                        label="Tanggal sertifikasi"
                        type="date"
                        :error="certificationEditForm.errors.certified_at"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <FormInputField
                        id="cert-edit-expires"
                        v-model="certificationEditForm.expires_at"
                        label="Berlaku sampai"
                        type="date"
                        :error="certificationEditForm.errors.expires_at"
                    />
                    <FormInputField
                        id="cert-edit-notes"
                        v-model="certificationEditForm.notes"
                        label="Catatan"
                        :error="certificationEditForm.errors.notes"
                    />
                </div>
                <FormFileField
                    id="cert-edit-file"
                    v-model="certificationEditForm.file"
                    label="Ganti berkas sertifikat"
                    :accept="documentFileAccept"
                    :error="certificationEditForm.errors.file"
                    :current-file-name="editingCertification?.fileName"
                    :current-file-url="editingCertification?.fileUrl"
                />
                <div class="grid grid-cols-1 gap-2 sm:flex sm:justify-end">
                    <Button type="button" variant="outline" @click="closeCertificationEdit">Batal</Button>
                    <Button type="submit" :disabled="certificationEditForm.processing">Simpan sertifikasi</Button>
                </div>
            </form>
        </FormModal>
    </div>
</template>
