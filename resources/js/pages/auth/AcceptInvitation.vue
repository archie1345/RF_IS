<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { accept as invitationAccept } from '@/routes/invitations';

const props = defineProps<{
    token: string;
    email: string;
    name?: string | null;
    expiresAt?: string | null;
}>();

const form = useForm({
    password: '',
    password_confirmation: '',
});
const invitationError = computed(() => (form.errors as Record<string, string | undefined>).invitation);

function submit() {
    form.post(invitationAccept.url(props.token), {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout title="Accept invitation" description="Set your password to activate your RF IS account.">
        <Head title="Accept invitation" />

        <div class="rounded-lg border bg-muted/30 p-4 text-sm">
            <p><strong>Name:</strong> {{ props.name ?? 'Invited user' }}</p>
            <p><strong>Email:</strong> {{ props.email }}</p>
            <p v-if="props.expiresAt"><strong>Expires:</strong> {{ props.expiresAt }}</p>
        </div>

        <form class="mt-6 flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <InputError :message="invitationError" />

            <Button type="submit" class="w-full" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Activate account
            </Button>
        </form>

        <div class="mt-6 text-center text-sm text-muted-foreground">
            Already active?
            <TextLink :href="login()" class="underline underline-offset-4">Log in</TextLink>
        </div>
    </AuthLayout>
</template>
