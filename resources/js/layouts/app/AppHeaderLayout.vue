<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';

import type { Props } from './AppHeaderLayout.types';

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div v-translate>
        <AppShell class="flex-col">
            <AppHeader :breadcrumbs="breadcrumbs" />
            <AppContent>
                <slot />
            </AppContent>
        </AppShell>
    </div>
</template>
