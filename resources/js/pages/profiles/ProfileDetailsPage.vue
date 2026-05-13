<script setup lang="ts">
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import FormModal from '@/components/shared/FormModal.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { FileText, PencilLine, ShieldCheck } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

type AthleteProfile = {
    height_cm?: number;
    weight_kg?: number;
    geup?: string;
    nik?: string;
    bpjs?: string;
    nikHash?: string | null;
    bpjsHash?: string | null;
    phone?: string;
    bday?: string;
    gender?: string;
    alamat?: string;
    branch_id?: string | number | null;
    group_id?: string | number | null;
    branch?: { branch_name: string };
    group?: { group_name: string };
};

type SelectOption = {
    value: string | number;
    label: string;
};

type CoachProfile = {
    status?: string;
    specialization?: string;
    bio?: string;
};

type ParentProfile = {
    phone?: string;
    relation?: string;
    occupation?: string;
    notes?: string;
    athletes?: Array<{
        id: number;
        name: string;
        branch?: { branch_name: string };
        group?: { group_name: string };
    }>;
};

type Certification = {
    id: number;
    cert_type: string;
    title: string;
    issuer?: string;
    certified_at?: string;
    expires_at?: string;
    notes?: string;
    fileName?: string | null;
    fileUrl?: string | null;
};

type Achievement = {
    id: number;
    championship_name: string;
    medal: string;
    location?: string;
    event_date?: string;
    class_name?: string;
    division?: string;
    category?: string;
    notes?: string;
    fileName?: string | null;
    fileUrl?: string | null;
};

type User = {
    id: number;
    name: string;
    email: string;
    gender?: string;
    bday?: string;
    phone?: string;
    roles: string[];
    bio?: string;
    profilePictureUrl?: string | null;
    athleteProfile?: AthleteProfile | null;
    coachProfile?: CoachProfile | null;
    parentProfile?: ParentProfile | null;
    achievements: Achievement[];
    certifications: Certification[];
};

const selectedImage = ref<string | null>(null);
const selectedImageObjectUrl = ref<string | null>(null);
const cropperRef = ref<any>(null);
const profilePictureError = ref('');
const profilePictureReady = ref(false);
const profilePictureFileInput = ref<HTMLInputElement | null>(null);
const profilePictureWidth = 600;
const profilePictureHeight = 800;

const props = withDefaults(defineProps<{
    user: User;
    context?: 'admin' | 'settings';
    canEditAccount?: boolean;
    canEditRoleProfiles?: boolean;
    accountUpdateUrl?: string;
    profileUpdateUrl?: string;
    certificationStoreUrl?: string;
    achievementStoreUrl?: string;
    passwordUpdateUrl?: string | null;
    branches?: SelectOption[];
    groups?: SelectOption[];
}>(), {
    context: 'admin',
    canEditAccount: false,
    canEditRoleProfiles: true,
    branches: () => [],
    groups: () => [],
});

const isSettingsContext = computed(() => props.context === 'settings');

const breadcrumbs = computed<BreadcrumbItem[]>(() => isSettingsContext.value
    ? [
        { title: 'Profile settings', href: '/settings/profile' },
    ]
    : [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: props.user.name, href: `/users/${props.user.id}` },
    ]);

const pageShell = computed(() => isSettingsContext.value ? SettingsLayout : 'div');

const pageShellClass = computed(() => isSettingsContext.value
    ? ''
    : 'flex flex-1 flex-col gap-6 p-4 md:p-6');

const pageContentClass = computed(() => isSettingsContext.value
    ? 'flex flex-1 flex-col gap-6'
    : 'contents');

const profileUpdateUrl = computed(() => props.profileUpdateUrl ?? `/users/${props.user.id}/profile`);
const certificationStoreUrl = computed(() => props.certificationStoreUrl ?? `/users/${props.user.id}/certifications`);
const achievementStoreUrl = computed(() => props.achievementStoreUrl ?? `/users/${props.user.id}/achievements`);
const accountUpdateUrl = computed(() => props.accountUpdateUrl ?? '/settings/profile');
const canEditRoleProfiles = computed(() => props.canEditRoleProfiles);
const shouldShowMilestones = computed(() =>
    props.user.roles.includes('athlete') ||
    props.user.roles.includes('coach')
);

const canManageMilestones = computed(() => shouldShowMilestones.value);

const certificationUpdateUrl = (id: number | string) => props.context === 'settings'
    ? `/settings/profile/certifications/${id}`
    : `/users/${props.user.id}/certifications/${id}`;

const achievementUpdateUrl = (id: number | string) => props.context === 'settings'
    ? `/settings/profile/achievements/${id}`
    : `/users/${props.user.id}/achievements/${id}`;

