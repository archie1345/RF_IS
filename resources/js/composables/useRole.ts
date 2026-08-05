import { usePage } from '@inertiajs/vue3';
import { computed, type Ref } from 'vue';
import type { Auth } from '@/types/auth';
import type { AppRole } from '@/types/domain';

export function useRole(role?: Ref<AppRole> | AppRole) {
    const page = usePage<{ auth: Auth }>();
    const roleValue = computed<AppRole>(() => {
        if (role) {
            return typeof role === 'string' ? role : role.value;
        }

        return page.props.auth.user?.activeRole ?? page.props.auth.user?.role ?? 'athlete';
    });
    const availableRoles = computed<AppRole[]>(() => page.props.auth.user?.roles ?? [roleValue.value]);

    return {
        role: roleValue,
        availableRoles,
        isMultiRole: computed(() => availableRoles.value.length > 1),
        hasAssignedRole: (candidate: AppRole) => availableRoles.value.includes(candidate),
        isAdmin: computed(() => roleValue.value === 'admin'),
        isCoach: computed(() => roleValue.value === 'coach'),
        isParent: computed(() => roleValue.value === 'parent'),
        isAthlete: computed(() => roleValue.value === 'athlete'),
    };
}
