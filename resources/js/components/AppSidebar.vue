<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    CalendarCheck2,
    CalendarRange,
    CreditCard,
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
import { managementRoutes } from '@/data/mvp';
import type { Auth } from '@/types/auth';
import { type NavItem } from '@/types';
import type { AppRole } from '@/types/mvp';
import AppLogo from './AppLogo.vue';

const page = usePage<{ auth: Auth }>();

const role = computed<AppRole>(() => {
    const userRole = page.props.auth?.user?.role;

    return userRole === 'admin' ||
        userRole === 'coach' ||
        userRole === 'parent' ||
        userRole === 'athlete'
        ? userRole
        : 'athlete';
});

const navByRole: Record<AppRole, NavItem[]> = {
    admin: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Athletes', href: managementRoutes.athletes, icon: Users },
        { title: 'Payments', href: managementRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
        { title: 'Coach Sessions', href: managementRoutes.sessions, icon: CalendarRange },
    ],
    coach: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
        { title: 'Coach Sessions', href: managementRoutes.sessions, icon: CalendarRange },
    ],
    parent: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Payments', href: managementRoutes.payments, icon: CreditCard },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
    ],
    athlete: [
        { title: 'Dashboard', href: managementRoutes.dashboard, icon: LayoutGrid },
        { title: 'Attendance', href: managementRoutes.attendance, icon: CalendarCheck2 },
        { title: 'Championships', href: managementRoutes.championships, icon: Trophy },
    ],
};

const mainNavItems = computed(() => navByRole[role.value]);

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
