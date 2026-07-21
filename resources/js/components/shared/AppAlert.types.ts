
export type AlertTone = 'info' | 'success' | 'warning' | 'danger' | 'neutral';

export type AlertAction = {
    label: string;
    variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    disabled?: boolean;
};