const certColumns: TableColumn[] = [
    { key: 'cert_type', label: 'Type' },
    { key: 'title', label: 'Title' },
    { key: 'issuer', label: 'Issuer' },
    { key: 'certified_at', label: 'Certified' },
    { key: 'expires_at', label: 'Expires' },
    { key: 'notes', label: 'Notes' },
    { key: 'file_name', label: 'File' },
];

const achColumns: TableColumn[] = [
    { key: 'championship_name', label: 'Championship' },
    { key: 'medal', label: 'Medal' },
    { key: 'location', label: 'Location' },
    { key: 'event_date', label: 'Date' },
    { key: 'class_name', label: 'Class' },
    { key: 'division', label: 'Division' },
    { key: 'category', label: 'Category' },
    { key: 'notes', label: 'Notes' },
    { key: 'file_name', label: 'File' },
];

const certRows: TableRow[] = props.user.certifications.map((cert) => ({
    id: String(cert.id),
    cert_type: cert.cert_type,
    title: cert.title,
    issuer: cert.issuer ?? '-',
    certified_at: cert.certified_at ?? '-',
    expires_at: cert.expires_at ?? '-',
    notes: cert.notes ?? '-',
    file_name: cert.fileName ?? '-',
    file_url: cert.fileUrl ?? '',
}));

const achRows: TableRow[] = props.user.achievements.map((ach) => ({
    id: String(ach.id),
    championship_name: ach.championship_name,
    medal: ach.medal,
    location: ach.location ?? '-',
    event_date: ach.event_date ?? '-',
    class_name: ach.class_name ?? '-',
    division: ach.division ?? '-',
    category: ach.category ?? '-',
    notes: ach.notes ?? '-',
    file_name: ach.fileName ?? '-',
    file_url: ach.fileUrl ?? '',
}));

const certForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
    notes: '',
    file: null as File | null,
});

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

const editingCertification = ref<Certification | null>(null);
const editingAchievement = ref<Achievement | null>(null);

const certificationEditForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
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

const isEditingProfile = ref(false);
const isEditingAccount = ref(false);
const isEditingAthlete = ref(false);
const isEditingCoach = ref(false);
const isEditingParent = ref(false);
const isEditingPassword = ref(false);

const accountForm = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    gender: props.user.gender ?? 'MALE',
    bday: props.user.bday ?? '',
    phone: props.user.phone ?? '',
});

const profileForm = useForm({
    bio: props.user.bio ?? '',
    profile_picture: null as File | null,
});

const athleteForm = useForm({
    height_cm: String(props.user.athleteProfile?.height_cm ?? ''),
    weight_kg: String(props.user.athleteProfile?.weight_kg ?? ''),
    geup: props.user.athleteProfile?.geup ?? 'GEUP_10',
    gender: props.user.athleteProfile?.gender ?? 'MALE',
    bday: props.user.athleteProfile?.bday ?? '',
    phone: props.user.athleteProfile?.phone ?? '',
    nik: props.user.athleteProfile?.nik ?? '',
    bpjs: props.user.athleteProfile?.bpjs ?? '',
    alamat: props.user.athleteProfile?.alamat ?? '',
    branch_id: String(props.user.athleteProfile?.branch_id ?? props.branches[0]?.value ?? ''),
    group_id: String(props.user.athleteProfile?.group_id ?? props.groups[0]?.value ?? ''),
});

const coachForm = useForm({
    status: props.user.coachProfile?.status ?? 'active',
    specialization: props.user.coachProfile?.specialization ?? '',
    bio: props.user.coachProfile?.bio ?? '',
});

const parentForm = useForm({
    phone: props.user.parentProfile?.phone ?? '',
    relation: props.user.parentProfile?.relation ?? 'guardian',
    occupation: props.user.parentProfile?.occupation ?? '',
    notes: props.user.parentProfile?.notes ?? '',
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

function revokeSelectedImageObjectUrl() {
    if (selectedImageObjectUrl.value) {
        URL.revokeObjectURL(selectedImageObjectUrl.value);
        selectedImageObjectUrl.value = null;
    }
}

function clearSelectedImage() {
    revokeSelectedImageObjectUrl();
    selectedImage.value = null;
    profilePictureReady.value = false;
    profilePictureError.value = '';
    profileForm.profile_picture = null;

    if (profilePictureFileInput.value) {
        profilePictureFileInput.value.value = '';
    }
}

function cancelAccountEdit() {
    isEditingAccount.value = false;
    accountForm.reset();
}

function cancelProfileEdit() {
    isEditingProfile.value = false;
    profileForm.reset();
    clearSelectedImage();
}

function cancelAthleteEdit() {
    isEditingAthlete.value = false;
    athleteForm.reset();
}

function cancelCoachEdit() {
    isEditingCoach.value = false;
    coachForm.reset();
}

function cancelParentEdit() {
    isEditingParent.value = false;
    parentForm.reset();
}

function cancelPasswordEdit() {
    isEditingPassword.value = false;
    passwordForm.reset();
    passwordForm.clearErrors();
}

function saveAccountChanges() {
    accountForm.patch(accountUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAccount.value = false;
            window.location.reload();
        },
        onError: (errors) => {
            console.error('Account Form Errors:', errors);
            alert('Save failed! Check the console or the red text below inputs.');
        },
    });
}

