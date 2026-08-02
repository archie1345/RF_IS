<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem, NavSection } from '@/types';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/resource-table';
import { getVisibleSections, navigationSections } from '@/lib/navigation';

const page = usePage<{ auth: Auth }>();
const activeRole = computed<AppRole>(() => page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete');
const assignedRoles = computed<AppRole[]>(() => {
    const roles = page.props.auth.user?.roles ?? [];
    return roles.length > 0 ? roles : [activeRole.value];
});

const mainNavSections = computed<NavSection[]>(() => getVisibleSections(navigationSections, assignedRoles.value));
const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar>
        <SidebarHeader class="min-w-0 p-2 border-b border-sidebar-border/40 shrink-0">
            <Link
                :href="dashboard.url()"
                class="flex h-12 w-full min-w-0 items-center justify-center rounded-md px-2 transition group-data-[collapsible=icon]:h-10 group-data-[collapsible=icon]:w-10 group-data-[collapsible=icon]:self-center group-data-[collapsible=icon]:p-0 hover:bg-sidebar-accent"
            >
                <AppLogoIcon
                    class-name="block h-8 w-auto max-w-full shrink-0 object-contain group-data-[collapsible=icon]:h-6 group-data-[collapsible=icon]:w-6"
                />
                <span class="sr-only">RF IS dashboard</span>
            </Link>
        </SidebarHeader>

        <SidebarContent class="py-2"><NavMain :sections="mainNavSections" /></SidebarContent>
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
