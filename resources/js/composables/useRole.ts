import { computed, type Ref } from 'vue';
import type { AppRole } from '@/types/domain';

export function useRole(role: Ref<AppRole> | AppRole) {
    const roleValue = computed(() => (typeof role === 'string' ? role : role.value));

    return {
        role: roleValue,
        isAdmin: computed(() => roleValue.value === 'admin'),
        isCoach: computed(() => roleValue.value === 'coach'),
        isParent: computed(() => roleValue.value === 'parent'),
        isAthlete: computed(() => roleValue.value === 'athlete'),
    };
}