function savePasswordChanges() {
    if (!props.passwordUpdateUrl) return;

    passwordForm.put(props.passwordUpdateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingPassword.value = false;
            passwordForm.reset();
        },
        onError: (errors) => {
            console.error('Password Form Errors:', errors);
        },
    });
}

async function saveProfileChanges() {
    if (selectedImage.value && !profileForm.profile_picture) {
        await applyCrop();

        if (!profileForm.profile_picture) {
            return;
        }
    }

    profileForm.post(profileUpdateUrl.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isEditingProfile.value = false;
            profileForm.reset();
            clearSelectedImage();
            window.location.reload();
        },
        onError: (errors) => {
            console.error('Profile Form Errors:', errors);
            alert('Save failed! Check the console or the red text below inputs.');
        },
    });
}

function saveAthleteChanges() {
    if (!canEditRoleProfiles.value) return;

    athleteForm.put(`/users/${props.user.id}/athlete-profile`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAthlete.value = false;
            window.location.reload();
        },
        onError: (errors) => {
            console.error('Athlete Form Errors:', errors);
            alert('Save failed! Check the console or the red text below inputs.');
        },
    });
}

function saveCoachChanges() {
    if (!canEditRoleProfiles.value) return;

    coachForm.put(`/users/${props.user.id}/coach-profile`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingCoach.value = false;
            window.location.reload();
        },
        onError: (errors) => {
            console.error('Coach Form Errors:', errors);
            alert('Save failed! Check the console or the red text below inputs.');
        },
    });
}

function saveParentChanges() {
    if (!canEditRoleProfiles.value) return;

    parentForm.put(`/users/${props.user.id}/parent-profile`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingParent.value = false;
            window.location.reload();
        },
        onError: (errors) => {
            console.error('Parent Form Errors:', errors);
            alert('Save failed! Check the console or the red text below inputs.');
        },
    });
}

function addCertification() {
    certForm.post(certificationStoreUrl.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            certForm.reset();
            certForm.cert_type = 'BELT';
            window.location.reload();
        },
        onError: (errors) => console.error('Cert Errors:', errors),
    });
}

function fileFromEvent(event: Event): File | null {
    return (event.target as HTMLInputElement).files?.[0] ?? null;
}

function onCertificationFileChange(event: Event) {
    certForm.file = fileFromEvent(event);
}

function onAchievementFileChange(event: Event) {
    achievementForm.file = fileFromEvent(event);
}

function onCertificationEditFileChange(event: Event) {
    certificationEditForm.file = fileFromEvent(event);
}

function onAchievementEditFileChange(event: Event) {
    achievementEditForm.file = fileFromEvent(event);
}

function openCertificationEdit(row: TableRow) {
    const certification = props.user.certifications.find((item) => String(item.id) === String(row.id));

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
        .post(certificationUpdateUrl(editingCertification.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeCertificationEdit();
                window.location.reload();
            },
            onFinish: () => {
                certificationEditForm.transform((data) => data);
            },
        });
}

function addAchievement() {
    achievementForm.post(achievementStoreUrl.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            achievementForm.reset();
            achievementForm.medal = 'NONE';
            window.location.reload();
        },
        onError: (errors) => console.error('Achievement Errors:', errors),
    });
}

function openAchievementEdit(row: TableRow) {
    const achievement = props.user.achievements.find((item) => String(item.id) === String(row.id));

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
        .post(achievementUpdateUrl(editingAchievement.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeAchievementEdit();
                window.location.reload();
            },
            onFinish: () => {
                achievementEditForm.transform((data) => data);
            },
        });
}

function shortHash(value?: string | null) {
    if (!value) return 'Not stored';
    if (value.length <= 20) return value;

    return `${value.slice(0, 12)}...${value.slice(-8)}`;
}

async function onProfilePictureChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    const maxSize = 2 * 1024 * 1024;

    if (file.size > maxSize) {
        profilePictureError.value = 'Profile picture must be smaller than 2MB.';
        target.value = '';
        return;
    }

    if (!file.type.startsWith('image/')) {
        profilePictureError.value = 'Selected file must be an image.';
        target.value = '';
        return;
    }

    profilePictureError.value = '';
    profilePictureReady.value = false;
    profileForm.profile_picture = null;

    revokeSelectedImageObjectUrl();
    selectedImageObjectUrl.value = URL.createObjectURL(file);
    selectedImage.value = selectedImageObjectUrl.value;
}

