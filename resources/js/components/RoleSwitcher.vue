<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update as roleContextUpdate } from '@/routes/role-context';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/resource-table';

const page = usePage<{ auth: Auth }>();
const isSwitching = ref(false);

const roles = computed<AppRole[]>(() => page.props.auth.user?.roles ?? []);
const activeRole = computed<AppRole>(() => page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete');
const isMultiRole = computed(() => roles.value.length > 1);

const roleLabels: Record<AppRole, string> = {
    admin: 'Admin',
    coach: 'Coach',
    parent: 'Parent',
    athlete: 'Athlete',
};

function switchRole(role: AppRole): void {
    if (role === activeRole.value || isSwitching.value) return;

    router.put(
        roleContextUpdate.url(),
        { role },
        {
            preserveScroll: false,
            preserveState: false,
            onStart: () => {
                isSwitching.value = true;
            },
            onFinish: () => {
                isSwitching.value = false;
            },
        },
    );
}
</script>

<template>
    <DropdownMenu v-if="isMultiRole">
        <DropdownMenuTrigger as-child>
            <Button type="button" variant="outline" size="sm" class="gap-2" :disabled="isSwitching">
                <UsersRound class="size-4" />
                <span class="hidden sm:inline">{{ roleLabels[activeRole] }} mode</span>
                <span class="sm:hidden">{{ roleLabels[activeRole] }}</span>
                <ChevronDown class="size-3.5 opacity-70" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuLabel>Work as</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="role in roles"
                :key="role"
                :disabled="isSwitching"
                class="cursor-pointer"
                @select.prevent="switchRole(role)"
            >
                <Check class="mr-2 size-4" :class="role === activeRole ? 'opacity-100' : 'opacity-0'" />
                {{ roleLabels[role] }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <div
        v-else
        class="inline-flex h-9 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm font-medium"
        aria-label="Current account role"
    >
        <UsersRound class="size-4" />
        {{ roleLabels[activeRole] }}
    </div>
</template>
