<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { useProfilePictureCropper } from '@/composables/useProfilePictureCropper';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import ProfileAchievementsSection from '@/pages/profiles/components/ProfileAchievementsSection.vue';
import ProfileCertificationsSection from '@/pages/profiles/components/ProfileCertificationsSection.vue';
import ProfileRoleDetailsSection from '@/pages/profiles/components/ProfileRoleDetailsSection.vue';
import ProfileSaveErrorAlert from '@/pages/profiles/components/ProfileSaveErrorAlert.vue';
import { useProfileRoutes } from '@/pages/profiles/composables/useProfileRoutes';
import { coachStatusOptions, genderOptions, geupOptions, parentRelationOptions } from '@/pages/profiles/profileOptions';
import type { ProfileSelectOption, ProfileUser } from '@/pages/profiles/types';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { index as usersIndex, show as userShow } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import 'vue-advanced-cropper/dist/style.css';

const props = withDefaults(
    defineProps<{
        user: ProfileUser;
        context?: 'admin' | 'settings';
        canEditAccount?: boolean;
        canEditRoleProfiles?: boolean;
        accountUpdateUrl?: string;
        profileUpdateUrl?: string;
        certificationStoreUrl?: string;
        achievementStoreUrl?: string;
        passwordUpdateUrl?: string | null;
        branches?: ProfileSelectOption[];
        groups?: ProfileSelectOption[];
    }>(),
    {
        context: 'admin',
        canEditAccount: false,
        canEditRoleProfiles: true,
        branches: () => [],
        groups: () => [],
    },
);

const {
    isSettingsContext,
    accountUpdateUrl,
    profileUpdateUrl,
    certificationStoreUrl,
    achievementStoreUrl,
    certificationUpdateUrl,
    achievementUpdateUrl,
    athleteProfileUpdateUrl,
    coachProfileUpdateUrl,
    parentProfileUpdateUrl,
} = useProfileRoutes({
    user: props.user,
    context: props.context,
    accountUpdateUrl: props.accountUpdateUrl,
    profileUpdateUrl: props.profileUpdateUrl,
    certificationStoreUrl: props.certificationStoreUrl,
    achievementStoreUrl: props.achievementStoreUrl,
});

const breadcrumbs = computed<BreadcrumbItem[]>(() =>
    isSettingsContext.value
        ? [{ title: 'Profile settings', href: profileEdit.url() }]
        : [
              { title: 'Dashboard', href: dashboard.url() },
              { title: 'Users', href: usersIndex.url() },
              { title: props.user.name, href: userShow.url(props.user.id) },
          ],
);

const pageShell = computed(() => (isSettingsContext.value ? SettingsLayout : 'div'));
const pageShellClass = computed(() => (isSettingsContext.value ? '' : 'flex flex-1 flex-col gap-6 p-4 md:p-6'));
const pageContentClass = computed(() => (isSettingsContext.value ? 'flex flex-1 flex-col gap-6' : 'contents'));
const canEditRoleProfiles = computed(() => props.canEditRoleProfiles);
const isAthlete = computed(() => props.user.roles.includes('athlete'));
const isCoach = computed(() => props.user.roles.includes('coach'));
const isParent = computed(() => props.user.roles.includes('parent'));
const hasRoleDetails = computed(() => isAthlete.value || isCoach.value || isParent.value);
const shouldShowMilestones = computed(() => isAthlete.value || isCoach.value);
const canManageMilestones = computed(() => shouldShowMilestones.value);

const isEditingProfile = ref(false);
const isEditingAccount = ref(false);
const isEditingAthlete = ref(false);
const isEditingCoach = ref(false);
const isEditingParent = ref(false);
const isEditingPassword = ref(false);
const saveError = ref<{
    title: string;
    message: string;
    fields: string[];
} | null>(null);

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

