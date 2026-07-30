<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, MessageCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { buildWhatsAppUrl } from '@/lib/whatsapp';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const page = usePage<{ publicAdminWhatsapp?: string }>();

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const contactAdminUrl = computed(() =>
    buildWhatsAppUrl(
        page.props.publicAdminWhatsapp,
        'Halo Admin Rhino Fighter, saya ingin membuat akun RF IS. Mohon bantuan untuk proses pendaftaran akun.',
    ),
);
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <Link
            :href="home()"
            class="mb-5 inline-flex w-fit items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Back to home
        </Link>

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-brand-lime">
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5">
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="processing" data-test="login-button">
                    <Spinner v-if="processing" />
                    Log in
                </Button>
            </div>

            <div class="rounded-xl border bg-muted/30 p-4 text-center text-sm">
                <p class="font-medium text-foreground">Need an account?</p>
                <a
                    v-if="contactAdminUrl"
                    :href="contactAdminUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2 inline-flex items-center gap-2 font-semibold text-emerald-700 underline underline-offset-4 dark:text-emerald-300"
                >
                    <MessageCircle class="size-4" />
                    Contact admin to create your account
                </a>
                <p v-else class="mt-2 text-muted-foreground">
                    Account creation is handled by the administrator. Ask an RF IS administrator to register you.
                </p>
            </div>
        </Form>
    </AuthBase>
</template>
