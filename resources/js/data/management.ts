export const managementRoutes = {
    dashboard: '/dashboard',
    componentsPlayground: '/components-playground',
    activityLogs: '/admin/activity-logs',
    announcements: '/announcements',
    parentChildSwitcher: '/parent/children',
    athletes: '/users',
    coachParentManagement: '/coach-parent-management',
    roleUsers: '/role-users',
    achievements: '/achievements',
    payments: '/payments',
    attendance: '/attendance',
    championships: '/championships',
    sessions: '/sessions',
} as const satisfies Record<string, string>;

