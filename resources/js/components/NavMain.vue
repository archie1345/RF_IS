<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useI18n } from '@/composables/useI18n';
import type { NavItem, NavSection } from '@/types';

const props = withDefaults(
    defineProps<{
        items?: NavItem[];
        sections?: NavSection[];
    }>(),
    {
        items: () => [],
        sections: () => [],
    },
);

const { isCurrentUrl } = useCurrentUrl();
const { t } = useI18n();

const visibleSections = computed<NavSection[]>(() => {
    if (props.sections.length > 0) return props.sections.filter((section) => section.items.length > 0);
    return props.items.length > 0 ? [{ label: 'Menu', items: props.items }] : [];
});

function hrefText(href: unknown): string {
    return typeof href === 'string' ? href : String(href ?? '');
}
</script>

<template>
    <SidebarGroup v-for="section in visibleSections" :key="section.label" class="px-2 py-1">
        <SidebarGroupLabel>{{ t(section.label) }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem
                v-for="item in section.items"
                :key="`${section.label}-${item.title}-${hrefText(item.href)}`"
            >
                <SidebarMenuButton
                    v-if="item.href"
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="t(item.title)"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ t(item.title) }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
