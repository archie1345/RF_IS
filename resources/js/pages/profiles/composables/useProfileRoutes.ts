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
            (isSettingsContext.value ? profileUpdate.url() : userAccountUpdate.url(options.user.id)),
    );
    const profileUpdateUrl = computed(
        () =>
            options.profileUpdateUrl ??
            (isSettingsContext.value ? profileDetailsUpdate.url() : userProfileUpdate.url(options.user.id)),
    );
    const certificationStoreUrl = computed(
        () =>
            options.certificationStoreUrl ??
            (isSettingsContext.value ? profileCertificationStore.url() : userCertificationStore.url(options.user.id)),
    );
    const achievementStoreUrl = computed(
        () =>
            options.achievementStoreUrl ??
            (isSettingsContext.value ? profileAchievementStore.url() : userAchievementStore.url(options.user.id)),
    );

    const certificationUpdateUrl = (id: number | string) =>
        options.context === 'settings'
            ? profileCertificationUpdate.url(id)
            : userCertificationUpdate.url({ user: options.user.id, certification: id });

    const achievementUpdateUrl = (id: number | string) =>
        options.context === 'settings'
            ? profileAchievementUpdate.url(id)
            : userAchievementUpdate.url({ user: options.user.id, achievement: id });

    const athleteProfileUpdateUrl = computed(() => userAthleteProfileUpdate.url(options.user.id));
    const coachProfileUpdateUrl = computed(() => userCoachProfileUpdate.url(options.user.id));
    const parentProfileUpdateUrl = computed(() => userParentProfileUpdate.url(options.user.id));

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