function editCurrentProfilePicture() {
    if (!props.user.profilePictureUrl) return;

    revokeSelectedImageObjectUrl();
    selectedImage.value = props.user.profilePictureUrl;
    profilePictureReady.value = false;
    profilePictureError.value = '';
    profileForm.profile_picture = null;
}

async function applyCrop() {
    const canvas = cropperRef.value?.getResult()?.canvas;

    if (!canvas) {
        profilePictureError.value = 'Move or zoom the image, then apply the 3x4 crop.';
        return;
    }

    const outputCanvas = document.createElement('canvas');
    outputCanvas.width = profilePictureWidth;
    outputCanvas.height = profilePictureHeight;

    const context = outputCanvas.getContext('2d');

    if (!context) {
        profilePictureError.value = 'Could not prepare the cropped image.';
        return;
    }

    context.drawImage(canvas, 0, 0, profilePictureWidth, profilePictureHeight);

    const croppedFile = await new Promise<File | null>((resolve) => {
        outputCanvas.toBlob((blob: Blob | null) => {
            if (!blob) {
                resolve(null);
                return;
            }

            resolve(
                new File([blob], 'profile-picture-3x4.jpg', {
                    type: 'image/jpeg',
                }),
            );
        }, 'image/jpeg', 0.9);
    });

    if (!croppedFile) {
        profilePictureError.value = 'Could not prepare the cropped image.';
        return;
    }

    profileForm.profile_picture = croppedFile;
    profilePictureReady.value = true;
    profilePictureError.value = '';
}

function zoomCrop(factor: number) {
    cropperRef.value?.zoom?.(factor);
    profilePictureReady.value = false;
    profileForm.profile_picture = null;
}

function rotateCrop() {
    cropperRef.value?.rotate?.(90);
    profilePictureReady.value = false;
    profileForm.profile_picture = null;
}

function resetCrop() {
    cropperRef.value?.reset?.();
    profilePictureReady.value = false;
    profileForm.profile_picture = null;
}

function markCropDirty() {
    profilePictureReady.value = false;
    profileForm.profile_picture = null;
}

onBeforeUnmount(() => {
    revokeSelectedImageObjectUrl();
});
</script>

