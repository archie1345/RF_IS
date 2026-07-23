<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { update as roleContextUpdate } from '@/routes/role-context';
import type { NavItem, NavSection } from '@/types';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/resource-table';

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

const page = usePage<{ auth: Auth }>();
const { isCurrentUrl } = useCurrentUrl();
const switchingHref = ref<string | null>(null);

const activeRole = computed<AppRole>(() =>
    page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete',
);
const assignedRoles = computed<AppRole[]>(() => {
    const roles = page.props.auth.user?.roles ?? [];
    return roles.length > 0 ? roles : [activeRole.value];
});

const visibleSections = computed<NavSection[]>(() => {
    if (props.sections.length > 0) return props.sections.filter((section) => section.items.length > 0);
    return props.items.length > 0 ? [{ label: 'Menu', items: props.items }] : [];
});

function hrefText(item: NavItem): string {
    return typeof item.href === 'string' ? item.href : String(item.href);
}

function requiredRole(item: NavItem): AppRole | null {
    if (!item.roles?.length || item.roles.includes(activeRole.value)) return null;

    return item.roles.find((role) => assignedRoles.value.includes(role)) ?? null;
}

function openItem(event: MouseEvent, item: NavItem): void {
    const role = requiredRole(item);
    if (!role || switchingHref.value) return;

    event.preventDefault();
    const destination = hrefText(item);
    switchingHref.value = destination;

    router.put(
        roleContextUpdate.url(),
        { role, redirect_to: destination },
        {
            preserveScroll: false,
            preserveState: false,
            onFinish: () => {
                switchingHref.value = null;
            },
        },
    );
}
</script>

<template>
    <SidebarGroup v-for="section in visibleSections" :key="section.label" class="px-2 py-1">
        <SidebarGroupLabel>{{ section.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in section.items" :key="`${section.label}-${item.title}-${hrefText(item)}`">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    :aria-busy="switchingHref === hrefText(item)"
                >
                    <Link :href="item.href" @click="openItem($event, item)">
                        <LoaderCircle v-if="switchingHref === hrefText(item)" class="animate-spin" />
                        <component :is="item.icon" v-else-if="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
