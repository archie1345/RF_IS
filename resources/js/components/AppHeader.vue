<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, Menu, Search, Users, ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { getVisibleSections, navigationSections } from '@/lib/navigation';
import { routeId } from '@/lib/routeIds';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import type { AppRole } from '@/types/resource-table';
import type { Props } from './AppHeader.types';
import { clear as clearChildRoute, switchMethod as switchChildRoute } from '@/routes/parent/children';
import { show as userShow } from '@/routes/users';

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const activeRole = computed<AppRole>(() => auth.value?.user?.activeRole ?? auth.value?.user?.role ?? 'athlete');
const assignedRoles = computed<AppRole[]>(() => {
    const roles = auth.value?.user?.roles ?? [];
    return roles.length > 0 ? roles : [activeRole.value];
});
const visibleNavSections = computed(() => getVisibleSections(navigationSections, assignedRoles.value));

const isParent = computed(() => auth.value?.user?.role === 'parent');
const parentChildren = computed(() => auth.value?.children ?? []);
const activeChild = computed(() => auth.value?.activeChild ?? null);
const isChildPickerOpen = ref(false);
const childSearch = ref('');
const visibleChildrenCount = ref(12);
const { whenCurrentUrl } = useCurrentUrl();

const activeItemStyles = 'bg-accent text-accent-foreground';

const rightNavItems: NavItem[] = [
    
];

const filteredChildren = computed(() => {
    const query = childSearch.value.trim().toLowerCase();

    if (!query) {
        return parentChildren.value;
    }

    return parentChildren.value.filter((child) => child.name.toLowerCase().includes(query));
});

const visibleChildren = computed(() => filteredChildren.value.slice(0, visibleChildrenCount.value));

watch(isChildPickerOpen, (isOpen) => {
    if (!isOpen) {
        childSearch.value = '';
        visibleChildrenCount.value = 12;
    }
});

watch(childSearch, () => {
    visibleChildrenCount.value = 12;
});

function switchChild(athleteId: string | null) {
    if (!athleteId) {
        return;
    }

    isChildPickerOpen.value = false;

    router.post(switchChildRoute.url(), { athlete_id: athleteId }, { preserveScroll: true });
}

function clearChildContext() {
    router.delete(clearChildRoute.url(), { preserveScroll: true });
}

function profileUrl(userId: unknown): string {
    const normalizedUserId = routeId(userId);

    return normalizedUserId === null ? '#' : userShow.url(normalizedUserId);
}

