<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useI18n } from '@/composables/useI18n';
import { getInitials } from '@/composables/useInitials';
import { getVisibleSections, navigationSections } from '@/lib/navigation';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import type { AppRole } from '@/types/resource-table';
import type { Props } from './AppHeader.types';

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const assignedRoles = computed<AppRole[]>(() => {
    const roles = auth.value?.user?.roles ?? [];
    return roles.length > 0 ? roles : [auth.value?.user?.role ?? 'athlete'];
});
const visibleNavSections = computed(() => getVisibleSections(navigationSections, assignedRoles.value));

const { whenCurrentUrl } = useCurrentUrl();
const { t } = useI18n();

const activeItemStyles = 'bg-accent text-accent-foreground';

const rightNavItems: NavItem[] = [];
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-11 w-11"
                                :aria-label="t('Open navigation menu')"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="flex h-full w-[300px] flex-col p-6">
                            <SheetTitle class="sr-only">{{ t('Navigation Menu') }}</SheetTitle>
                            <SheetHeader class="mb-4 flex shrink-0 justify-start text-left">
                                <AppLogoIcon class="h-10 w-auto" />
                            </SheetHeader>
                            <div
                                class="-mx-2 flex min-h-0 flex-1 scrollbar-none flex-col justify-between overflow-x-hidden overflow-y-auto px-2"
                            >
                                <nav class="space-y-4">
                                    <div v-for="section in visibleNavSections" :key="section.label">
                                        <div
                                            class="px-3 pb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{ t(section.label) }}
                                        </div>
                                        <div class="space-y-1">
                                            <Link
                                                v-for="item in section.items"
                                                :key="item.title"
                                                :href="item.href || '#'"
                                                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                                :class="whenCurrentUrl(item.href || '#', activeItemStyles)"
                                            >
                                                <component v-if="item.icon" :is="item.icon" class="size-5 shrink-0" />
                                                <span class="truncate">{{ t(item.title) }}</span>
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
                                        <span>{{ t(item.title) }}</span>
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
                <div class="ml-10 hidden h-full lg:flex lg:flex-1">
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
                                        class="h-9 gap-1 px-3 text-sm font-medium"
                                        :class="{
                                            'bg-accent text-accent-foreground': section.items.some((item) =>
                                                whenCurrentUrl(item.href || '#', 'yes'),
                                            ),
                                        }"
                                    >
                                        {{ t(section.label) }}
                                        <ChevronDown class="h-4 w-4 opacity-50" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start" class="w-[400px] p-4 md:w-[500px] lg:w-[600px]">
                                    <ul class="grid gap-3 md:grid-cols-2">
                                        <li v-for="item in section.items" :key="item.title" class="min-w-0">
                                            <DropdownMenuItem as-child class="p-0">
                                                <Link
                                                    :href="item.href || '#'"
                                                    class="block w-full min-w-0 cursor-pointer space-y-1 rounded-md p-3 leading-none no-underline transition-colors outline-none select-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                                    :class="
                                                        whenCurrentUrl(
                                                            item.href || '#',
                                                            'bg-accent text-accent-foreground',
                                                        )
                                                    "
                                                >
                                                    <div
                                                        class="flex items-center gap-2 overflow-hidden text-sm leading-none font-medium"
                                                    >
                                                        <component
                                                            v-if="item.icon"
                                                            :is="item.icon"
                                                            class="h-4 w-4 shrink-0"
                                                        />
                                                        <span class="truncate">{{ t(item.title) }}</span>
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
                                    <component
                                        v-if="section.items[0].icon"
                                        :is="section.items[0].icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ t(section.label) }}
                                </Link>
                            </Button>
                        </div>
                    </nav>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <div class="relative flex items-center space-x-1">
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
                                aria-label="Buka menu pengguna"
                                data-test="user-menu-trigger"
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
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
