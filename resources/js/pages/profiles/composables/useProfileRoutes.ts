import { computed } from 'vue';
import type { ProfileUser } from '@/pages/profiles/types';
import { update as profileUpdate } from '@/routes/profile';
import { store as profileAchievementStore, update as profileAchievementUpdate } from '@/routes/profile/achievements';
import {
    store as profileCertificationStore,
    update as profileCertificationUpdate,
} from '@/routes/profile/certifications';
import { update as profileDetailsUpdate } from '@/routes/profile/details';
import { update as userAccountUpdate } from '@/routes/users/account';
import { store as userAchievementStore, update as userAchievementUpdate } from '@/routes/users/achievements';
import { update as userAthleteProfileUpdate } from '@/routes/users/athlete-profile';
import { store as userCertificationStore, update as userCertificationUpdate } from '@/routes/users/certifications';
import { update as userCoachProfileUpdate } from '@/routes/users/coach-profile';
import { update as userParentProfileUpdate } from '@/routes/users/parent-profile';
import { update as userProfileUpdate } from '@/routes/users/profile';

type ProfileContext = 'admin' | 'settings';

function routeId(value: string | number): number {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

export function useProfileRoutes(options: {
    user: ProfileUser;
    context: ProfileContext;
    accountUpdateUrl?: string;
    profileUpdateUrl?: string;
    certificationStoreUrl?: string;
    achievementStoreUrl?: string;
}) {
    const isSettingsContext = computed(() => options.context === 'settings');
    const userId = computed(() => routeId(options.user.id));

    const accountUpdateUrl = computed(
        () =>
            options.accountUpdateUrl ??
            (isSettingsContext.value ? profileUpdate.url() : userAccountUpdate.url(userId.value)),
    );
    const profileUpdateUrl = computed(
        () =>
            options.profileUpdateUrl ??
            (isSettingsContext.value ? profileDetailsUpdate.url() : userProfileUpdate.url(userId.value)),
    );
    const certificationStoreUrl = computed(
        () =>
            options.certificationStoreUrl ??
            (isSettingsContext.value ? profileCertificationStore.url() : userCertificationStore.url(userId.value)),
    );
    const achievementStoreUrl = computed(
        () =>
            options.achievementStoreUrl ??
            (isSettingsContext.value ? profileAchievementStore.url() : userAchievementStore.url(userId.value)),
    );

    const certificationUpdateUrl = (id: number | string) => {
        const certificationId = routeId(id);

        return options.context === 'settings'
            ? profileCertificationUpdate.url(certificationId)
            : userCertificationUpdate.url({ user: userId.value, certification: certificationId });
    };

    const achievementUpdateUrl = (id: number | string) => {
        const achievementId = routeId(id);

        return options.context === 'settings'
            ? profileAchievementUpdate.url(achievementId)
            : userAchievementUpdate.url({ user: userId.value, achievement: achievementId });
    };

    const athleteProfileUpdateUrl = computed(() => userAthleteProfileUpdate.url(userId.value));
    const coachProfileUpdateUrl = computed(() => userCoachProfileUpdate.url(userId.value));
    const parentProfileUpdateUrl = computed(() => userParentProfileUpdate.url(userId.value));

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