const {
    selectedImage,
    cropperRef,
    profilePictureError,
    profilePictureReady,
    profilePictureFileInput,
    profilePictureWidth,
    profilePictureHeight,
    clearSelectedImage,
    onProfilePictureChange,
    editCurrentProfilePicture,
    applyCrop,
    zoomCrop,
    rotateCrop,
    resetCrop,
    markCropDirty,
} = useProfilePictureCropper({
    onCroppedFileChange: (file) => {
        profileForm.profile_picture = file;
    },
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

function clearSaveError() {
    saveError.value = null;
}

function showSaveError(section: string, errors: Record<string, string>) {
    const fields = Array.from(new Set(Object.values(errors).filter(Boolean)));

    saveError.value = {
        title: `${section} could not be saved`,
        message: fields.length > 0 ? 'Review the highlighted fields and try again.' : 'The request failed. Please try again.',
        fields,
    };
}

function saveAccountChanges() {
    accountForm.patch(accountUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAccount.value = false;
            clearSaveError();
        },
        onError: (errors) => showSaveError('Account details', errors),
    });
}

function savePasswordChanges() {
    if (!props.passwordUpdateUrl) return;

    passwordForm.put(props.passwordUpdateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingPassword.value = false;
            passwordForm.reset();
            clearSaveError();
        },
        onError: (errors) => showSaveError('Password', errors),
    });
}

async function saveProfileChanges() {
    if (selectedImage.value && !profileForm.profile_picture) {
        await applyCrop();
        if (!profileForm.profile_picture) return;
    }

    profileForm.post(profileUpdateUrl.value, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isEditingProfile.value = false;
            profileForm.reset();
            clearSelectedImage();
            clearSaveError();
        },
        onError: (errors) => showSaveError('Profile details', errors),
    });
}

function saveAthleteChanges() {
    if (!canEditRoleProfiles.value) return;

    athleteForm.put(athleteProfileUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAthlete.value = false;
            clearSaveError();
        },
        onError: (errors) => showSaveError('Athlete details', errors),
    });
}

function saveCoachChanges() {
    if (!canEditRoleProfiles.value) return;

    coachForm.put(coachProfileUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingCoach.value = false;
            clearSaveError();
        },
        onError: (errors) => showSaveError('Coach details', errors),
    });
}

function saveParentChanges() {
    if (!canEditRoleProfiles.value) return;

    parentForm.put(parentProfileUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingParent.value = false;
            clearSaveError();
        },
        onError: (errors) => showSaveError('Parent details', errors),
    });
}

function editDisplayedProfilePicture() {
    editCurrentProfilePicture(props.user.profilePictureUrl);
}

function shortHash(value?: string | null) {
    if (!value) return 'Not stored';
    if (value.length <= 20) return value;
    return `${value.slice(0, 12)}...${value.slice(-8)}`;
}
</script>

