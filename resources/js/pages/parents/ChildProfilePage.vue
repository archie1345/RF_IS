<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import InputError from '@/components/InputError.vue';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SelectOption = {
    value: string | number;
    label: string;
};

type Child = {
    id: number;
    name: string;
    email: string;
    gender?: string;
    bday?: string;
    phone?: string;
    roles: string[];
    bio?: string;
    profilePictureUrl?: string | null;
    athleteProfile?: {
        height_cm?: number;
        weight_kg?: number;
        geup?: string;
        nik?: string;
        bpjs?: string;
        phone?: string;
        bday?: string;
        gender?: string;
        alamat?: string;
        branch_id?: string | number | null;
        group_id?: string | number | null;
        branch?: string;
        group?: string;
    } | null;
    achievements: Array<Record<string, unknown>>;
    certifications: Array<Record<string, unknown>>;
};

const props = withDefaults(defineProps<{
    child: Child;
    branches?: SelectOption[];
    groups?: SelectOption[];
}>(), {
    branches: () => [],
    groups: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Children', href: '/users' },
    { title: props.child.name, href: `/users/${props.child.id}` },
];

const isEditingAccount = ref(false);
const isEditingAthlete = ref(false);
const isEditingPassword = ref(false);

const geupOptions = computed(() => [
    'GEUP_10', 'GEUP_9', 'GEUP_8', 'GEUP_7', 'GEUP_6', 'GEUP_5', 'GEUP_4', 'GEUP_3', 'GEUP_2', 'GEUP_1', 'DAN',
].map((value) => ({ value, label: value.replace('_', ' ') })));

const genderOptions = [
    { value: 'MALE', label: 'Male' },
    { value: 'FEMALE', label: 'Female' },
];

const accountForm = useForm({
    name: props.child.name ?? '',
    email: props.child.email ?? '',
    gender: props.child.gender ?? 'MALE',
    bday: props.child.bday ?? '',
    phone: props.child.phone ?? '',
});

const athleteForm = useForm({
    height_cm: String(props.child.athleteProfile?.height_cm ?? ''),
    weight_kg: String(props.child.athleteProfile?.weight_kg ?? ''),
    geup: props.child.athleteProfile?.geup ?? 'GEUP_10',
    gender: props.child.athleteProfile?.gender ?? props.child.gender ?? 'MALE',
    bday: props.child.athleteProfile?.bday ?? props.child.bday ?? '',
    phone: props.child.athleteProfile?.phone ?? props.child.phone ?? '',
    nik: props.child.athleteProfile?.nik ?? '',
    bpjs: props.child.athleteProfile?.bpjs ?? '',
    alamat: props.child.athleteProfile?.alamat ?? '',
    branch_id: String(props.child.athleteProfile?.branch_id ?? props.branches[0]?.value ?? ''),
    group_id: String(props.child.athleteProfile?.group_id ?? props.groups[0]?.value ?? ''),
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

function saveAccount() {
    accountForm.patch(`/users/${props.child.id}/account`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAccount.value = false;
            window.location.reload();
        },
    });
}

function saveAthlete() {
    athleteForm.put(`/users/${props.child.id}/athlete-profile`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingAthlete.value = false;
            window.location.reload();
        },
    });
}

function savePassword() {
    passwordForm.put(`/users/${props.child.id}/password`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditingPassword.value = false;
            passwordForm.reset();
        },
    });
}

function cancelAccountEdit() {
    isEditingAccount.value = false;
    accountForm.reset();
    accountForm.clearErrors();
}

function cancelAthleteEdit() {
    isEditingAthlete.value = false;
    athleteForm.reset();
    athleteForm.clearErrors();
}

function cancelPasswordEdit() {
    isEditingPassword.value = false;
    passwordForm.reset();
    passwordForm.clearErrors();
}
</script>

