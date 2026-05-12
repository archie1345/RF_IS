<script setup lang="ts">
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

type AthleteProfile = {
    height_cm?: number;
    weight_kg?: number;
    geup?: string;
    nik?: string;
    bpjs?: string;
    phone?: string;
    bday?: string;
    gender?: string;
    alamat?: string;
    branch?: { branch_name: string };
    group?: { group_name: string };
};

type CoachProfile = {
    specialization?: string;
    experience_years?: number;
    certifications?: string;
};

type ParentProfile = {
    phone?: string;
    relationship?: string;
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
};

type User = {
    id: number;
    name: string;
    email: string;
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
const cropperRef = ref();
const profilePictureError = ref('');

const props = defineProps<{
    user: User;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: props.user.name, href: `/users/${props.user.id}` },
];

const certColumns: TableColumn[] = [
    { key: 'cert_type', label: 'Type' },
    { key: 'title', label: 'Title' },
    { key: 'issuer', label: 'Issuer' },
    { key: 'certified_at', label: 'Certified' },
    { key: 'expires_at', label: 'Expires' },
    { key: 'notes', label: 'Notes' },
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
];

const certRows: TableRow[] = props.user.certifications.map((cert) => ({
    id: String(cert.id),
    cert_type: cert.cert_type,
    title: cert.title,
    issuer: cert.issuer ?? '-',
    certified_at: cert.certified_at ?? '-',
    expires_at: cert.expires_at ?? '-',
    notes: cert.notes ?? '-',
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
}));

const certForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
    notes: '',
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
});

const isEditingProfile = ref(false);
const isEditingAthlete = ref(false);
const isEditingCoach = ref(false);
const isEditingParent = ref(false);

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
});

const coachForm = useForm({
    specialization: props.user.coachProfile?.specialization ?? '',
    experience_years: String(props.user.coachProfile?.experience_years ?? ''),
    certifications: props.user.coachProfile?.certifications ?? '',
});

const parentForm = useForm({
    phone: props.user.parentProfile?.phone ?? '',
    relationship: props.user.parentProfile?.relationship ?? '',
});

function cancelProfileEdit() {
    isEditingProfile.value = false;
    profileForm.reset();
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

function saveProfileChanges() {
    profileForm.post(`/users/${props.user.id}/profile`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isEditingProfile.value = false;
            profileForm.reset();
            window.location.reload();
        },
    });
}

function saveAthleteChanges() {
    if (!athleteForm.phone && !athleteForm.bday && !athleteForm.nik && !athleteForm.alamat) {
        alert('Cannot save a blank form. Please fill in the details.');
        return;
    }

    athleteForm.put(`/role/user/${props.user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAthlete.value = false;
            window.location.reload();
        },
    });
}

function saveCoachChanges() {
    if (!coachForm.specialization && !coachForm.experience_years && !coachForm.certifications) {
        alert('Cannot save a blank coach profile.');
        return;
    }

    coachForm.put(`/coaches/${props.user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingCoach.value = false;
            window.location.reload();
        },
    });
}

