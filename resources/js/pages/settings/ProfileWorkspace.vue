<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Camera,
    CheckCircle2,
    Dumbbell,
    Mail,
    MapPin,
    PencilLine,
    Phone,
    RotateCw,
    Ruler,
    Scale,
    ShieldCheck,
    UserRound,
    UsersRound,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import { Button } from '@/components/ui/button';
import { useAppPopup } from '@/composables/useAppPopup';
import { useProfilePictureCropper } from '@/composables/useProfilePictureCropper';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import ProfileAchievementsSection from '@/pages/profiles/components/ProfileAchievementsSection.vue';
import ProfileCertificationsSection from '@/pages/profiles/components/ProfileCertificationsSection.vue';
import { useProfileRoutes } from '@/pages/profiles/composables/useProfileRoutes';
import { coachStatusOptions, genderOptions, geupOptions, parentRelationOptions } from '@/pages/profiles/profileOptions';
import type { ProfileSelectOption, ProfileUser } from '@/pages/profiles/types';
import type { BreadcrumbItem } from '@/types';
import 'vue-advanced-cropper/dist/style.css';

const props = withDefaults(
    defineProps<{
        user: ProfileUser;
        mustVerifyEmail?: boolean;
        status?: string | null;
        accountUpdateUrl: string;
        profileUpdateUrl: string;
        certificationStoreUrl: string;
        achievementStoreUrl: string;
        branches?: ProfileSelectOption[];
        groups?: ProfileSelectOption[];
    }>(),
    {
        mustVerifyEmail: false,
        status: null,
        branches: () => [],
        groups: () => [],
    },
);

const popup = useAppPopup();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pengaturan', href: '/settings' },
    { title: 'Profil', href: '/settings/profile' },
];

const {
    certificationUpdateUrl,
    achievementUpdateUrl,
    athleteProfileUpdateUrl,
    coachProfileUpdateUrl,
    parentProfileUpdateUrl,
} = useProfileRoutes({
    user: props.user,
    context: 'settings',
    accountUpdateUrl: props.accountUpdateUrl,
    profileUpdateUrl: props.profileUpdateUrl,
    certificationStoreUrl: props.certificationStoreUrl,
    achievementStoreUrl: props.achievementStoreUrl,
});

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
    gender: props.user.athleteProfile?.gender ?? props.user.gender ?? 'MALE',
    bday: props.user.athleteProfile?.bday ?? props.user.bday ?? '',
    phone: props.user.athleteProfile?.phone ?? props.user.phone ?? '',
    nik: props.user.athleteProfile?.nik ?? '',
    bpjs: props.user.athleteProfile?.bpjs ?? '',
    alamat: props.user.athleteProfile?.alamat ?? '',
    branch_id: String(props.user.athleteProfile?.branch_id ?? ''),
    group_id: String(props.user.athleteProfile?.group_id ?? ''),
});

const coachForm = useForm({
    status: props.user.coachProfile?.status ?? 'active',
    specialization: props.user.coachProfile?.specialization ?? '',
    bio: props.user.coachProfile?.bio ?? '',
});

const parentForm = useForm({
    phone: props.user.parentProfile?.phone ?? props.user.phone ?? '',
    relation: props.user.parentProfile?.relation ?? 'guardian',
    occupation: props.user.parentProfile?.occupation ?? '',
    notes: props.user.parentProfile?.notes ?? '',
});

const editingAccount = ref(false);
const editingProfile = ref(false);
const editingAthlete = ref(false);
const editingCoach = ref(false);
const editingParent = ref(false);

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

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    coach: 'Pelatih',
    parent: 'Orang tua',
    athlete: 'Atlet',
};

const profileCompletion = computed(() => {
    const values = [
        props.user.name,
        props.user.email,
        props.user.gender,
        props.user.bday,
        props.user.phone,
        props.user.bio,
        props.user.profilePictureUrl,
    ];

    if (props.user.roles.includes('athlete')) {
        values.push(
            props.user.athleteProfile?.height_cm,
            props.user.athleteProfile?.weight_kg,
            props.user.athleteProfile?.branch_id,
            props.user.athleteProfile?.group_id,
            props.user.athleteProfile?.alamat,
        );
    }

    return Math.round(
        (values.filter((value) => value !== null && value !== undefined && String(value).trim() !== '').length /
            values.length) *
            100,
    );
});