<template>
    <Head :title="`${child.name} - Child Profile`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <PageSection
                :title="`${child.name}'s profile`"
                description="As a linked parent, you can update your child's account, athlete details, and login password here."
            />

            <div class="grid gap-6 xl:grid-cols-[280px_1fr]">
                <aside class="rounded-xl border border-border/70 bg-card p-5 shadow-sm">
                    <div class="flex flex-col items-center text-center">
                        <img
                            v-if="child.profilePictureUrl"
                            :src="child.profilePictureUrl"
                            :alt="child.name"
                            class="aspect-[3/4] w-28 rounded-lg border border-border object-cover"
                        />
                        <div
                            v-else
                            class="flex aspect-[3/4] w-28 items-center justify-center rounded-lg border border-border bg-muted text-4xl font-semibold text-muted-foreground"
                        >
                            {{ child.name.charAt(0).toUpperCase() }}
                        </div>

                        <h2 class="mt-4 text-xl font-semibold">{{ child.name }}</h2>
                        <p class="text-sm text-muted-foreground">{{ child.email }}</p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ child.athleteProfile?.branch ?? 'No branch' }} • {{ child.athleteProfile?.group ?? 'No group' }}
                        </p>
                    </div>
                </aside>

                <div class="grid gap-6">
                    <section class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Child Account Details</h3>
                                <p class="text-sm text-muted-foreground">Update the child account identity and contact information.</p>
                            </div>
                            <Button v-if="!isEditingAccount" variant="outline" size="sm" @click="isEditingAccount = true">Edit Account</Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveAccount">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="child-name" v-model="accountForm.name" label="Name" :disabled="!isEditingAccount" :error="accountForm.errors.name" />
                                <FormInputField id="child-email" v-model="accountForm.email" label="Email" type="email" :disabled="!isEditingAccount" :error="accountForm.errors.email" />
                            </div>
                            <div class="grid gap-3 md:grid-cols-3">
                                <FormSelectField id="child-gender" v-model="accountForm.gender" label="Gender" :disabled="!isEditingAccount" :options="genderOptions" :error="accountForm.errors.gender" />
                                <FormInputField id="child-bday" v-model="accountForm.bday" label="Birth date" type="date" :disabled="!isEditingAccount" :error="accountForm.errors.bday" />
                                <FormInputField id="child-phone" v-model="accountForm.phone" label="Phone" :disabled="!isEditingAccount" :error="accountForm.errors.phone" />
                            </div>
                            <div v-if="isEditingAccount" class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" size="sm" :disabled="accountForm.processing">Save Account</Button>
                                <Button type="button" variant="outline" size="sm" @click="cancelAccountEdit">Cancel</Button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Child Athlete Data</h3>
                                <p class="text-sm text-muted-foreground">Update training, body, rank, identifier, and address data for this child.</p>
                            </div>
                            <Button v-if="!isEditingAthlete" variant="outline" size="sm" @click="isEditingAthlete = true">Edit Athlete Data</Button>
                        </div>

                        <form class="space-y-3" @submit.prevent="saveAthlete">
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="child-height" v-model="athleteForm.height_cm" label="Height (cm)" type="number" :disabled="!isEditingAthlete" :error="athleteForm.errors.height_cm" />
                                <FormInputField id="child-weight" v-model="athleteForm.weight_kg" label="Weight (kg)" type="number" :disabled="!isEditingAthlete" :error="athleteForm.errors.weight_kg" />
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormSelectField id="child-geup" v-model="athleteForm.geup" label="Geup" :disabled="!isEditingAthlete" :options="geupOptions" :error="athleteForm.errors.geup" />
                                <FormSelectField id="child-athlete-gender" v-model="athleteForm.gender" label="Gender" :disabled="!isEditingAthlete" :options="genderOptions" :error="athleteForm.errors.gender" />
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="child-athlete-bday" v-model="athleteForm.bday" label="Birth date" type="date" :disabled="!isEditingAthlete" :error="athleteForm.errors.bday" />
                                <FormInputField id="child-athlete-phone" v-model="athleteForm.phone" label="Phone" :disabled="!isEditingAthlete" :error="athleteForm.errors.phone" />
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormSelectField id="child-branch" v-model="athleteForm.branch_id" label="Branch" :disabled="!isEditingAthlete" :options="branches" :error="athleteForm.errors.branch_id" />
                                <FormSelectField id="child-group" v-model="athleteForm.group_id" label="Group" :disabled="!isEditingAthlete" :options="groups" :error="athleteForm.errors.group_id" />
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <FormInputField id="child-nik" v-model="athleteForm.nik" label="NIK" :disabled="!isEditingAthlete" :error="athleteForm.errors.nik" />
                                <FormInputField id="child-bpjs" v-model="athleteForm.bpjs" label="BPJS" :disabled="!isEditingAthlete" :error="athleteForm.errors.bpjs" />
                            </div>
                            <FormInputField id="child-address" v-model="athleteForm.alamat" label="Address" :disabled="!isEditingAthlete" :error="athleteForm.errors.alamat" />
                            <div v-if="isEditingAthlete" class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" size="sm" :disabled="athleteForm.processing">Save Athlete Data</Button>
                                <Button type="button" variant="outline" size="sm" @click="cancelAthleteEdit">Cancel</Button>
                            </div>
                        </form>
                    </section>

                    <section class="rounded-xl border border-border/70 bg-card p-4 shadow-sm sm:p-5">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold">Child Password</h3>
                                <p class="text-sm text-muted-foreground">Set a new login password for this child account. This does not change your parent password.</p>
                            </div>
                            <Button v-if="!isEditingPassword" variant="outline" size="sm" @click="isEditingPassword = true">Change Child Password</Button>
                        </div>

                        <form v-if="isEditingPassword" class="space-y-3" @submit.prevent="savePassword">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <FormInputField id="child-password" v-model="passwordForm.password" label="New child password" type="password" :error="passwordForm.errors.password" />
                                    <InputError :message="passwordForm.errors.password" />
                                </div>
                                <div class="grid gap-2">
                                    <FormInputField id="child-password-confirmation" v-model="passwordForm.password_confirmation" label="Confirm new password" type="password" :error="passwordForm.errors.password_confirmation" />
                                    <InputError :message="passwordForm.errors.password_confirmation" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Button type="submit" size="sm" :disabled="passwordForm.processing">Save Child Password</Button>
                                <Button type="button" variant="outline" size="sm" @click="cancelPasswordEdit">Cancel</Button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
