import type { TableColumn } from '@/types/resource-table';

export const athleteRosterBaseColumns: TableColumn[] = [
    { key: 'athlete', label: 'Athlete' },
    { key: 'account_email', label: 'Email' },
    { key: 'branch', label: 'Branch' },
    { key: 'group', label: 'Group' },
];


export const athleteRosterTrailingColumns: TableColumn[] = [
    { key: 'geup', label: 'Geup' },
    { key: 'status', label: 'Status' },
];

export const coachRosterColumns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'specialization', label: 'Specialization' },
];

export const parentRosterColumns: TableColumn[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'relation', label: 'Relation' },
    { key: 'children', label: 'Children' },
];

export const geupOptions = [
    'GEUP_10',
    'GEUP_9',
    'GEUP_8',
    'GEUP_7',
    'GEUP_6',
    'GEUP_5',
    'GEUP_4',
    'GEUP_3',
    'GEUP_2',
    'GEUP_1',
    'DAN',
];

export const genderOptions = [
    { value: 'MALE', label: 'Male' },
    { value: 'FEMALE', label: 'Female' },
];