<template>
    <Head :title="`${user.name} - Profile`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <component :is="pageShell" :class="pageShellClass">
            <div :key="$page.url" :class="pageContentClass">
                <PageSection
                    :title="`${user.name}'s Profile`"
                    :description="`View detailed information for ${user.email}`"
                />

                <ProfileSaveErrorAlert
                    v-if="saveError"
                    :title="saveError.title"
                    :message="saveError.message"
                    :fields="saveError.fields"
                    @clear="clearSaveError"
                />

                <div class="grid gap-6">
                    <div
                        v-if="props.canEditAccount && !isAthlete"
                        class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Account Details</h3>
                                <p class="text-sm text-muted-foreground">Update your basic account information.</p>
                            </div>
                            <Button
                                v-if="!isEditingAccount"
                                variant="outline"
                                size="sm"
                                class="w-full sm:w-auto"
                                @click="isEditingAccount = true"
                            >
                                Edit
                            </Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveAccountChanges">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField
                                    id="account-name"
                                    v-model="accountForm.name"
                                    label="Name"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.name"
                                />
                                <FormInputField
                                    id="account-email"
                                    v-model="accountForm.email"
                                    label="Email"
                                    type="email"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.email"
                                />
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <FormSelectField
                                    id="account-gender"
                                    v-model="accountForm.gender"
                                    label="Gender"
                                    :disabled="!isEditingAccount"
                                    :options="genderOptions"
                                    :error="accountForm.errors.gender"
                                />
                                <FormInputField
                                    id="account-bday"
                                    v-model="accountForm.bday"
                                    label="Birth date"
                                    type="date"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.bday"
                                />
                                <FormInputField
                                    id="account-phone"
                                    v-model="accountForm.phone"
                                    label="Phone"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.phone"
                                />
                            </div>

                            <div v-if="isEditingAccount" class="mt-4 flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="accountForm.processing" size="sm" class="w-full sm:w-auto">
                                    Save Changes
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    @click="cancelAccountEdit"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div
                        v-if="props.passwordUpdateUrl"
                        class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Child Password</h3>
                                <p class="text-sm text-muted-foreground">Set a new login password for this child account.</p>
                            </div>
                            <Button
                                v-if="!isEditingPassword"
                                variant="outline"
                                size="sm"
                                class="w-full sm:w-auto"
                                @click="isEditingPassword = true"
                            >
                                Change Password
                            </Button>
                        </div>

                        <form v-if="isEditingPassword" class="space-y-3" @submit.prevent="savePasswordChanges">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField
                                    id="child-password"
                                    v-model="passwordForm.password"
                                    label="New child password"
                                    type="password"
                                    :error="passwordForm.errors.password"
                                />
                                <FormInputField
                                    id="child-password-confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    label="Confirm new password"
                                    type="password"
                                    :error="passwordForm.errors.password_confirmation"
                                />
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" size="sm" class="w-full sm:w-auto" :disabled="passwordForm.processing">
                                    Save Password
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    @click="cancelPasswordEdit"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div
                        v-if="shouldShowMilestones"
                        class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="flex-shrink-0">
                                    <img
                                        v-if="user.profilePictureUrl"
                                        :src="user.profilePictureUrl"
                                        :alt="user.name"
                                        class="aspect-[3/4] w-20 rounded-md border border-border object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex aspect-[3/4] w-20 items-center justify-center rounded-md border border-border bg-muted text-2xl font-semibold text-muted-foreground"
                                    >
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold">{{ user.name }}</h3>
                                    <p class="truncate text-sm text-muted-foreground">{{ user.email }}</p>
                                    <p class="text-sm text-muted-foreground">Roles: {{ user.roles.join(', ') }}</p>
                                </div>
                            </div>
                            <Button
                                v-if="!isEditingProfile"
                                variant="outline"
                                size="sm"
                                class="w-full sm:w-auto"
                                @click="isEditingProfile = true"
                            >
                                Edit
                            </Button>
                        </div>

                        <form class="space-y-4" @submit.prevent="saveProfileChanges">
                            <div class="grid gap-2">
                                <label for="bio" class="text-sm font-medium">Bio</label>
                                <textarea
                                    id="bio"
                                    v-model="profileForm.bio"
                                    :disabled="!isEditingProfile"
                                    rows="3"
                                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <p v-if="profileForm.errors.bio" class="text-sm text-brand-coral">
                                    {{ profileForm.errors.bio }}
                                </p>
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
                                    <Button
                                        v-if="user.profilePictureUrl"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="editDisplayedProfilePicture"
                                    >
                                        Edit current picture
                                    </Button>
                                </div>

                                <p v-if="profilePictureError" class="text-sm text-destructive">{{ profilePictureError }}</p>

                                <div v-if="selectedImage" class="mt-4 grid gap-3">
                                    <div class="overflow-hidden rounded-lg border border-border bg-muted">
                                        <Cropper
                                            ref="cropperRef"
                                            :src="selectedImage"
                                            :stencil-props="{ aspectRatio: 3 / 4 }"
                                            :canvas="{ width: profilePictureWidth, height: profilePictureHeight }"
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

                                    <p v-if="profilePictureReady" class="text-sm text-brand-lime">
                                        3x4 profile picture is ready to save.
                                    </p>
                                </div>
                            </div>
                            <div v-if="isEditingProfile" class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" :disabled="profileForm.processing" size="sm" class="w-full sm:w-auto">
                                    Save Changes
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="w-full sm:w-auto"
                                    @click="cancelProfileEdit"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>

                    <div
                        v-if="hasRoleDetails"
                        class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5"
                    >
                        <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold">
                            <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                            Role Details
                        </h3>

                        <ProfileRoleDetailsSection
                            v-if="isAthlete && props.canEditAccount"
                            title="Account information"
                            description="Basic account and contact information for this athlete."
                            :can-edit="props.canEditAccount"
                            :editing="isEditingAccount"
                            :processing="accountForm.processing"
                            save-label="Save Account"
                            @edit="isEditingAccount = true"
                            @save="saveAccountChanges"
                            @cancel="cancelAccountEdit"
                        >
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField
                                    id="athlete-account-name"
                                    v-model="accountForm.name"
                                    label="Name"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.name"
                                />
                                <FormInputField
                                    id="athlete-account-email"
                                    v-model="accountForm.email"
                                    label="Email"
                                    type="email"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.email"
                                />
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <FormSelectField
                                    id="athlete-account-gender"
                                    v-model="accountForm.gender"
                                    label="Gender"
                                    :disabled="!isEditingAccount"
                                    :options="genderOptions"
                                    :error="accountForm.errors.gender"
                                />
                                <FormInputField
                                    id="athlete-account-bday"
                                    v-model="accountForm.bday"
                                    label="Birth date"
                                    type="date"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.bday"
                                />
                                <FormInputField
                                    id="athlete-account-phone"
                                    v-model="accountForm.phone"
                                    label="Phone"
                                    :disabled="!isEditingAccount"
                                    :error="accountForm.errors.phone"
                                />
                            </div>
                        </ProfileRoleDetailsSection>

                        <ProfileRoleDetailsSection
                            v-if="isAthlete"
                            title="Athlete profile"
                            description="Training, membership, and identity information."
                            :can-edit="canEditRoleProfiles"
                            :editing="isEditingAthlete"
                            :processing="athleteForm.processing"
                            :divided="props.canEditAccount"
                            save-label="Save Athlete Profile"
                            @edit="isEditingAthlete = true"
                            @save="saveAthleteChanges"
                            @cancel="cancelAthleteEdit"
                        >
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField
                                    id="height"
                                    v-model="athleteForm.height_cm"
                                    label="Height (cm)"
                                    type="number"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.height_cm"
                                />
                                <FormInputField
                                    id="weight"
                                    v-model="athleteForm.weight_kg"
                                    label="Weight (kg)"
                                    type="number"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.weight_kg"
                                />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormSelectField
                                    id="geup"
                                    v-model="athleteForm.geup"
                                    label="Geup"
                                    :disabled="!isEditingAthlete"
                                    :options="geupOptions"
                                />
                                <FormSelectField
                                    id="gender"
                                    v-model="athleteForm.gender"
                                    label="Gender"
                                    :disabled="!isEditingAthlete"
                                    :options="genderOptions"
                                />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormSelectField
                                    id="athlete-branch"
                                    v-model="athleteForm.branch_id"
                                    label="Branch"
                                    :disabled="!isEditingAthlete"
                                    :options="props.branches"
                                    :error="athleteForm.errors.branch_id"
                                />
                                <FormSelectField
                                    id="athlete-group"
                                    v-model="athleteForm.group_id"
                                    label="Group"
                                    :disabled="!isEditingAthlete"
                                    :options="props.groups"
                                    :error="athleteForm.errors.group_id"
                                />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField
                                    id="bday"
                                    v-model="athleteForm.bday"
                                    label="Birthday"
                                    type="date"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.bday"
                                />
                                <FormInputField
                                    id="phone"
                                    v-model="athleteForm.phone"
                                    label="Phone"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.phone"
                                />
                            </div>
                            <div class="grid gap-2 md:grid-cols-2">
                                <FormInputField
                                    id="nik"
                                    v-model="athleteForm.nik"
                                    label="NIK"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.nik"
                                    :help="
                                        !athleteForm.nik && user.athleteProfile?.nikHash
                                            ? `Stored as hash only (${shortHash(user.athleteProfile?.nikHash)}). Re-enter once to display the real NIK here.`
                                            : undefined
                                    "
                                />
                                <FormInputField
                                    id="bpjs"
                                    v-model="athleteForm.bpjs"
                                    label="BPJS"
                                    :disabled="!isEditingAthlete"
                                    :error="athleteForm.errors.bpjs"
                                    :help="
                                        !athleteForm.bpjs && user.athleteProfile?.bpjsHash
                                            ? `Stored as hash only (${shortHash(user.athleteProfile?.bpjsHash)}). Re-enter once to display the real BPJS here.`
                                            : undefined
                                    "
                                />
                            </div>
                            <FormInputField
                                id="alamat"
                                v-model="athleteForm.alamat"
                                label="Address"
                                :disabled="!isEditingAthlete"
                                :error="athleteForm.errors.alamat"
                            />
                        </ProfileRoleDetailsSection>

                        <ProfileRoleDetailsSection
                            v-if="isCoach"
                            title="Coach profile"
                            description="Coaching status, specialization, and biography."
                            :can-edit="canEditRoleProfiles"
                            :editing="isEditingCoach"
                            :processing="coachForm.processing"
                            :divided="isAthlete"
                            @edit="isEditingCoach = true"
                            @save="saveCoachChanges"
                            @cancel="cancelCoachEdit"
                        >
                            <FormSelectField
                                id="coach-status"
                                v-model="coachForm.status"
                                label="Status"
                                :disabled="!isEditingCoach"
                                :options="coachStatusOptions"
                            />
                            <FormInputField
                                id="specialization"
                                v-model="coachForm.specialization"
                                label="Specialization"
                                :disabled="!isEditingCoach"
                                :error="coachForm.errors.specialization"
                            />
                            <div class="grid gap-2">
                                <label for="coach-bio" class="text-sm font-medium">Coach Bio</label>
                                <textarea
                                    id="coach-bio"
                                    v-model="coachForm.bio"
                                    :disabled="!isEditingCoach"
                                    rows="3"
                                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <p v-if="coachForm.errors.bio" class="text-sm text-brand-coral">{{ coachForm.errors.bio }}</p>
                            </div>
                        </ProfileRoleDetailsSection>

                        <ProfileRoleDetailsSection
                            v-if="isParent"
                            title="Parent profile"
                            description="Guardian contact details and linked athlete information."
                            :can-edit="canEditRoleProfiles"
                            :editing="isEditingParent"
                            :processing="parentForm.processing"
                            :divided="isAthlete || isCoach"
                            @edit="isEditingParent = true"
                            @save="saveParentChanges"
                            @cancel="cancelParentEdit"
                        >
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField
                                    id="parent-phone"
                                    v-model="parentForm.phone"
                                    label="Phone"
                                    :disabled="!isEditingParent"
                                    :error="parentForm.errors.phone"
                                />
                                <FormSelectField
                                    id="parent-relation"
                                    v-model="parentForm.relation"
                                    label="Relation"
                                    :disabled="!isEditingParent"
                                    :options="parentRelationOptions"
                                />
                            </div>
                            <FormInputField
                                id="parent-occupation"
                                v-model="parentForm.occupation"
                                label="Occupation"
                                :disabled="!isEditingParent"
                                :error="parentForm.errors.occupation"
                            />
                            <div class="grid gap-2">
                                <label for="parent-notes" class="text-sm font-medium">Notes</label>
                                <textarea
                                    id="parent-notes"
                                    v-model="parentForm.notes"
                                    :disabled="!isEditingParent"
                                    rows="3"
                                    class="rounded-lg border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                />
                            </div>

                            <div class="mt-4 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                                <span class="font-medium">Linked Athletes (Children):</span>
                                <ul
                                    v-if="user.parentProfile?.athletes?.length"
                                    class="mt-1 ml-4 list-disc text-muted-foreground"
                                >
                                    <li v-for="athlete in user.parentProfile?.athletes" :key="athlete.id">
                                        {{ athlete.name }} ({{ athlete.branch?.branch_name }} - {{ athlete.group?.group_name }})
                                    </li>
                                </ul>
                                <p v-else class="mt-1 text-muted-foreground italic">No children linked to this account yet.</p>
                            </div>
                        </ProfileRoleDetailsSection>
                    </div>
                </div>

                <ProfileCertificationsSection
                    v-if="shouldShowMilestones"
                    :certifications="user.certifications"
                    :can-manage="canManageMilestones"
                    :store-url="certificationStoreUrl"
                    :update-url="certificationUpdateUrl"
                />

                <ProfileAchievementsSection
                    v-if="shouldShowMilestones"
                    :achievements="user.achievements"
                    :can-manage="canManageMilestones"
                    :store-url="achievementStoreUrl"
                    :update-url="achievementUpdateUrl"
                />
            </div>
        </component>
    </AppLayout>
</template>