function saveParentChanges() {
    if (!parentForm.phone && !parentForm.relationship) {
        alert('Cannot save a blank parent profile.');
        return;
    }

    parentForm.put(`/parents/${props.user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingParent.value = false;
            window.location.reload();
        },
    });
}

function addCertification() {
    if (!certForm.title) return alert('Title cannot be blank');
    certForm.post(`/role-users/${props.user.id}/certifications`, {
        preserveScroll: true,
        onSuccess: () => {
            certForm.reset();
            certForm.cert_type = 'BELT';
            window.location.reload();
        },
    });
}

function addAchievement() {
    if (!achievementForm.championship_name) return alert('Championship name cannot be blank');
    achievementForm.post(`/role-users/${props.user.id}/achievements`, {
        preserveScroll: true,
        onSuccess: () => {
            achievementForm.reset();
            achievementForm.medal = 'NONE';
            window.location.reload();
        },
    });
}

async function onProfilePictureChange(event: Event) {
    const target = event.target as HTMLInputElement;

    const file = target.files?.[0];

    if (!file) return;

    const maxSize = 2 * 1024 * 1024;

    if (file.size > maxSize) {
        profilePictureError.value =
            'Profile picture must be smaller than 2MB.';
        return;
    }

    if (!file.type.startsWith('image/')) {
        profilePictureError.value =
            'Selected file must be an image.';
        return;
    }

    profilePictureError.value = '';

    selectedImage.value = URL.createObjectURL(file);
}

async function applyCrop() {
    const canvas = cropperRef.value?.getResult()?.canvas;

    if (!canvas) return;

    canvas.toBlob((blob: Blob | null) => {
        if (!blob) return;

        const croppedFile = new File(
            [blob],
            'profile-picture.jpg',
            {
                type: 'image/jpeg',
            }
        );

        profileForm.profile_picture = croppedFile;
    }, 'image/jpeg', 0.9);
}

onMounted(() => {
    window.addEventListener('popstate', () => {
        window.location.reload();
    });
});
</script>

<template>
    <Head :title="`${user.name} - Profile`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div :key="$page.url" class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection :title="`${user.name}'s Profile`" :description="`View detailed information for ${user.email}`" />

            <div class="grid gap-6">
                
                <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0">
                                <img
                                    v-if="user.profilePictureUrl"
                                    :src="user.profilePictureUrl"
                                    :alt="user.name"
                                    class="h-16 w-16 rounded-full object-cover"
                                />
                                <div v-else class="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-2xl font-semibold text-muted-foreground">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">{{ user.name }}</h3>
                                <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                                <p class="text-sm text-muted-foreground">Roles: {{ user.roles.join(', ') }}</p>
                            </div>
                        </div>
                        <Button v-if="!isEditingProfile" variant="outline" size="sm" @click="isEditingProfile = true">Edit</Button>
                    </div>

                    <form class="space-y-4" @submit.prevent="saveProfileChanges">
                        <div class="grid gap-2">
                            <label for="bio" class="text-sm font-medium">Bio</label>
                            <textarea id="bio" v-model="profileForm.bio" :disabled="!isEditingProfile" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed" />
                        </div>
                        <div v-if="isEditingProfile" class="grid gap-2">
                            <label for="profile-picture" class="text-sm font-medium">Profile Picture</label>
                            <input
                                id="profile-picture"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                @change="onProfilePictureChange"
                                class="rounded-md border border-input px-3 py-2"
                            />

                            <p
                                v-if="profilePictureError"
                                class="text-sm text-destructive"
                            >
                                {{ profilePictureError }}
                            </p>

                            <div
                                v-if="selectedImage"
                                class="mt-4 space-y-4"
                            >
                                <Cropper
                                    ref="cropperRef"
                                    :src="selectedImage"
                                    :stencil-props="{
                                        aspectRatio: 1
                                    }"
                                    class="h-72 w-full"
                                />

                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="applyCrop"
                                >
                                    Apply Crop
                                </Button>
                            </div>                        </div>
                        <div v-if="isEditingProfile" class="flex gap-2">
                            <Button type="submit" :disabled="profileForm.processing" size="sm">Save Changes</Button>
                            <Button type="button" variant="outline" size="sm" @click="cancelProfileEdit">Cancel</Button>
                        </div>
                    </form>
                </div>

                <div v-if="user.roles.includes('athlete')" class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="font-semibold">Athlete Details</h4>
                        <Button v-if="!isEditingAthlete" variant="outline" size="sm" @click="isEditingAthlete = true">Edit</Button>
                    </div>

                    <form class="space-y-3" @submit.prevent="saveAthleteChanges">
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="height" v-model="athleteForm.height_cm" label="Height (cm)" type="number" :disabled="!isEditingAthlete" />
                            <FormInputField id="weight" v-model="athleteForm.weight_kg" label="Weight (kg)" type="number" :disabled="!isEditingAthlete" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormSelectField id="geup" v-model="athleteForm.geup" label="Geup" :disabled="!isEditingAthlete" :options="[{ value: 'GEUP_10', label: 'GEUP 10' }, { value: 'GEUP_9', label: 'GEUP 9' }, { value: 'GEUP_8', label: 'GEUP 8' }, { value: 'GEUP_7', label: 'GEUP 7' }, { value: 'GEUP_6', label: 'GEUP 6' }, { value: 'GEUP_5', label: 'GEUP 5' }, { value: 'GEUP_4', label: 'GEUP 4' }, { value: 'GEUP_3', label: 'GEUP 3' }, { value: 'GEUP_2', label: 'GEUP 2' }, { value: 'GEUP_1', label: 'GEUP 1' }, { value: 'DAN', label: 'DAN' }]" />
                            <FormSelectField id="gender" v-model="athleteForm.gender" label="Gender" :disabled="!isEditingAthlete" :options="[{ value: 'MALE', label: 'Male' }, { value: 'FEMALE', label: 'Female' }]" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="bday" v-model="athleteForm.bday" label="Birthday" type="date" :disabled="!isEditingAthlete" />
                            <FormInputField id="phone" v-model="athleteForm.phone" label="Phone" :disabled="!isEditingAthlete" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="nik" v-model="athleteForm.nik" label="NIK" :disabled="!isEditingAthlete" />
                            <FormInputField id="bpjs" v-model="athleteForm.bpjs" label="BPJS" :disabled="!isEditingAthlete" />
                        </div>
                        <FormInputField id="alamat" v-model="athleteForm.alamat" label="Address" :disabled="!isEditingAthlete" />
                        
                        <div v-if="isEditingAthlete" class="flex gap-2 mt-4">
                            <Button type="submit" :disabled="athleteForm.processing" size="sm">Save Changes</Button>
                            <Button type="button" variant="outline" size="sm" @click="cancelAthleteEdit">Cancel</Button>
                        </div>
                    </form>
                </div>

                <div v-if="user.roles.includes('coach')" class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="font-semibold">Coach Details</h4>
                        <Button v-if="!isEditingCoach" variant="outline" size="sm" @click="isEditingCoach = true">Edit</Button>
                    </div>

                    <form class="space-y-3" @submit.prevent="saveCoachChanges">
                        <FormInputField id="specialization" v-model="coachForm.specialization" label="Specialization" :disabled="!isEditingCoach" />
                        <FormInputField id="experience-years" v-model="coachForm.experience_years" label="Experience (years)" type="number" :disabled="!isEditingCoach" />
                        <FormInputField id="certifications" v-model="coachForm.certifications" label="Certifications" :disabled="!isEditingCoach" />
                        
                        <div v-if="isEditingCoach" class="flex gap-2 mt-4">
                            <Button type="submit" :disabled="coachForm.processing" size="sm">Save Changes</Button>
                            <Button type="button" variant="outline" size="sm" @click="cancelCoachEdit">Cancel</Button>
                        </div>
                    </form>
                </div>

                <div v-if="user.roles.includes('parent')" class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="font-semibold">Parent Details</h4>
                        <Button v-if="!isEditingParent" variant="outline" size="sm" @click="isEditingParent = true">Edit</Button>
                    </div>

                    <form class="space-y-3" @submit.prevent="saveParentChanges">
                        <div class="grid gap-3 md:grid-cols-2">
                            <FormInputField id="parent-phone" v-model="parentForm.phone" label="Phone" :disabled="!isEditingParent" />
                            <FormInputField id="relationship" v-model="parentForm.relationship" label="Relationship" :disabled="!isEditingParent" />
                        </div>
                        
                        <div class="mt-4 p-3 bg-muted/30 border border-border rounded-lg text-sm">
                            <span class="font-medium">Linked Athletes (Children):</span>
                            <ul v-if="user.parentProfile?.athletes?.length" class="ml-4 mt-1 list-disc text-muted-foreground">
                                <li v-for="athlete in user.parentProfile?.athletes" :key="athlete.id">
                                    {{ athlete.name }} ({{ athlete.branch?.branch_name }} - {{ athlete.group?.group_name }})
                                </li>
                            </ul>
                            <p v-else class="mt-1 text-muted-foreground italic">No children linked to this account yet.</p>
                        </div>

                        <div v-if="isEditingParent" class="flex gap-2 mt-4">
                            <Button type="submit" :disabled="parentForm.processing" size="sm">Save Changes</Button>
                            <Button type="button" variant="outline" size="sm" @click="cancelParentEdit">Cancel</Button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                <h4 class="mb-3 font-semibold">Certifications</h4>
                <DataTable
                    title="Certifications"
                    description="View all certifications for this user."
                    :columns="certColumns"
                    :rows="certRows"
                    empty-text="No certifications found."
                />
                <div v-if="user.roles.includes('athlete') || user.roles.includes('coach')" class="mt-6 pt-4 border-t border-border">
                    <h5 class="mb-2 font-medium">Add Certification</h5>
                    <form class="grid gap-3" @submit.prevent="addCertification">
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormSelectField id="cert-type" v-model="certForm.cert_type" label="Type" :options="[{ value: 'BELT', label: 'Belt' }, { value: 'REFEREE', label: 'Referee' }, { value: 'TRAINER', label: 'Trainer' }]" />
                            <FormInputField id="cert-title" v-model="certForm.title" label="Title" required />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="cert-issuer" v-model="certForm.issuer" label="Issuer" />
                            <FormInputField id="cert-date" v-model="certForm.certified_at" label="Certified at" type="date" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="cert-expires" v-model="certForm.expires_at" label="Expires at" type="date" />
                            <FormInputField id="cert-notes" v-model="certForm.notes" label="Notes" />
                        </div>
                        <div>
                            <Button type="submit" :disabled="certForm.processing">Add Certification</Button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                <h4 class="mb-3 font-semibold">Achievements</h4>
                <DataTable
                    title="Achievements"
                    description="View all achievements for this user."
                    :columns="achColumns"
                    :rows="achRows"
                    empty-text="No achievements found."
                />
                <div v-if="user.roles.includes('athlete') || user.roles.includes('coach')" class="mt-6 pt-4 border-t border-border">
                    <h5 class="mb-2 font-medium">Add Achievement</h5>
                    <form class="grid gap-3" @submit.prevent="addAchievement">
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="ach-name" v-model="achievementForm.championship_name" label="Championship name" required />
                            <FormSelectField id="ach-medal" v-model="achievementForm.medal" label="Medal" :options="[{ value: 'GOLD', label: 'Gold' }, { value: 'SILVER', label: 'Silver' }, { value: 'BRONZE', label: 'Bronze' }, { value: 'NONE', label: 'None' }]" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <FormInputField id="ach-location" v-model="achievementForm.location" label="Location" />
                            <FormInputField id="ach-date" v-model="achievementForm.event_date" label="Date" type="date" />
                        </div>
                        <div class="grid gap-2 md:grid-cols-3">
                            <FormInputField id="ach-class" v-model="achievementForm.class_name" label="Class name" />
                            <FormInputField id="ach-division" v-model="achievementForm.division" label="Division" />
                            <FormInputField id="ach-category" v-model="achievementForm.category" label="Category" />
                        </div>
                        <FormInputField id="ach-notes" v-model="achievementForm.notes" label="Notes" />
                        <div>
                            <Button type="submit" :disabled="achievementForm.processing">Add Achievement</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>