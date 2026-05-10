<script setup lang="ts">
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import { managementRoutes } from '@/data/management';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { SelectOption, TableColumn, TableRow } from '@/types/management';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type UserProfile = {
    name: string;
    email: string;
    bio?: string;
    profile_picture_url?: string | null;
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

const props = defineProps<{
    user: UserProfile;
    certifications: Certification[];
    achievements: Achievement[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: managementRoutes.dashboard },
    { title: 'My Profile', href: '/my-profile' },
];

const certColumns: TableColumn[] = [
    { key: 'cert_type', label: 'Type' },
    { key: 'title', label: 'Title' },
    { key: 'issuer', label: 'Issuer' },
    { key: 'certified_at', label: 'Certified' },
    { key: 'expires_at', label: 'Expires' },
];

const achColumns: TableColumn[] = [
    { key: 'championship_name', label: 'Championship' },
    { key: 'medal', label: 'Medal' },
    { key: 'location', label: 'Location' },
    { key: 'event_date', label: 'Date' },
];

const profileForm = useForm({
    bio: props.user.bio ?? '',
    profile_picture: null as File | null,
});

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

const certRows = ref<TableRow[]>(props.certifications.map((cert) => ({
    id: String(cert.id),
    cert_type: cert.cert_type,
    title: cert.title,
    issuer: cert.issuer ?? '-',
    certified_at: cert.certified_at ?? '-',
    expires_at: cert.expires_at ?? '-',
})));

const achRows = ref<TableRow[]>(props.achievements.map((ach) => ({
    id: String(ach.id),
    championship_name: ach.championship_name,
    medal: ach.medal,
    location: ach.location ?? '-',
    event_date: ach.event_date ?? '-',
})));

function onProfilePictureChange(event: Event) {
    const target = event.target as HTMLInputElement;
    profileForm.profile_picture = target.files?.[0] ?? null;
}

function saveProfile() {
    profileForm.post('/my-profile', { forceFormData: true, preserveScroll: true });
}

function addCertification() {
    certForm.post('/my-profile/certifications', { forceFormData: true, preserveScroll: true });
}

function addAchievement() {
    achievementForm.post('/my-profile/achievements', { forceFormData: true, preserveScroll: true });
}

function onCertificationFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    certForm.file = target.files?.[0] ?? null;
}

function onAchievementFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    achievementForm.file = target.files?.[0] ?? null;
}
</script>

<template>
    <Head title="My Profile" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection title="My Profile" description="Manage your profile picture, bio, certifications, and achievements." />

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                    <div class="mb-4 flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <img
                                v-if="user.profile_picture_url"
                                :src="user.profile_picture_url"
                                :alt="user.name"
                                class="h-16 w-16 rounded-full object-cover"
                            >
                            <div v-else class="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground">
                                No image
                            </div>
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ user.name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                        </div>
                    </div>

                    <form class="grid gap-3" @submit.prevent="saveProfile">
                        <h4 class="font-semibold">Update Profile</h4>
                        <FormInputField id="profile-bio" v-model="profileForm.bio" label="Bio" placeholder="Tell us about yourself" />
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Profile picture</label>
                            <input
                                type="file"
                                accept="image/*"
                                class="h-10 rounded-md border border-input px-3 py-2 text-sm file:mr-4 file:rounded file:border-0 file:bg-primary file:px-2 file:py-1 file:text-xs file:text-primary-foreground file:hover:bg-primary/90"
                                @change="onProfilePictureChange"
                            >
                        </div>
                        <Button type="submit" :disabled="profileForm.processing" class="w-full">Save profile</Button>
                    </form>
                </div>

                <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                    <form class="grid gap-3" @submit.prevent="addCertification">
                        <h4 class="font-semibold">Add Certification</h4>
                        <FormSelectField
                            id="cert-type"
                            v-model="certForm.cert_type"
                            label="Type"
                            :options="[
                                { value: 'BELT', label: 'Belt' },
                                { value: 'REFEREE', label: 'Referee' },
                                { value: 'TRAINER', label: 'Trainer' },
                            ]"
                        />
                        <FormInputField id="cert-title" v-model="certForm.title" label="Title" placeholder="e.g., Red Belt" />
                        <FormInputField id="cert-issuer" v-model="certForm.issuer" label="Issuer" placeholder="e.g., ITF" />
                        <FormInputField id="cert-date" v-model="certForm.certified_at" label="Certified at" type="date" />
                        <FormInputField id="cert-expires" v-model="certForm.expires_at" label="Expires at" type="date" />
                        <FormInputField id="cert-notes" v-model="certForm.notes" label="Notes" placeholder="Additional details" />
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Attach certificate file (optional)</label>
                            <input type="file" class="h-10 rounded-md border border-input px-3 py-2 text-sm" @change="onCertificationFileChange">
                        </div>
                        <Button type="submit" :disabled="certForm.processing" class="w-full">Add certification</Button>
                    </form>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                    <form class="grid gap-3" @submit.prevent="addAchievement">
                        <h4 class="font-semibold">Add Achievement</h4>
                        <FormInputField
                            id="ach-name"
                            v-model="achievementForm.championship_name"
                            label="Championship name"
                            placeholder="e.g., National Tournament 2026"
                        />
                        <FormSelectField
                            id="ach-medal"
                            v-model="achievementForm.medal"
                            label="Medal"
                            :options="[
                                { value: 'GOLD', label: '🥇 Gold' },
                                { value: 'SILVER', label: '🥈 Silver' },
                                { value: 'BRONZE', label: '🥉 Bronze' },
                                { value: 'NONE', label: 'Participation' },
                            ]"
                        />
                        <FormInputField id="ach-location" v-model="achievementForm.location" label="Location" placeholder="City/Venue" />
                        <FormInputField id="ach-date" v-model="achievementForm.event_date" label="Date" type="date" />
                        <FormInputField id="ach-class" v-model="achievementForm.class_name" label="Class name" placeholder="e.g., Senior" />
                        <FormInputField id="ach-division" v-model="achievementForm.division" label="Division" placeholder="e.g., Under 75kg" />
                        <FormInputField id="ach-category" v-model="achievementForm.category" label="Category" placeholder="e.g., Male" />
                        <FormInputField id="ach-notes" v-model="achievementForm.notes" label="Notes" placeholder="Additional details" />
                        <div class="grid gap-2">
                            <label class="text-sm font-medium">Attach achievement file (optional)</label>
                            <input type="file" class="h-10 rounded-md border border-input px-3 py-2 text-sm" @change="onAchievementFileChange">
                        </div>
                        <Button type="submit" :disabled="achievementForm.processing" class="w-full">Add achievement</Button>
                    </form>
                </div>

                <div class="space-y-6">
                    <div v-if="certRows.length > 0" class="rounded-lg border p-4">
                        <h4 class="mb-4 font-semibold">Your Certifications</h4>
                        <DataTable
                            title=""
                            description=""
                            :columns="certColumns"
                            :rows="certRows"
                        />
                    </div>

                    <div v-if="achRows.length > 0" class="rounded-lg border p-4">
                        <h4 class="mb-4 font-semibold">Your Achievements</h4>
                        <DataTable
                            title=""
                            description=""
                            :columns="achColumns"
                            :rows="achRows"
                        />
                    </div>

                    <div v-if="certRows.length === 0 && achRows.length === 0" class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                        <p>Add certifications and achievements to get started!</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
