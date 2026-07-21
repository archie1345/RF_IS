
export type SessionVisibility = 'upcoming' | 'archived' | 'all';

export type SessionFilters = {
    visibility: SessionVisibility | 'past';
    archived_count?: number;
    past_count?: number;
    upcoming_count: number;
    all_count: number;
};
