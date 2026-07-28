<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import RoleSwitcher from '@/components/RoleSwitcher.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-14 min-w-0 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-3 transition-[width,height] ease-linear sm:h-16 sm:px-4"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <Breadcrumbs
                v-if="breadcrumbs && breadcrumbs.length > 0"
                class="min-w-0 flex-1"
                :breadcrumbs="breadcrumbs"
            />
        </div>

        <RoleSwitcher class="ml-auto shrink-0" />
    </header>
</template>
