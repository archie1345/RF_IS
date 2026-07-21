declare module '@/routes/users' {
    export const show: { url: (params?: unknown) => string };
    export const index: { url: (params?: unknown) => string };
}

declare module '@/routes/attendance' {
    export const update: { url: (params?: unknown) => string };
    export const bulkUpdate: { url: (params?: unknown) => string };
    export const index: { url: (params?: unknown) => string };
    export const store: { url: (params?: unknown) => string };
}

declare module '@/routes/championships' {
    export const show: { url: (params?: unknown) => string };
    export const index: { url: (params?: unknown) => string };
    export const exportMethod: { url: (params?: unknown) => string };
}

declare module '@/routes/profile/certifications' {
    export const store: { url: (params?: unknown) => string };
    export const update: { url: (params?: unknown) => string };
}

declare module '@/routes/users/certifications' {
    export const store: { url: (params?: unknown) => string };
    export const update: { url: (params?: unknown) => string };
}

declare module '@/routes/profile/achievements' {
    export const store: { url: (params?: unknown) => string };
    export const update: { url: (params?: unknown) => string };
}

declare module '@/routes/users/achievements' {
    export const store: { url: (params?: unknown) => string };
    export const update: { url: (params?: unknown) => string };
}

declare module '@/routes/sessions/coach-attendance' {
    export const store: { url: (params?: unknown) => string };
    export const update: { url: (params?: unknown) => string };
    export const destroy: { url: (params?: unknown) => string };
}
