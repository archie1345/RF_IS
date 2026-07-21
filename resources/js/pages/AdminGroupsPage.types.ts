
export type TrainingGroupRecord = {
    id: number;
    name: string;
    description?: string | null;
    is_active: boolean;
    classes_count: number;
    athletes_count: number;
};
