<script setup lang="ts">
import { CalendarDays, Megaphone, Pencil, Trash2, UserRound, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { TableBadgeCell, TableRow } from '@/types/resource-table';

const props = withDefaults(
    defineProps<{
        announcement: TableRow;
        compact?: boolean;
        editable?: boolean;
    }>(),
    {
        compact: false,
        editable: false,
    },
);

const emit = defineEmits<{
    edit: [announcement: TableRow];
    remove: [announcement: TableRow];
}>();

const title = computed(() => String(props.announcement.title ?? 'Tanpa judul'));
const message = computed(() => String(props.announcement.message ?? ''));
const audience = computed(() => String(props.announcement.target ?? props.announcement.audience ?? 'Semua pengguna'));
const author = computed(() => String(props.announcement.author ?? 'Sistem'));
const published = computed(() => String(props.announcement.published ?? '-'));
const status = computed(() => {
    const value = props.announcement.status;

    if (value && typeof value === 'object' && 'text' in value) {
        return value as TableBadgeCell;
    }

    return { kind: 'badge', text: String(value ?? 'Diterbitkan'), tone: 'success' } as TableBadgeCell;
});

const statusClass = computed(
    () =>
        ({
            success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            warning: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
            danger: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
            info: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
            neutral: 'bg-muted text-muted-foreground',
        })[status.value.tone ?? 'neutral'],
);
</script>

<template>
    <article
        class="group relative overflow-hidden rounded-2xl border bg-card shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
        :class="props.compact ? 'p-4' : 'p-5 sm:p-6'"
    >
        <div class="absolute inset-x-0 top-0 h-1 bg-primary/70" />

        <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Megaphone class="size-5" />
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 :class="props.compact ? 'text-sm font-semibold' : 'text-lg font-bold'">
                        {{ title }}
                    </h2>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass">
                        {{ status.text }}
                    </span>
                </div>

                <p
                    class="mt-3 text-sm leading-6 text-muted-foreground"
                    :class="props.compact ? 'line-clamp-2' : 'whitespace-pre-line'"
                >
                    {{ message }}
                </p>

                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5">
                        <UsersRound class="size-3.5" />{{ audience }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <CalendarDays class="size-3.5" />{{ published }}
                    </span>
                    <span v-if="!props.compact" class="inline-flex items-center gap-1.5">
                        <UserRound class="size-3.5" />{{ author }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="props.editable && !props.compact" class="mt-5 flex flex-wrap justify-end gap-2 border-t pt-4">
            <Button type="button" size="sm" variant="outline" @click="emit('edit', props.announcement)">
                <Pencil class="mr-2 size-4" />Ubah
            </Button>
            <Button type="button" size="sm" variant="destructive" @click="emit('remove', props.announcement)">
                <Trash2 class="mr-2 size-4" />Hapus
            </Button>
        </div>
    </article>
</template>
