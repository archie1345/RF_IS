<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileText, PencilLine } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FileUploadField from '@/components/forms/FileUploadField.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import { Button } from '@/components/ui/button';
import { certificationTypeOptions, documentFileAccept } from '@/pages/profiles/profileOptions';
import { certificationColumns, certificationRows } from '@/pages/profiles/profileTables';
import type { ProfileCertification } from '@/pages/profiles/types';
import type { TableRow } from '@/types/management';

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

function addCertification() {
    certForm.post(props.storeUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            certForm.reset();
            certForm.cert_type = 'BELT';
        },
        onError: (errors) => console.error('Cert Errors:', errors),
    });
}

function openCertificationEdit(row: TableRow) {
    const certification = props.certifications.find((item) => String(item.id) === String(row.id));

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

function closeCertificationEdit() {
    editingCertification.value = null;
    certificationEditForm.reset();
    certificationEditForm.clearErrors();
}

function saveCertificationEdit() {
    if (!editingCertification.value) return;

    certificationEditForm
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(props.updateUrl(editingCertification.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeCertificationEdit();
            },
            onFinish: () => {
                certificationEditForm.transform((data) => data);
            },
        });
}
</script>

<template>
    <div class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
        <h4 class="mb-3 flex items-center gap-2 font-semibold">
            <FileText class="h-4 w-4 text-muted-foreground" />
            Certifications
        </h4>
        <DataTable
            title="Certifications"
            description="View all certifications for this user."
            :columns="certificationColumns"
            :rows="rows"
            action-label="Manage"
            empty-text="No certifications found."
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
                <Button type="button" variant="outline" size="sm" class="gap-2" @click="openCertificationEdit(row)">
                    <PencilLine class="h-3.5 w-3.5" />
                    Edit
                </Button>
            </template>
        </DataTable>
        <div v-if="props.canManage" class="mt-6 border-t border-border pt-4">
            <h5 class="mb-2 font-medium">Add Certification</h5>
            <form class="grid gap-3" @submit.prevent="addCertification">
                <div class="grid gap-2 md:grid-cols-2">
                    <FormSelectField
                        id="cert-type"
                        v-model="certForm.cert_type"
                        label="Type"
                        :options="certificationTypeOptions"
                    />
                    <FormInputField
                        id="cert-title"
                        v-model="certForm.title"
                        label="Title"
                        required
                        :error="certForm.errors.title"
                    />
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <FormInputField
                        id="cert-issuer"
                        v-model="certForm.issuer"
                        label="Issuer"
                        :error="certForm.errors.issuer"
                    />
                    <FormInputField id="cert-date" v-model="certForm.certified_at" label="Certified at" type="date" />
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <FormInputField id="cert-expires" v-model="certForm.expires_at" label="Expires at" type="date" />
                    <FormInputField
                        id="cert-notes"
                        v-model="certForm.notes"
                        label="Notes"
                        :error="certForm.errors.notes"
                    />
                </div>
                <FileUploadField
                    id="cert-file"
                    v-model="certForm.file"
                    label="Certificate File"
                    :accept="documentFileAccept"
                    :error="certForm.errors.file"
                />
                <div>
                    <Button type="submit" :disabled="certForm.processing">Add Certification</Button>
                </div>
            </form>
        </div>

        <FormModal :open="Boolean(editingCertification)" max-width-class="max-w-4xl" @close="closeCertificationEdit">
            <form class="grid gap-4" @submit.prevent="saveCertificationEdit">
                <div>
                    <h3 class="text-lg font-semibold">Edit Certification</h3>
                    <p class="text-sm text-muted-foreground">Update the record details or replace the attached file.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <FormSelectField
                        id="cert-edit-type"
                        v-model="certificationEditForm.cert_type"
                        label="Type"
                        :options="certificationTypeOptions"
                        :error="certificationEditForm.errors.cert_type"
                    />
                    <FormInputField
                        id="cert-edit-title"
                        v-model="certificationEditForm.title"
                        label="Title"
                        required
                        :error="certificationEditForm.errors.title"
                    />
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <FormInputField
                        id="cert-edit-issuer"
                        v-model="certificationEditForm.issuer"
                        label="Issuer"
                        :error="certificationEditForm.errors.issuer"
                    />
                    <FormInputField
                        id="cert-edit-date"
                        v-model="certificationEditForm.certified_at"
                        label="Certified at"
                        type="date"
                        :error="certificationEditForm.errors.certified_at"
                    />
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <FormInputField
                        id="cert-edit-expires"
                        v-model="certificationEditForm.expires_at"
                        label="Expires at"
                        type="date"
                        :error="certificationEditForm.errors.expires_at"
                    />
                    <FormInputField
                        id="cert-edit-notes"
                        v-model="certificationEditForm.notes"
                        label="Notes"
                        :error="certificationEditForm.errors.notes"
                    />
                </div>
                <FileUploadField
                    id="cert-edit-file"
                    v-model="certificationEditForm.file"
                    label="Replace Certificate File"
                    :accept="documentFileAccept"
                    :error="certificationEditForm.errors.file"
                    :current-file-name="editingCertification?.fileName"
                    :current-file-url="editingCertification?.fileUrl"
                />

                <div class="flex flex-col justify-end gap-2 sm:flex-row">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" @click="closeCertificationEdit"
                        >Cancel</Button
                    >
                    <Button type="submit" class="w-full sm:w-auto" :disabled="certificationEditForm.processing"
                        >Save Certification</Button
                    >
                </div>
            </form>
        </FormModal>
    </div>
</template>
