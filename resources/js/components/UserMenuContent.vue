<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings, Globe } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuPortal,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';

import { useI18n } from '@/composables/useI18n';
import { logout } from '@/routes';
import type { Props } from './UserMenuContent.types';

const { t } = useI18n();

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>

    <DropdownMenuSeparator />

    <DropdownMenuSub>
        <DropdownMenuSubTrigger class="cursor-pointer">
            <Globe class="mr-2 h-4 w-4" />
            {{ t('Bahasa / Language') }}
        </DropdownMenuSubTrigger>
        <DropdownMenuPortal>
            <DropdownMenuSubContent>
                <DropdownMenuItem as="a" href="/locale/id" class="cursor-pointer">
                    {{ t('Indonesia') }}
                </DropdownMenuItem>
                <DropdownMenuItem as="a" href="/locale/en" class="cursor-pointer">
                    {{ t('English') }}
                </DropdownMenuItem>
            </DropdownMenuSubContent>
        </DropdownMenuPortal>
    </DropdownMenuSub>

    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" href="/settings" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('Pengaturan') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('Keluar') }}
        </Link>
    </DropdownMenuItem>
</template>