<template>
    <Head :title="`${user.name} - Profile`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <component :is="pageShell" :class="pageShellClass">
            <div :key="$page.url" :class="pageContentClass">
                <PageSection :title="`${user.name}'s Profile`" :description="`View detailed information for ${user.email}`" />

                <div class="grid gap-6">
                    <div v-if="props.canEditAccount" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Account Details</h3>
                                <p class="text-sm text-muted-foreground">Update your basic account information.</p>
                            </div>
                            <Button v-if="!isEditingAccount" variant="outline" size="sm" class="w-full sm:w-auto" @click="isEditingAccount = true">Edit</Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveAccountChanges">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="account-name" v-model="accountForm.name" label="Name" :disabled="!isEditingAccount" :error="accountForm.errors.name" />
                                <FormInputField id="account-email" v-model="accountForm.email" label="Email" type="email" :disabled="!isEditingAccount" :error="accountForm.errors.email" />
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <FormSelectField id="account-gender" v-model="accountForm.gender" label="Gender" :disabled="!isEditingAccount" :options="[{ value: 'MALE', label: 'Male' }, { value: 'FEMALE', label: 'Female' }]" :error="accountForm.errors.gender" />
                                <FormInputField id="account-bday" v-model="accountForm.bday" label="Birth date" type="date" :disabled="!isEditingAccount" :error="accountForm.errors.bday" />
                                <FormInputField id="account-phone" v-model="accountForm.phone" label="Phone" :disabled="!isEditingAccount" :error="accountForm.errors.phone" />
                            </div>

                            <div v-if="isEditingAccount" class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="accountForm.processing" size="sm" class="w-full sm:w-auto">Save Changes</Button>
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="cancelAccountEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div v-if="props.passwordUpdateUrl" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Child Password</h3>
                                <p class="text-sm text-muted-foreground">Set a new login password for this child account.</p>
                            </div>
                            <Button v-if="!isEditingPassword" variant="outline" size="sm" class="w-full sm:w-auto" @click="isEditingPassword = true">Change Password</Button>
                        </div>

                        <form v-if="isEditingPassword" class="space-y-3" @submit.prevent="savePasswordChanges">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="child-password" v-model="passwordForm.password" label="New child password" type="password" :error="passwordForm.errors.password" />
                                <FormInputField id="child-password-confirmation" v-model="passwordForm.password_confirmation" label="Confirm new password" type="password" :error="passwordForm.errors.password_confirmation" />
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" size="sm" class="w-full sm:w-auto" :disabled="passwordForm.processing">Save Password</Button>
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="cancelPasswordEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div v-if="shouldShowMilestones" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="flex-shrink-0">
                                    <img
                                        v-if="user.profilePictureUrl"
                                        :src="user.profilePictureUrl"
                                        :alt="user.name"
                                        class="aspect-[3/4] w-20 rounded-md border border-border object-cover"
                                    />
                                    <div v-else class="flex aspect-[3/4] w-20 items-center justify-center rounded-md border border-border bg-muted text-2xl font-semibold text-muted-foreground">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold">{{ user.name }}</h3>
                                    <p class="truncate text-sm text-muted-foreground">{{ user.email }}</p>
                                    <p class="text-sm text-muted-foreground">Roles: {{ user.roles.join(', ') }}</p>
                                </div>
                            </div>
                            <Button v-if="!isEditingProfile" variant="outline" size="sm" class="w-full sm:w-auto" @click="isEditingProfile = true">Edit</Button>
                        </div>

                        <form class="space-y-4" @submit.prevent="saveProfileChanges">
                            <div class="grid gap-2">
                                <label for="bio" class="text-sm font-medium">Bio</label>
                                <textarea id="bio" v-model="profileForm.bio" :disabled="!isEditingProfile" rows="3" class="rounded-lg border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50" />
                                <p v-if="profileForm.errors.bio" class="text-sm text-red-500">{{ profileForm.errors.bio }}</p>
                            </div>
                            <div v-if="isEditingProfile" class="grid gap-2">
                                <label for="profile-picture" class="text-sm font-medium">Profile Picture</label>
                                <div class="flex flex-wrap gap-2">
                                    <input
                                        id="profile-picture"
                                        ref="profilePictureFileInput"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm sm:w-auto"
                                        @change="onProfilePictureChange"
                                    />
                                    <Button v-if="user.profilePictureUrl" type="button" variant="outline" size="sm" @click="editCurrentProfilePicture">
                                        Edit current picture
                                    </Button>
                                </div>

                                <p v-if="profilePictureError" class="text-sm text-destructive">
                                    {{ profilePictureError }}
                                </p>

                                <div v-if="selectedImage" class="mt-4 grid gap-3">
                                    <div class="overflow-hidden rounded-lg border border-border bg-muted">
                                        <Cropper
                                            ref="cropperRef"
                                            :src="selectedImage"
                                            :stencil-props="{
                                                aspectRatio: 3 / 4
                                            }"
                                            :canvas="{
                                                width: profilePictureWidth,
                                                height: profilePictureHeight
                                            }"
                                            class="h-72 w-full sm:h-96"
                                            image-restriction="stencil"
                                            @change="markCropDirty"
                                        />
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="zoomCrop(1.1)">Zoom in</Button>
                                        <Button type="button" variant="outline" size="sm" @click="zoomCrop(0.9)">Zoom out</Button>
                                        <Button type="button" variant="outline" size="sm" @click="rotateCrop">Rotate</Button>
                                        <Button type="button" variant="outline" size="sm" @click="resetCrop">Reset</Button>
                                        <Button type="button" size="sm" @click="applyCrop">Use 3x4 Crop</Button>
                                    </div>

                                    <p v-if="profilePictureReady" class="text-sm text-green-600">
                                        3x4 profile picture is ready to save.
                                    </p>
                                </div>
                            </div>
                            <div v-if="isEditingProfile" class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="profileForm.processing" size="sm" class="w-full sm:w-auto">Save Changes</Button>
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="cancelProfileEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div v-if="user.roles.includes('athlete')" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h4 class="flex items-center gap-2 font-semibold">
                                <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                                Athlete Details
                            </h4>
                            <Button v-if="canEditRoleProfiles && !isEditingAthlete" variant="outline" size="sm" class="w-full sm:w-auto" @click="isEditingAthlete = true">Edit</Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveAthleteChanges">
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField id="height" v-model="athleteForm.height_cm" label="Height (cm)" type="number" :disabled="!isEditingAthlete" :error="athleteForm.errors.height_cm" />
                                <FormInputField id="weight" v-model="athleteForm.weight_kg" label="Weight (kg)" type="number" :disabled="!isEditingAthlete" :error="athleteForm.errors.weight_kg" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormSelectField id="geup" v-model="athleteForm.geup" label="Geup" :disabled="!isEditingAthlete" :options="[{ value: 'GEUP_10', label: 'GEUP 10' }, { value: 'GEUP_9', label: 'GEUP 9' }, { value: 'GEUP_8', label: 'GEUP 8' }, { value: 'GEUP_7', label: 'GEUP 7' }, { value: 'GEUP_6', label: 'GEUP 6' }, { value: 'GEUP_5', label: 'GEUP 5' }, { value: 'GEUP_4', label: 'GEUP 4' }, { value: 'GEUP_3', label: 'GEUP 3' }, { value: 'GEUP_2', label: 'GEUP 2' }, { value: 'GEUP_1', label: 'GEUP 1' }, { value: 'DAN', label: 'DAN' }]" />
                                <FormSelectField id="gender" v-model="athleteForm.gender" label="Gender" :disabled="!isEditingAthlete" :options="[{ value: 'MALE', label: 'Male' }, { value: 'FEMALE', label: 'Female' }]" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormSelectField id="athlete-branch" v-model="athleteForm.branch_id" label="Branch" :disabled="!isEditingAthlete" :options="props.branches" :error="athleteForm.errors.branch_id" />
                                <FormSelectField id="athlete-group" v-model="athleteForm.group_id" label="Group" :disabled="!isEditingAthlete" :options="props.groups" :error="athleteForm.errors.group_id" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField id="bday" v-model="athleteForm.bday" label="Birthday" type="date" :disabled="!isEditingAthlete" :error="athleteForm.errors.bday" />
                                <FormInputField id="phone" v-model="athleteForm.phone" label="Phone" :disabled="!isEditingAthlete" :error="athleteForm.errors.phone" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField
                                    id="nik"
                                    v-model="athleteForm.nik"
                                    label="NIK"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.nik"
                                    :help="!athleteForm.nik && user.athleteProfile?.nikHash ? `Stored as hash only (${shortHash(user.athleteProfile?.nikHash)}). Re-enter once to display the real NIK here.` : undefined"
                                />
                                <FormInputField
                                    id="bpjs"
                                    v-model="athleteForm.bpjs"
                                    label="BPJS"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.bpjs"
                                    :help="!athleteForm.bpjs && user.athleteProfile?.bpjsHash ? `Stored as hash only (${shortHash(user.athleteProfile?.bpjsHash)}). Re-enter once to display the real BPJS here.` : undefined"
                                />
                            </div>
                            <FormInputField id="alamat" v-model="athleteForm.alamat" label="Address" :disabled="!isEditingAthlete" :error="athleteForm.errors.alamat" />

                            <div v-if="isEditingAthlete" class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="athleteForm.processing" size="sm" class="w-full sm:w-auto">Save Changes</Button>
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="cancelAthleteEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div v-if="user.roles.includes('coach')" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h4 class="font-semibold">Coach Details</h4>
                            <Button v-if="canEditRoleProfiles && !isEditingCoach" variant="outline" size="sm" class="w-full sm:w-auto" @click="isEditingCoach = true">Edit</Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveCoachChanges">
                            <FormSelectField id="coach-status" v-model="coachForm.status" label="Status" :disabled="!isEditingCoach" :options="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]" />
                            <FormInputField id="specialization" v-model="coachForm.specialization" label="Specialization" :disabled="!isEditingCoach" :error="coachForm.errors.specialization" />
                            <div class="grid gap-2">
                                <label for="coach-bio" class="text-sm font-medium">Coach Bio</label>
                                <textarea id="coach-bio" v-model="coachForm.bio" :disabled="!isEditingCoach" rows="3" class="rounded-lg border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50" />
                                <p v-if="coachForm.errors.bio" class="text-sm text-red-500">{{ coachForm.errors.bio }}</p>
                            </div>

                            <div v-if="isEditingCoach" class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="coachForm.processing" size="sm" class="w-full sm:w-auto">Save Changes</Button>
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="cancelCoachEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>

                    <div v-if="user.roles.includes('parent')" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5 lg:col-span-2">
                        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h4 class="font-semibold">Parent Details</h4>
                            <Button v-if="canEditRoleProfiles && !isEditingParent" variant="outline" size="sm" class="w-full sm:w-auto" @click="isEditingParent = true">Edit</Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveParentChanges">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="parent-phone" v-model="parentForm.phone" label="Phone" :disabled="!isEditingParent" :error="parentForm.errors.phone" />
                                <FormSelectField id="parent-relation" v-model="parentForm.relation" label="Relation" :disabled="!isEditingParent" :options="[{ value: 'father', label: 'Father' }, { value: 'mother', label: 'Mother' }, { value: 'guardian', label: 'Guardian' }]" />
                            </div>
                            <FormInputField id="parent-occupation" v-model="parentForm.occupation" label="Occupation" :disabled="!isEditingParent" :error="parentForm.errors.occupation" />
                            <div class="grid gap-2">
                                <label for="parent-notes" class="text-sm font-medium">Notes</label>
                                <textarea id="parent-notes" v-model="parentForm.notes" :disabled="!isEditingParent" rows="3" class="rounded-lg border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50" />
                            </div>

                            <div class="mt-4 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                                <span class="font-medium">Linked Athletes (Children):</span>
                                <ul v-if="user.parentProfile?.athletes?.length" class="ml-4 mt-1 list-disc text-muted-foreground">
                                    <li v-for="athlete in user.parentProfile?.athletes" :key="athlete.id">
                                        {{ athlete.name }} ({{ athlete.branch?.branch_name }} - {{ athlete.group?.group_name }})
                                    </li>
                                </ul>
                                <p v-else class="mt-1 text-muted-foreground italic">No children linked to this account yet.</p>
                            </div>

                            <div v-if="isEditingParent" class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="parentForm.processing" size="sm" class="w-full sm:w-auto">Save Changes</Button>
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="cancelParentEdit">Cancel</Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="shouldShowMilestones" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                    <h4 class="mb-3 flex items-center gap-2 font-semibold">
                        <FileText class="h-4 w-4 text-muted-foreground" />
                        Certifications
                    </h4>
                    <DataTable
                        title="Certifications"
                        description="View all certifications for this user."
                        :columns="certColumns"
                        :rows="certRows"
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
                        <template v-if="canManageMilestones" #row-actions="{ row }">
                            <Button type="button" variant="outline" size="sm" class="gap-2" @click="openCertificationEdit(row)">
                                <PencilLine class="h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </template>
                    </DataTable>
                    <div v-if="canManageMilestones" class="mt-6 border-t border-border pt-4">
                        <h5 class="mb-2 font-medium">Add Certification</h5>
                        <form class="grid gap-3" @submit.prevent="addCertification">
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormSelectField id="cert-type" v-model="certForm.cert_type" label="Type" :options="[{ value: 'BELT', label: 'Belt' }, { value: 'REFEREE', label: 'Referee' }, { value: 'TRAINER', label: 'Trainer' }]" />
                                <FormInputField id="cert-title" v-model="certForm.title" label="Title" required :error="certForm.errors.title" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField id="cert-issuer" v-model="certForm.issuer" label="Issuer" :error="certForm.errors.issuer" />
                                <FormInputField id="cert-date" v-model="certForm.certified_at" label="Certified at" type="date" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField id="cert-expires" v-model="certForm.expires_at" label="Expires at" type="date" />
                                <FormInputField id="cert-notes" v-model="certForm.notes" label="Notes" :error="certForm.errors.notes" />
                            </div>
                            <div class="grid gap-2">
                                <label for="cert-file" class="text-sm font-medium">Certificate File</label>
                                <input
                                    id="cert-file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                    @change="onCertificationFileChange"
                                />
                            </div>
                            <div>
                                <Button type="submit" :disabled="certForm.processing">Add Certification</Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="shouldShowMilestones" class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                    <h4 class="mb-3 flex items-center gap-2 font-semibold">
                        <FileText class="h-4 w-4 text-muted-foreground" />
                        Achievements
                    </h4>
                    <DataTable
                        title="Achievements"
                        description="View all achievements for this user."
                        :columns="achColumns"
                        :rows="achRows"
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
                        <template v-if="canManageMilestones" #row-actions="{ row }">
                            <Button type="button" variant="outline" size="sm" class="gap-2" @click="openAchievementEdit(row)">
                                <PencilLine class="h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </template>
                    </DataTable>
                    <div v-if="canManageMilestones" class="mt-6 border-t border-border pt-4">
                        <h5 class="mb-2 font-medium">Add Achievement</h5>
                        <form class="grid gap-3" @submit.prevent="addAchievement">
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField id="ach-name" v-model="achievementForm.championship_name" label="Championship name" required :error="achievementForm.errors.championship_name" />
                                <FormSelectField id="ach-medal" v-model="achievementForm.medal" label="Medal" :options="[{ value: 'GOLD', label: 'Gold' }, { value: 'SILVER', label: 'Silver' }, { value: 'BRONZE', label: 'Bronze' }, { value: 'NONE', label: 'None' }]" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField id="ach-location" v-model="achievementForm.location" label="Location" :error="achievementForm.errors.location" />
                                <FormInputField id="ach-date" v-model="achievementForm.event_date" label="Date" type="date" :error="achievementForm.errors.event_date" />
                            </div>
                            <div class="grid gap-2 md:grid-cols-3">
                                <FormInputField id="ach-class" v-model="achievementForm.class_name" label="Class name" />
                                <FormInputField id="ach-division" v-model="achievementForm.division" label="Division" />
                                <FormInputField id="ach-category" v-model="achievementForm.category" label="Category" />
                            </div>
                            <FormInputField id="ach-notes" v-model="achievementForm.notes" label="Notes" />
                            <div class="grid gap-2">
                                <label for="achievement-file" class="text-sm font-medium">Supporting File</label>
                                <input
                                    id="achievement-file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                    @change="onAchievementFileChange"
                                />
                            </div>
                            <div>
                                <Button type="submit" :disabled="achievementForm.processing">Add Achievement</Button>
                            </div>
                        </form>
                    </div>
                </div>

                <FormModal :open="Boolean(editingCertification)" max-width-class="max-w-4xl" @close="closeCertificationEdit">
                    <form class="grid gap-4" @submit.prevent="saveCertificationEdit">
                        <div>
                            <h3 class="text-lg font-semibold">Edit Certification</h3>
                            <p class="text-sm text-muted-foreground">Update the record details or replace the attached file.</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <FormSelectField id="cert-edit-type" v-model="certificationEditForm.cert_type" label="Type" :options="[{ value: 'BELT', label: 'Belt' }, { value: 'REFEREE', label: 'Referee' }, { value: 'TRAINER', label: 'Trainer' }]" :error="certificationEditForm.errors.cert_type" />
                            <FormInputField id="cert-edit-title" v-model="certificationEditForm.title" label="Title" required :error="certificationEditForm.errors.title" />
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <FormInputField id="cert-edit-issuer" v-model="certificationEditForm.issuer" label="Issuer" :error="certificationEditForm.errors.issuer" />
                            <FormInputField id="cert-edit-date" v-model="certificationEditForm.certified_at" label="Certified at" type="date" :error="certificationEditForm.errors.certified_at" />
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <FormInputField id="cert-edit-expires" v-model="certificationEditForm.expires_at" label="Expires at" type="date" :error="certificationEditForm.errors.expires_at" />
                            <FormInputField id="cert-edit-notes" v-model="certificationEditForm.notes" label="Notes" :error="certificationEditForm.errors.notes" />
                        </div>
                        <div class="grid gap-2">
                            <label for="cert-edit-file" class="text-sm font-medium">Replace Certificate File</label>
                            <a v-if="editingCertification?.fileUrl" :href="editingCertification.fileUrl" target="_blank" class="text-sm font-medium underline underline-offset-4">
                                Current file: {{ editingCertification.fileName ?? 'Open file' }}
                            </a>
                            <input
                                id="cert-edit-file"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                @change="onCertificationEditFileChange"
                            />
                            <p v-if="certificationEditForm.errors.file" class="text-sm text-destructive">{{ certificationEditForm.errors.file }}</p>
                        </div>

                        <div class="flex flex-col justify-end gap-2 sm:flex-row">
                            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="closeCertificationEdit">Cancel</Button>
                            <Button type="submit" class="w-full sm:w-auto" :disabled="certificationEditForm.processing">Save Certification</Button>
                        </div>
                    </form>
                </FormModal>

                <FormModal :open="Boolean(editingAchievement)" max-width-class="max-w-4xl" @close="closeAchievementEdit">
                    <form class="grid gap-4" @submit.prevent="saveAchievementEdit">
                        <div>
                            <h3 class="text-lg font-semibold">Edit Achievement</h3>
                            <p class="text-sm text-muted-foreground">Update the achievement details or replace the supporting file.</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <FormInputField id="ach-edit-name" v-model="achievementEditForm.championship_name" label="Championship name" required :error="achievementEditForm.errors.championship_name" />
                            <FormSelectField id="ach-edit-medal" v-model="achievementEditForm.medal" label="Medal" :options="[{ value: 'GOLD', label: 'Gold' }, { value: 'SILVER', label: 'Silver' }, { value: 'BRONZE', label: 'Bronze' }, { value: 'NONE', label: 'None' }]" :error="achievementEditForm.errors.medal" />
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <FormInputField id="ach-edit-location" v-model="achievementEditForm.location" label="Location" :error="achievementEditForm.errors.location" />
                            <FormInputField id="ach-edit-date" v-model="achievementEditForm.event_date" label="Date" type="date" :error="achievementEditForm.errors.event_date" />
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <FormInputField id="ach-edit-class" v-model="achievementEditForm.class_name" label="Class name" :error="achievementEditForm.errors.class_name" />
                            <FormInputField id="ach-edit-division" v-model="achievementEditForm.division" label="Division" :error="achievementEditForm.errors.division" />
                            <FormInputField id="ach-edit-category" v-model="achievementEditForm.category" label="Category" :error="achievementEditForm.errors.category" />
                        </div>
                        <FormInputField id="ach-edit-notes" v-model="achievementEditForm.notes" label="Notes" :error="achievementEditForm.errors.notes" />
                        <div class="grid gap-2">
                            <label for="achievement-edit-file" class="text-sm font-medium">Replace Supporting File</label>
                            <a v-if="editingAchievement?.fileUrl" :href="editingAchievement.fileUrl" target="_blank" class="text-sm font-medium underline underline-offset-4">
                                Current file: {{ editingAchievement.fileName ?? 'Open file' }}
                            </a>
                            <input
                                id="achievement-edit-file"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                @change="onAchievementEditFileChange"
                            />
                            <p v-if="achievementEditForm.errors.file" class="text-sm text-destructive">{{ achievementEditForm.errors.file }}</p>
                        </div>

                        <div class="flex flex-col justify-end gap-2 sm:flex-row">
                            <Button type="button" variant="outline" class="w-full sm:w-auto" @click="closeAchievementEdit">Cancel</Button>
                            <Button type="submit" class="w-full sm:w-auto" :disabled="achievementEditForm.processing">Save Achievement</Button>
                        </div>
                    </form>
                </FormModal>
            </div>
        </component>
    </AppLayout>
</template>