const initials = computed(() =>
    props.user.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join(''),
);

const filteredGroups = computed(() => {
    if (!athleteForm.branch_id) return props.groups;

    return props.groups.filter((group) => {
        const option = group as ProfileSelectOption & { branch_id?: string | number | null };
        return option.branch_id === undefined || String(option.branch_id ?? '') === String(athleteForm.branch_id);
    });
});

function showErrors(title: string, errors: Record<string, string>): void {
    const messages = Object.values(errors).filter(Boolean);
    void popup.error(title, messages.length ? messages.join('\n') : 'Periksa data lalu coba lagi.');
}

function saveAccount(): void {
    accountForm.patch(props.accountUpdateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            editingAccount.value = false;
        },
        onError: (errors) => showErrors('Data akun belum tersimpan', errors),
    });
}

async function saveProfile(): Promise<void> {
    if (selectedImage.value && !profileForm.profile_picture) {
        await applyCrop();
        if (!profileForm.profile_picture) return;
    }

    profileForm.post(props.profileUpdateUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            editingProfile.value = false;
            clearSelectedImage();
            profileForm.profile_picture = null;
        },
        onError: (errors) => showErrors('Foto atau bio belum tersimpan', errors),
    });
}

function saveAthlete(): void {
    athleteForm.put(athleteProfileUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingAthlete.value = false;
        },
        onError: (errors) => showErrors('Data atlet belum tersimpan', errors),
    });
}

function saveCoach(): void {
    coachForm.put(coachProfileUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingCoach.value = false;
        },
        onError: (errors) => showErrors('Data pelatih belum tersimpan', errors),
    });
}

function saveParent(): void {
    parentForm.put(parentProfileUpdateUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            editingParent.value = false;
        },
        onError: (errors) => showErrors('Data orang tua belum tersimpan', errors),
    });
}

function cancelAccount(): void {
    accountForm.reset();
    accountForm.clearErrors();
    editingAccount.value = false;
}

function cancelProfile(): void {
    profileForm.reset();
    profileForm.clearErrors();
    clearSelectedImage();
    editingProfile.value = false;
}

function cancelAthlete(): void {
    athleteForm.reset();
    athleteForm.clearErrors();
    editingAthlete.value = false;
}

function cancelCoach(): void {
    coachForm.reset();
    coachForm.clearErrors();
    editingCoach.value = false;
}

function cancelParent(): void {
    parentForm.reset();
    parentForm.clearErrors();
    editingParent.value = false;
}

