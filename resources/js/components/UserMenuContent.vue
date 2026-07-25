<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, LogOut, Settings, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';

import type { Props } from './UserMenuContent.types';
import { logout } from '@/routes';
import { update as roleContextUpdate } from '@/routes/role-context';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/resource-table';

const page = usePage<{ auth: Auth }>();
const isSwitchingRole = ref(false);
const roles = computed<AppRole[]>(() => page.props.auth.user.roles ?? []);
const activeRole = computed<AppRole>(() =>
    page.props.auth.user.activeRole ?? page.props.auth.user.role ?? 'athlete',
);
const isMultiRole = computed(() => roles.value.length > 1);

const roleLabels: Record<AppRole, string> = {
    admin: 'Admin',
    coach: 'Pelatih',
    parent: 'Orang tua',
    athlete: 'Atlet',
};

const handleLogout = () => {
    router.flushAll();
};

function switchRole(role: AppRole): void {
    if (role === activeRole.value || isSwitchingRole.value) return;

    router.put(
        roleContextUpdate.url(),
        { role },
        {
            preserveState: false,
            preserveScroll: false,
            onStart: () => {
                isSwitchingRole.value = true;
            },
            onFinish: () => {
                isSwitchingRole.value = false;
            },
        },
    );
}

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>

    <template v-if="isMultiRole">
        <DropdownMenuSeparator />
        <DropdownMenuLabel class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
            <UsersRound class="size-4" />
            Gunakan peran
        </DropdownMenuLabel>
        <DropdownMenuGroup>
            <DropdownMenuItem
                v-for="role in roles"
                :key="role"
                :disabled="isSwitchingRole"
                class="cursor-pointer"
                @select.prevent="switchRole(role)"
            >
                <Check class="mr-2 size-4" :class="role === activeRole ? 'opacity-100' : 'opacity-0'" />
                {{ roleLabels[role] }}
            </DropdownMenuItem>
        </DropdownMenuGroup>
    </template>

    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" href="/settings" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Pengaturan
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
            Keluar
        </Link>
    </DropdownMenuItem>
</template>
