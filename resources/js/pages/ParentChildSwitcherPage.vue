<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';
import PageSection from '@/components/shared/PageSection.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as parentChildrenIndex } from '@/routes/parent/children';
import { show as userShow } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    children: Array<{
        athlete_id: string;
        user_id: number;
        name: string;
        email: string;
        branch: string;
        group: string;
        is_active?: boolean;
    }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Beranda', href: dashboard.url() },
    { title: 'Profil Anak', href: parentChildrenIndex.url() },
];
</script>

<template>
    <Head title="Profil Anak" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
            <PageSection
                eyebrow="Keluarga"
                title="Pilih profil anak"
                description="Halaman lain menampilkan data semua anak secara bersamaan dan menyediakan filter anak. Pilihan di sini hanya membuka profil anak yang ingin Anda lihat."
            >
                <template #actions>
                    <Button as-child type="button" variant="outline">
                        <Link :href="dashboard.url()">Kembali ke beranda</Link>
                    </Button>
                </template>
            </PageSection>

            <div v-if="props.children.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="child in props.children"
                    :key="child.athlete_id"
                    class="rounded-xl border border-border/70 bg-card p-5 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-primary/10 p-2 text-primary"><UserRound class="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-lg font-bold">{{ child.name }}</h2>
                            <p class="truncate text-sm text-muted-foreground">{{ child.email }}</p>
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Cabang</dt>
                            <dd class="text-right font-medium">{{ child.branch }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted-foreground">Kelas</dt>
                            <dd class="text-right font-medium">{{ child.group }}</dd>
                        </div>
                    </dl>

                    <Button as-child class="mt-5 w-full">
                        <Link :href="userShow.url(child.user_id)">Buka profil {{ child.name }}</Link>
                    </Button>
                </article>
            </div>

            <div v-else class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                Belum ada profil anak yang terhubung ke akun ini.
            </div>
        </div>
    </AppLayout>
</template>
