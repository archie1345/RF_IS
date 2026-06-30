import type { ProfileAchievement, ProfileCertification } from '@/pages/profiles/types';
import type { TableColumn, TableRow } from '@/types/resource-table';

export const certificationColumns: TableColumn[] = [
    { key: 'cert_type', label: 'Type' },
    { key: 'title', label: 'Title' },
    { key: 'issuer', label: 'Issuer' },
    { key: 'certified_at', label: 'Certified' },
    { key: 'expires_at', label: 'Expires' },
    { key: 'notes', label: 'Notes' },
    { key: 'file_name', label: 'File' },
];

export const achievementColumns: TableColumn[] = [
    { key: 'championship_name', label: 'Championship' },
    { key: 'medal', label: 'Medal' },
    { key: 'location', label: 'Location' },
    { key: 'event_date', label: 'Date' },
    { key: 'class_name', label: 'Class' },
    { key: 'division', label: 'Division' },
    { key: 'category', label: 'Category' },
    { key: 'notes', label: 'Notes' },
    { key: 'file_name', label: 'File' },
];

export function certificationRows(certifications: ProfileCertification[]): TableRow[] {
    return certifications.map((cert) => ({
        id: String(cert.id),
        cert_type: cert.cert_type,
        title: cert.title,
        issuer: cert.issuer ?? '-',
        certified_at: cert.certified_at ?? '-',
        expires_at: cert.expires_at ?? '-',
        notes: cert.notes ?? '-',
        file_name: cert.fileName ?? '-',
        file_url: cert.fileUrl ?? '',
    }));
}

export function achievementRows(achievements: ProfileAchievement[]): TableRow[] {
    return achievements.map((ach) => ({
        id: String(ach.id),
        championship_name: ach.championship_name,
        medal: ach.medal,
        location: ach.location ?? '-',
        event_date: ach.event_date ?? '-',
        class_name: ach.class_name ?? '-',
        division: ach.division ?? '-',
        category: ach.category ?? '-',
        notes: ach.notes ?? '-',
        file_name: ach.fileName ?? '-',
        file_url: ach.fileUrl ?? '',
    }));
}
