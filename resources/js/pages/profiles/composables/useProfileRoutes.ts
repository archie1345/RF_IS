import type { ProfileUser } from '@/pages/profiles/types';
import { computed } from 'vue';

type ProfileContext = 'admin' | 'settings';

export function useProfileRoutes(options: {
    user: ProfileUser;
    context: ProfileContext;
    accountUpdateUrl?: string;
    profileUpdateUrl?: string;
    certificationStoreUrl?: string;
    achievementStoreUrl?: string;
}) {
    const isSettingsContext = computed(() => options.context === 'settings');

    const accountUpdateUrl = computed(
        () =>
            options.accountUpdateUrl ??
            (isSettingsContext.value ? '/settings/profile' : `/users/${options.user.id}/account`),
    );
    const profileUpdateUrl = computed(() => options.profileUpdateUrl ?? `/users/${options.user.id}/profile`);
    const certificationStoreUrl = computed(
        () => options.certificationStoreUrl ?? `/users/${options.user.id}/certifications`,
    );
    const achievementStoreUrl = computed(() => options.achievementStoreUrl ?? `/users/${options.user.id}/achievements`);

    const certificationUpdateUrl = (id: number | string) =>
        options.context === 'settings'
            ? `/settings/profile/certifications/${id}`
            : `/users/${options.user.id}/certifications/${id}`;

    const achievementUpdateUrl = (id: number | string) =>
        options.context === 'settings'
            ? `/settings/profile/achievements/${id}`
            : `/users/${options.user.id}/achievements/${id}`;

    const athleteProfileUpdateUrl = computed(() => `/users/${options.user.id}/athlete-profile`);
    const coachProfileUpdateUrl = computed(() => `/users/${options.user.id}/coach-profile`);
    const parentProfileUpdateUrl = computed(() => `/users/${options.user.id}/parent-profile`);

    return {
        isSettingsContext,
        accountUpdateUrl,
        profileUpdateUrl,
        certificationStoreUrl,
        achievementStoreUrl,
        certificationUpdateUrl,
        achievementUpdateUrl,
        athleteProfileUpdateUrl,
        coachProfileUpdateUrl,
        parentProfileUpdateUrl,
    };
}