function editCurrentPhoto(): void {
    editCurrentProfilePicture(props.user.profilePictureUrl);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profil" />

        <SettingsLayout>
            <div class="space-y-6">
                <section
                    class="overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/10 via-card to-card p-5 sm:p-6"
                >
                    <div class="flex flex-col gap-5 md:flex-row md:items-center">
                        <div class="relative shrink-0">
                            <img
                                v-if="props.user.profilePictureUrl"
                                :src="props.user.profilePictureUrl"
                                :alt="props.user.name"
                                class="aspect-[3/4] w-28 rounded-2xl border-4 border-background object-cover shadow-md sm:w-32"
                            />
                            <div
                                v-else
                                class="flex aspect-[3/4] w-28 items-center justify-center rounded-2xl border-4 border-background bg-primary/10 text-3xl font-bold text-primary shadow-md sm:w-32"
                            >
                                {{ initials }}
                            </div>
                            <span
                                class="absolute -right-2 -bottom-2 rounded-full border-4 border-background bg-primary p-2 text-primary-foreground"
                            >
                                <Camera class="size-4" />
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold tracking-[0.2em] text-primary uppercase">Profil pengguna</p>
                            <h1 class="mt-2 text-2xl font-bold tracking-tight break-words sm:text-3xl">
                                {{ props.user.name }}
                            </h1>
                            <div
                                class="mt-2 flex flex-col gap-1 text-sm text-muted-foreground sm:flex-row sm:flex-wrap sm:gap-x-5"
                            >
                                <span class="inline-flex min-w-0 items-center gap-2"
                                    ><Mail class="size-4 shrink-0" /><span class="break-all">{{
                                        props.user.email
                                    }}</span></span
                                >
                                <span v-if="props.user.phone" class="inline-flex items-center gap-2"
                                    ><Phone class="size-4" />{{ props.user.phone }}</span
                                >
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="role in props.user.roles"
                                    :key="role"
                                    class="rounded-full border bg-background px-3 py-1 text-xs font-semibold"
                                >
                                    {{ roleLabels[role] ?? role }}
                                </span>
                            </div>
                        </div>

                        <div class="w-full rounded-2xl border bg-background/80 p-4 md:w-48">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium">Kelengkapan</span>
                                <span class="text-sm font-bold text-primary">{{ profileCompletion }}%</span>
                            </div>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-primary transition-all"
                                    :style="{ width: `${profileCompletion}%` }"
                                />
                            </div>
                            <p class="mt-3 text-xs text-muted-foreground">
                                Lengkapi foto, kontak, dan data peran agar administrasi lebih mudah.
                            </p>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="rounded-xl bg-primary/10 p-2 text-primary"
                                    ><UserRound class="size-5"
                                /></span>
                                <div>
                                    <h2 class="font-semibold">Data akun</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Nama, email, tanggal lahir, dan kontak utama.
                                    </p>
                                </div>
                            </div>
                            <Button
                                v-if="!editingAccount"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                @click="editingAccount = true"
                                ><PencilLine class="size-4" /> Ubah</Button
                            >
                        </div>

                        <form class="grid gap-4" @submit.prevent="saveAccount">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <FormInputField
                                    id="profile-account-name"
                                    v-model="accountForm.name"
                                    label="Nama lengkap"
                                    required
                                    :disabled="!editingAccount"
                                    :error="accountForm.errors.name"
                                />
                                <FormInputField
                                    id="profile-account-email"
                                    v-model="accountForm.email"
                                    label="Email"
                                    type="email"
                                    required
                                    :disabled="!editingAccount"
                                    :error="accountForm.errors.email"
                                />
                                <FormSelectField
                                    id="profile-account-gender"
                                    v-model="accountForm.gender"
                                    label="Jenis kelamin"
                                    :options="genderOptions"
                                    :disabled="!editingAccount"
                                    :error="accountForm.errors.gender"
                                />
                                <FormInputField
                                    id="profile-account-bday"
                                    v-model="accountForm.bday"
                                    label="Tanggal lahir"
                                    type="date"
                                    :disabled="!editingAccount"
                                    :error="accountForm.errors.bday"
                                />
                            </div>
                            <FormInputField
                                id="profile-account-phone"
                                v-model="accountForm.phone"
                                label="Nomor telepon"
                                type="tel"
                                :disabled="!editingAccount"
                                :error="accountForm.errors.phone"
                            />
                            <div v-if="editingAccount" class="grid gap-2 sm:flex sm:justify-end">
                                <Button type="button" variant="outline" @click="cancelAccount">Batal</Button>
                                <Button type="submit" :disabled="accountForm.processing">Simpan data akun</Button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <span class="rounded-xl bg-primary/10 p-2 text-primary"><Camera class="size-5" /></span>
                                <div>
                                    <h2 class="font-semibold">Foto 3×4 dan bio</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Foto disimpan sebagai JPEG 600×800 dan siap untuk kebutuhan event.
                                    </p>
                                </div>
                            </div>
                            <Button
                                v-if="!editingProfile"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                @click="editingProfile = true"
                                ><PencilLine class="size-4" /> Ubah</Button
                            >
                        </div>

                        <form class="grid gap-4" @submit.prevent="saveProfile">
                            <div class="grid gap-2">
                                <label for="profile-bio" class="text-sm font-medium">Bio singkat</label>
                                <textarea
                                    id="profile-bio"
                                    v-model="profileForm.bio"
                                    rows="4"
                                    :disabled="!editingProfile"
                                    class="rounded-xl border border-input bg-background px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                                />
                                <p v-if="profileForm.errors.bio" class="text-sm text-destructive">
                                    {{ profileForm.errors.bio }}
                                </p>
                            </div>

                            <div v-if="editingProfile" class="grid gap-3">
                                <label for="profile-photo-file" class="text-sm font-medium">Pilih foto</label>
                                <input
                                    id="profile-photo-file"
                                    ref="profilePictureFileInput"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="min-h-11 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm"
                                    @change="onProfilePictureChange"
                                />
                                <Button
                                    v-if="props.user.profilePictureUrl"
                                    type="button"
                                    variant="outline"
                                    class="w-full sm:w-fit"
                                    @click="editCurrentPhoto"
                                    >Atur ulang foto saat ini</Button
                                >
                                <p v-if="profilePictureError" class="text-sm text-destructive">
                                    {{ profilePictureError }}
                                </p>

                                <div v-if="selectedImage" class="grid gap-3 rounded-2xl border bg-muted/20 p-3">
                                    <div class="overflow-hidden rounded-xl border bg-muted">
                                        <Cropper
                                            ref="cropperRef"
                                            :src="selectedImage"
                                            :stencil-props="{ aspectRatio: 3 / 4 }"
                                            :canvas="{ width: profilePictureWidth, height: profilePictureHeight }"
                                            class="h-80 w-full sm:h-96"
                                            image-restriction="stencil"
                                            @change="markCropDirty"
                                        />
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="gap-2"
                                            @click="zoomCrop(1.1)"
                                            ><ZoomIn class="size-4" /> Perbesar</Button
                                        >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="gap-2"
                                            @click="zoomCrop(0.9)"
                                            ><ZoomOut class="size-4" /> Perkecil</Button
                                        >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="gap-2"
                                            @click="rotateCrop"
                                            ><RotateCw class="size-4" /> Putar</Button
                                        >
                                        <Button type="button" variant="outline" size="sm" @click="resetCrop"
                                            >Reset</Button
                                        >
                                        <Button type="button" size="sm" class="col-span-2 gap-2" @click="applyCrop"
                                            ><CheckCircle2 class="size-4" /> Gunakan crop 3×4</Button
                                        >
                                    </div>
                                    <p
                                        v-if="profilePictureReady"
                                        class="inline-flex items-center gap-2 text-sm font-medium text-emerald-600"
                                    >
                                        <CheckCircle2 class="size-4" /> Foto 3×4 siap disimpan.
                                    </p>
                                </div>
                            </div>

                            <div v-if="editingProfile" class="grid gap-2 sm:flex sm:justify-end">
                                <Button type="button" variant="outline" @click="cancelProfile">Batal</Button>
                                <Button type="submit" :disabled="profileForm.processing">Simpan foto dan bio</Button>
                            </div>
                        </form>
                    </section>
                </div>

                <section
                    v-if="props.user.roles.includes('athlete')"
                    class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5"
                >
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="rounded-xl bg-primary/10 p-2 text-primary"><Dumbbell class="size-5" /></span>
                            <div>
                                <h2 class="font-semibold">Data atlet</h2>
                                <p class="text-sm text-muted-foreground">
                                    Data fisik, sabuk, keanggotaan, dan identitas administratif.
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="!editingAthlete"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-2"
                            @click="editingAthlete = true"
                            ><PencilLine class="size-4" /> Ubah</Button
                        >
                    </div>

                    <form class="grid gap-4" @submit.prevent="saveAthlete">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <FormInputField
                                id="profile-athlete-height"
                                v-model="athleteForm.height_cm"
                                label="Tinggi (cm)"
                                type="number"
                                min="1"
                                step="0.1"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.height_cm"
                            />
                            <FormInputField
                                id="profile-athlete-weight"
                                v-model="athleteForm.weight_kg"
                                label="Berat (kg)"
                                type="number"
                                min="1"
                                step="0.1"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.weight_kg"
                            />
                            <FormSelectField
                                id="profile-athlete-geup"
                                v-model="athleteForm.geup"
                                label="Sabuk / Geup"
                                :options="geupOptions"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.geup"
                            />
                            <FormSelectField
                                id="profile-athlete-gender"
                                v-model="athleteForm.gender"
                                label="Jenis kelamin"
                                :options="genderOptions"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.gender"
                            />
                            <FormSelectField
                                id="profile-athlete-branch"
                                v-model="athleteForm.branch_id"
                                label="Cabang"
                                :options="props.branches"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.branch_id"
                            />
                            <FormSelectField
                                id="profile-athlete-group"
                                v-model="athleteForm.group_id"
                                label="Kelas latihan"
                                :options="filteredGroups"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.group_id"
                            />
                            <FormInputField
                                id="profile-athlete-bday"
                                v-model="athleteForm.bday"
                                label="Tanggal lahir"
                                type="date"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.bday"
                            />
                            <FormInputField
                                id="profile-athlete-phone"
                                v-model="athleteForm.phone"
                                label="Nomor telepon"
                                type="tel"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.phone"
                            />
                            <FormInputField
                                id="profile-athlete-nik"
                                v-model="athleteForm.nik"
                                label="NIK"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.nik"
                            />
                            <FormInputField
                                id="profile-athlete-bpjs"
                                v-model="athleteForm.bpjs"
                                label="BPJS"
                                :disabled="!editingAthlete"
                                :error="athleteForm.errors.bpjs"
                            />
                        </div>
                        <FormInputField
                            id="profile-athlete-address"
                            v-model="athleteForm.alamat"
                            label="Alamat"
                            :disabled="!editingAthlete"
                            :error="athleteForm.errors.alamat"
                        />
                        <div v-if="editingAthlete" class="grid gap-2 sm:flex sm:justify-end">
                            <Button type="button" variant="outline" @click="cancelAthlete">Batal</Button
                            ><Button type="submit" :disabled="athleteForm.processing">Simpan data atlet</Button>
                        </div>
                    </form>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section
                        v-if="props.user.roles.includes('coach')"
                        class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="rounded-xl bg-primary/10 p-2 text-primary"
                                    ><ShieldCheck class="size-5"
                                /></span>
                                <div>
                                    <h2 class="font-semibold">Data pelatih</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Status, spesialisasi, dan profil profesional.
                                    </p>
                                </div>
                            </div>
                            <Button
                                v-if="!editingCoach"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="editingCoach = true"
                                >Ubah</Button
                            >
                        </div>
                        <form class="grid gap-4" @submit.prevent="saveCoach">
                            <FormSelectField
                                id="profile-coach-status"
                                v-model="coachForm.status"
                                label="Status"
                                :options="coachStatusOptions"
                                :disabled="!editingCoach"
                                :error="coachForm.errors.status"
                            />
                            <FormInputField
                                id="profile-coach-specialization"
                                v-model="coachForm.specialization"
                                label="Spesialisasi"
                                :disabled="!editingCoach"
                                :error="coachForm.errors.specialization"
                            />
                            <div class="grid gap-2">
                                <label for="profile-coach-bio" class="text-sm font-medium">Bio pelatih</label
                                ><textarea
                                    id="profile-coach-bio"
                                    v-model="coachForm.bio"
                                    rows="4"
                                    :disabled="!editingCoach"
                                    class="rounded-xl border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                                />
                                <p v-if="coachForm.errors.bio" class="text-sm text-destructive">
                                    {{ coachForm.errors.bio }}
                                </p>
                            </div>
                            <div v-if="editingCoach" class="grid gap-2 sm:flex sm:justify-end">
                                <Button type="button" variant="outline" @click="cancelCoach">Batal</Button
                                ><Button type="submit" :disabled="coachForm.processing">Simpan data pelatih</Button>
                            </div>
                        </form>
                    </section>

                    <section
                        v-if="props.user.roles.includes('parent')"
                        class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5"
                    >
                        <div class="mb-5 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="rounded-xl bg-primary/10 p-2 text-primary"
                                    ><UsersRound class="size-5"
                                /></span>
                                <div>
                                    <h2 class="font-semibold">Data orang tua</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Hubungan keluarga dan daftar anak tertaut.
                                    </p>
                                </div>
                            </div>
                            <Button
                                v-if="!editingParent"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="editingParent = true"
                                >Ubah</Button
                            >
                        </div>
                        <form class="grid gap-4" @submit.prevent="saveParent">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <FormInputField
                                    id="profile-parent-phone"
                                    v-model="parentForm.phone"
                                    label="Nomor telepon"
                                    :disabled="!editingParent"
                                    :error="parentForm.errors.phone"
                                /><FormSelectField
                                    id="profile-parent-relation"
                                    v-model="parentForm.relation"
                                    label="Hubungan"
                                    :options="parentRelationOptions"
                                    :disabled="!editingParent"
                                    :error="parentForm.errors.relation"
                                />
                            </div>
                            <FormInputField
                                id="profile-parent-occupation"
                                v-model="parentForm.occupation"
                                label="Pekerjaan"
                                :disabled="!editingParent"
                                :error="parentForm.errors.occupation"
                            />
                            <div class="grid gap-2">
                                <label for="profile-parent-notes" class="text-sm font-medium">Catatan</label
                                ><textarea
                                    id="profile-parent-notes"
                                    v-model="parentForm.notes"
                                    rows="3"
                                    :disabled="!editingParent"
                                    class="rounded-xl border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                                />
                            </div>
                            <div class="rounded-xl border bg-muted/20 p-3">
                                <p class="text-sm font-semibold">Anak tertaut</p>
                                <div v-if="props.user.parentProfile?.athletes?.length" class="mt-3 grid gap-2">
                                    <div
                                        v-for="athlete in props.user.parentProfile.athletes"
                                        :key="athlete.id"
                                        class="flex items-start gap-3 rounded-lg bg-background p-3 text-sm"
                                    >
                                        <BadgeCheck class="mt-0.5 size-4 shrink-0 text-primary" />
                                        <div>
                                            <p class="font-medium">{{ athlete.name }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ athlete.branch?.branch_name ?? 'Tanpa cabang' }} ·
                                                {{ athlete.group?.group_name ?? 'Tanpa kelas' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="mt-2 text-sm text-muted-foreground">
                                    Belum ada akun anak yang tertaut.
                                </p>
                            </div>
                            <div v-if="editingParent" class="grid gap-2 sm:flex sm:justify-end">
                                <Button type="button" variant="outline" @click="cancelParent">Batal</Button
                                ><Button type="submit" :disabled="parentForm.processing">Simpan data orang tua</Button>
                            </div>
                        </form>
                    </section>
                </div>

                <div v-if="props.user.roles.includes('athlete')" class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border bg-card p-4">
                        <Ruler class="size-5 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">Tinggi</p>
                        <p class="text-lg font-bold">{{ props.user.athleteProfile?.height_cm ?? '-' }} cm</p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <Scale class="size-5 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">Berat</p>
                        <p class="text-lg font-bold">{{ props.user.athleteProfile?.weight_kg ?? '-' }} kg</p>
                    </div>
                    <div class="rounded-xl border bg-card p-4">
                        <MapPin class="size-5 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">Cabang / kelas</p>
                        <p class="text-sm font-bold">
                            {{ props.user.athleteProfile?.branch?.branch_name ?? '-' }} ·
                            {{ props.user.athleteProfile?.group?.group_name ?? '-' }}
                        </p>
                    </div>
                </div>

                <ProfileCertificationsSection
                    v-if="props.user.roles.includes('athlete') || props.user.roles.includes('coach')"
                    :certifications="props.user.certifications"
                    :can-manage="true"
                    :store-url="props.certificationStoreUrl"
                    :update-url="certificationUpdateUrl"
                />

                <ProfileAchievementsSection
                    v-if="props.user.roles.includes('athlete') || props.user.roles.includes('coach')"
                    :achievements="props.user.achievements"
                    :can-manage="true"
                    :store-url="props.achievementStoreUrl"
                    :update-url="achievementUpdateUrl"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
