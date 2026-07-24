<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Megaphone } from 'lucide-vue-next';
import AnnouncementCard from '@/components/announcements/AnnouncementCard.vue';
import { Button } from '@/components/ui/button';
import { index as announcementsIndex } from '@/routes/announcements';
import type { TableRow } from '@/types/resource-table';

const props = withDefaults(defineProps<{
    announcements?: TableRow[];
}>(), {
    announcements: () => [],
});
</script>

<template>
    <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Megaphone class="size-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Informasi terbaru</p>
                    <h2 class="truncate font-bold">Pengumuman</h2>
                </div>
            </div>
            <Button as-child variant="ghost" size="sm" class="shrink-0 gap-1">
                <Link :href="announcementsIndex.url()">Lihat semua <ArrowRight class="size-3.5" /></Link>
            </Button>
        </div>

        <div v-if="props.announcements.length" class="grid gap-3 lg:grid-cols-3">
            <AnnouncementCard
                v-for="announcement in props.announcements.slice(0, 3)"
                :key="String(announcement.id)"
                :announcement="announcement"
                compact
            />
        </div>

        <div v-else class="rounded-xl border border-dashed p-5 text-center text-sm text-muted-foreground">
            Belum ada pengumuman aktif untuk peran ini.
        </div>
    </section>
</template>
