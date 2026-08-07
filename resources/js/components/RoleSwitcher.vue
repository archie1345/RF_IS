<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, LoaderCircle, UsersRound } from '@lucide/vue';
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
const switchingRole = ref<AppRole | null>(null);

const roles = computed<AppRole[]>(() => page.props.auth.user?.roles ?? []);
const activeRole = computed<AppRole>(() => page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete');
const isMultiRole = computed(() => roles.value.length > 1);

const roleLabels: Record<AppRole, string> = {
    admin: 'Admin',
    coach: 'Pelatih',
    parent: 'Orang tua',
    athlete: 'Atlet',
};

function switchRole(role: AppRole): void {
    if (role === activeRole.value || switchingRole.value !== null) return;

    router.put(
        roleContextUpdate.url(),
        { role },
        {
            preserveScroll: false,
            preserveState: false,
            onStart: () => {
                switchingRole.value = role;
            },
            onFinish: () => {
                switchingRole.value = null;
            },
        },
    );
}
</script>

<template>
    <DropdownMenu v-if="isMultiRole">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="max-w-[9.5rem] gap-2 sm:max-w-none"
                :disabled="switchingRole !== null"
                aria-label="Ganti tampilan akun"
            >
                <LoaderCircle v-if="switchingRole" class="size-4 animate-spin" />
                <UsersRound v-else class="size-4" />
                <span class="truncate">{{ roleLabels[activeRole] }}</span>
                <ChevronDown class="size-3.5 shrink-0 opacity-70" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel>
                <span class="block">Tampilkan sebagai</span>
                <span class="mt-0.5 block text-xs font-normal text-muted-foreground">
                    Pilih sesuai tugas yang ingin dilakukan
                </span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="role in roles"
                :key="role"
                :disabled="switchingRole !== null"
                class="min-h-11 cursor-pointer"
                @select.prevent="switchRole(role)"
            >
                <LoaderCircle v-if="switchingRole === role" class="mr-2 size-4 animate-spin" />
                <Check v-else class="mr-2 size-4" :class="role === activeRole ? 'opacity-100' : 'opacity-0'" />
                {{ roleLabels[role] }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <div
        v-else
        class="inline-flex h-9 max-w-[9.5rem] items-center gap-2 rounded-md border border-input bg-background px-3 text-sm font-medium sm:max-w-none"
        aria-label="Peran akun saat ini"
    >
        <UsersRound class="size-4 shrink-0" />
        <span class="truncate">{{ roleLabels[activeRole] }}</span>
    </div>
</template>
