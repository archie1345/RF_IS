<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import FormInputField from '@/components/forms/FormInputField.vue';
import FormSelectField from '@/components/forms/FormSelectField.vue';
import DataTable from '@/components/shared/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    certifications: Array<{
        id: string;
        cert_type: string;
        title: string;
        issuer: string;
        certified_at: string;
        expires_at: string;
        notes: string;
    }>;
};

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;

const certForm = useForm({
    cert_type: 'BELT',
    title: '',
    issuer: '',
    certified_at: '',
    expires_at: '',
    notes: '',
    file: null as File | null,
});

const certColumns = [
    { key: 'cert_type', label: 'Type' },
    { key: 'title', label: 'Title' },
    { key: 'issuer', label: 'Issuer' },
    { key: 'certified_at', label: 'Certified' },
    { key: 'expires_at', label: 'Expires' },
    { key: 'notes', label: 'Notes' },
];

function onCertificationFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    certForm.file = target.files?.[0] ?? null;
}

function addCertification() {
    certForm.post('/settings/profile/certifications', { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <h1 class="sr-only">Profile Settings</h1>

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Profile information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="gender">Gender</Label>
                        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="">-</option>
                            <option value="MALE" :selected="user.gender === 'MALE'">Male</option>
                            <option value="FEMALE" :selected="user.gender === 'FEMALE'">Female</option>
                        </select>
                        <InputError class="mt-2" :message="errors.gender" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bday">Birth date</Label>
                        <Input id="bday" type="date" name="bday" :default-value="String(user.bday ?? '')" />
                        <InputError class="mt-2" :message="errors.bday" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" type="text" name="phone" :default-value="String(user.phone ?? '')" />
                        <InputError class="mt-2" :message="errors.phone" />
                    </div>

                    <div class="text-sm">
                        <Link href="/achievements" class="underline underline-offset-2">Open achievements page</Link>
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
                        >

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>
            </div>

            <DeleteUser />

            <div class="rounded-2xl border border-border/70 bg-card/80 p-5 shadow-sm">
                <Heading
                    variant="small"
                    title="Certifications"
                    description="Add belt, referee, or trainer certifications. Certification records stay in profile."
                />

                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="addCertification">
                    <FormSelectField id="profile-cert-type" v-model="certForm.cert_type" label="Type" :options="[{ value: 'BELT', label: 'Belt' }, { value: 'REFEREE', label: 'Referee' }, { value: 'TRAINER', label: 'Trainer' }]" :error="certForm.errors.cert_type" />
                    <FormInputField id="profile-cert-title" v-model="certForm.title" label="Title" :error="certForm.errors.title" />
                    <FormInputField id="profile-cert-issuer" v-model="certForm.issuer" label="Issuer" :error="certForm.errors.issuer" />
                    <FormInputField id="profile-cert-date" v-model="certForm.certified_at" label="Certified at" type="date" :error="certForm.errors.certified_at" />
                    <FormInputField id="profile-cert-expire" v-model="certForm.expires_at" label="Expires at" type="date" :error="certForm.errors.expires_at" />
                    <FormInputField id="profile-cert-notes" v-model="certForm.notes" label="Notes" :error="certForm.errors.notes" />
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="profile-cert-file">Attach file (optional)</Label>
                        <input id="profile-cert-file" type="file" class="h-10 rounded-md border border-input px-3 py-2 text-sm" @change="onCertificationFileChange">
                        <InputError :message="certForm.errors.file" />
                    </div>
                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="certForm.processing">Add certification</Button>
                    </div>
                </form>

                <div class="mt-6">
                    <DataTable
                        title="Certification table"
                        description="Your certification records"
                        :columns="certColumns"
                        :rows="props.certifications"
                    />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

