<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft,MessageCircle } from 'lucide-vue-next';
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
import { computed } from 'vue';

const page = usePage<{ publicAdminWhatsapp?: string }>();

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const defaultPhone = '08813323088';
const whatsappUrl = computed(() => {
    const phone = page.props.publicAdminWhatsapp || defaultPhone;
    return buildWhatsAppUrl(phone, 'Halo, saya ingin bertanya mengenai Rhino Fighter Taekwondo.');
});
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <Link
            :href="home()"
            class="mb-1 inline-flex w-fit items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
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
            <div class="grid gap-4">
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
        </Form>
        <a
        :href="whatsappUrl ?? '#'"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Hubungi admin melalui WhatsApp"
        title="Hubungi admin melalui WhatsApp"
        class="fixed right-5 bottom-5 z-50 inline-flex size-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xl ring-1 ring-white/20 transition hover:scale-105 hover:bg-emerald-400 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none sm:right-7 sm:bottom-7"
    >
        <MessageCircle class="size-7" aria-hidden="true" />
    </a>
    </AuthBase>
</template>
