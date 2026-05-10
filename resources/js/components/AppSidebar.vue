<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Blocks,
    CalendarCheck2,
    CreditCard,
    FileClock,
    LayoutGrid,
    Trophy,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { managementRoutes } from '@/data/management';
import type { Auth } from '@/types/auth';
import { type NavItem } from '@/types';
import type { AppRole } from '@/types/management';
import AppLogo from './AppLogo.vue';

const page = usePage<{ auth: Auth }>();

const roles = computed<AppRole[]>(() => {
    const assignedRoles = page.props.auth?.user?.roles ?? [];
    const validRoles = assignedRoles.filter((item): item is AppRole => ['admin', 'coach', 'parent', 'athlete'].includes(item));

    if (validRoles.length > 0) {
        return validRoles;
    }

    const userRole = page.props.auth?.user?.role;
    return userRole === 'admin' || userRole === 'coach' || userRole === 'parent' || userRole === 'athlete' ? [userRole] : ['athlete'];
});

const navByRole: Record<AppRole, NavItem[]> = {
    admin: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Admin Panel', href: '/admin', icon: BadgeCheck },
        { title: 'Components Playground', href: managementRoutes.componentsPlayground, icon: Blocks },
        { title: 'Athletes', href: managementRoutes.athletes, icon: Users },
        { title: 'Coaches & Parents', href: managementRoutes.coachParentManagement, icon: Users },
        { title: 'Role Users', href: managementRoutes.roleUsers, icon: Users },
        { title: 'Payments', href: managementRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: managementRoutes.sessions, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
        { title: 'Announcements', href: managementRoutes.announcements, icon: FileClock },
        { title: 'User Activity Log', href: managementRoutes.activityLogs, icon: FileClock },
    ],
    coach: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Payments', href: managementRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
        { title: 'Achievements', href: managementRoutes.achievements, icon: Trophy },
        { title: 'Announcements', href: managementRoutes.announcements, icon: FileClock },
    ],
    parent: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Switch Child', href: managementRoutes.parentChildSwitcher, icon: Users },
        { title: 'Payments', href: managementRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
        { title: 'Achievements', href: managementRoutes.achievements, icon: Trophy },
        { title: 'Announcements', href: managementRoutes.announcements, icon: FileClock },
    ],
    athlete: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Payments', href: managementRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
        { title: 'Achievements', href: managementRoutes.achievements, icon: Trophy },
        { title: 'Announcements', href: managementRoutes.announcements, icon: FileClock },
    ],
};

const mainNavItems = computed(() => {
    const seen = new Set<string>();

    return roles.value
        .flatMap((entry) => navByRole[entry])
        .filter((item) => {
            const href = String(item.href);
            if (seen.has(href)) {
                return false;
            }
            seen.add(href);
            return true;
        });
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="managementRoutes.dashboard">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