function showMoreChildren() {
    visibleChildrenCount.value += 12;
}
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4 ">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="flex flex-col w-[300px] p-6 h-full">
                            <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                            <SheetHeader class="shrink-0 flex justify-start text-left mb-4">
                                <AppLogoIcon class="h-10 w-auto" />
                            </SheetHeader>
                            <div class="flex-1 flex flex-col justify-between overflow-y-auto overflow-x-hidden scrollbar-none min-h-0 -mx-2 px-2">
                                <nav class="space-y-4">
                                    <div v-for="section in visibleNavSections" :key="section.label">
                                        <div class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ section.label }}</div>
                                        <div class="space-y-1">
                                            <Link
                                                v-for="item in section.items"
                                                :key="item.title"
                                                :href="item.href || '#'"
                                                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                                :class="whenCurrentUrl(item.href || '#', activeItemStyles)"
                                            >
                                                <component v-if="item.icon" :is="item.icon" class="size-5 shrink-0" />
                                                <span class="truncate">{{ item.title }}</span>
                                            </Link>
                                        </div>
                                    </div>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <a
                                        v-for="item in rightNavItems"
                                        :key="item.title"
                                        :href="toUrl(item.href)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        <component v-if="item.icon" :is="item.icon" class="size-5 shrink-0" />
                                        <span>{{ item.title }}</span>
                                    </a>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="dashboard()" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1 ml-10">
                    <nav class="flex h-full items-stretch space-x-2">
                        <div
                            v-for="section in visibleNavSections"
                            :key="section.label"
                            class="relative flex h-full items-center"
                        >
                            <DropdownMenu v-if="section.items.length > 1">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        class="h-9 px-3 text-sm font-medium gap-1"
                                        :class="{
                                            'bg-accent text-accent-foreground': section.items.some(item => whenCurrentUrl(item.href || '#', 'yes'))
                                        }"
                                    >
                                        {{ section.label }}
                                        <ChevronDown class="h-4 w-4 opacity-50" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" class="w-[400px] p-4 md:w-[500px] lg:w-[600px]">
                                    <ul class="grid gap-3 md:grid-cols-2">
                                        <li v-for="item in section.items" :key="item.title" class="min-w-0">
                                            <DropdownMenuItem as-child class="p-0">
                                                <Link
                                                    :href="item.href || '#'"
                                                    class="block w-full min-w-0 select-none space-y-1 rounded-md p-3 leading-none no-underline outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground cursor-pointer"
                                                    :class="whenCurrentUrl(item.href || '#', 'bg-accent text-accent-foreground')"
                                                >
                                                    <div class="flex items-center gap-2 text-sm font-medium leading-none overflow-hidden">
                                                        <component v-if="item.icon" :is="item.icon" class="h-4 w-4 shrink-0" />
                                                        <span class="truncate">{{ item.title }}</span>
                                                    </div>
                                                </Link>
                                            </DropdownMenuItem>
                                        </li>
                                    </ul>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Button
                                v-else-if="section.items.length === 1"
                                variant="ghost"
                                as-child
                                class="h-9 px-3 text-sm font-medium"
                                :class="whenCurrentUrl(section.items[0].href || '#', activeItemStyles)"
                            >
                                <Link :href="section.items[0].href || '#'">
                                    <component v-if="section.items[0].icon" :is="section.items[0].icon" class="mr-2 h-4 w-4" />
                                    {{ section.label }}
                                </Link>
                            </Button>
                        </div>
                    </nav>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <div v-if="isParent && parentChildren.length > 0" class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="hidden h-9 items-center gap-2 lg:inline-flex"
                            @click="isChildPickerOpen = true"
                        >
                            <Users class="size-4" />
                            {{ activeChild?.name ?? 'Select child' }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="lg:hidden"
                            @click="isChildPickerOpen = true"
                        >
                            <Users class="size-5" />
                            <span class="sr-only">Open child picker</span>
                        </Button>
                        <Button v-if="activeChild" type="button" variant="outline" size="sm" @click="clearChildContext">
                            Exit view
                        </Button>
                        <Button
                            v-if="activeChild?.user_id"
                            as-child
                            variant="outline"
                            size="sm"
                            class="hidden lg:inline-flex"
                        >
                            <Link :href="profileUrl(activeChild.user_id)">Profile</Link>
                        </Button>
                    </div>

                    <div class="relative flex items-center space-x-1">
                        <Button variant="ghost" size="icon" class="group h-9 w-9 cursor-pointer">
                            <Search class="size-5 opacity-80 group-hover:opacity-100" />
                        </Button>

                        <div class="hidden space-x-1 lg:flex">
                            <template v-for="item in rightNavItems" :key="item.title">
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                as-child
                                                class="group h-9 w-9 cursor-pointer"
                                            >
                                                <a :href="toUrl(item.href)" target="_blank" rel="noopener noreferrer">
                                                    <span class="sr-only">{{ item.title }}</span>
                                                    <component
                                                        :is="item.icon"
                                                        class="size-5 opacity-80 group-hover:opacity-100"
                                                    />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar class="size-8 overflow-hidden rounded-full">
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-64">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 ">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>

        <Dialog v-model:open="isChildPickerOpen">
            <DialogContent class="w-[calc(100vw-1.5rem)] rounded-xl border-border/70 sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Users class="size-5" />
                        Switch child account
                    </DialogTitle>
                    <DialogDescription> Search and open the child account context you want to view. </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <Input v-model="childSearch" placeholder="Search child name" />

                    <div class="rounded-xl border border-border/70 bg-card">
                        <div v-if="visibleChildren.length > 0" class="max-h-80 space-y-1 overflow-y-auto p-2">
                            <div
                                v-for="child in visibleChildren"
                                :key="child.athlete_id"
                                class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-muted"
                                :class="child.athlete_id === activeChild?.athlete_id ? 'bg-muted' : ''"
                            >
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 py-1 text-left"
                                    @click="switchChild(child.athlete_id)"
                                >
                                    <span class="block truncate font-medium text-foreground">{{ child.name }}</span>
                                    <span
                                        v-if="child.athlete_id === activeChild?.athlete_id"
                                        class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        Active
                                    </span>
                                </button>
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="shrink-0"
                                    @click="isChildPickerOpen = false"
                                >
                                    <Link :href="profileUrl(child.user_id)">Profile</Link>
                                </Button>
                            </div>
                        </div>

                        <div v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
                            No child matches that search.
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">
                            Showing {{ visibleChildren.length }} of {{ filteredChildren.length }} children
                        </p>
                        <Button
                            v-if="visibleChildren.length < filteredChildren.length"
                            type="button"
                            variant="outline"
                            @click="showMoreChildren"
                        >
                            Show more
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
