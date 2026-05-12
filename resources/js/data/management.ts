import type {
    AppRole,
    RoleDashboardContent,
    TableBadgeCell,
    TableRow,
} from '@/types/management';

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

const badge = (text: string, tone: TableBadgeCell['tone'] = 'neutral') => ({
    kind: 'badge' as const,
    text,
    tone,
});

export const dashboardContent: Record<AppRole, RoleDashboardContent> = {
    admin: {
        headline: 'Dashboard',
        description:
            'Keep athlete onboarding, collections, registrations, and training delivery moving from one place.',
        metrics: [
            {
                label: 'Active athletes',
                value: '148',
                detail: '12 new profiles pending review',
                tone: 'success',
            },
            {
                label: 'Payments due',
                value: 'Rp18.4M',
                detail: '8 invoices due this week',
                tone: 'warning',
            },
            {
                label: 'Upcoming events',
                value: '5',
                detail: '2 championship registrations still open',
                tone: 'info',
            },
        ],
        panels: [
            {
                title: 'Athlete intake',
                description:
                    'Review new athlete profiles, assign them to groups, and complete missing parent details.',
                ctaLabel: 'Open athlete management',
                ctaHref: managementRoutes.athletes,
                items: [
                    'Approve 6 new athlete records',
                    'Resolve 3 duplicate parent contacts',
                    'Move 4 athletes into competition groups',
                ],
            },
            {
                title: 'Collections and registrations',
                description:
                    'Track unpaid balances and confirm championship entries before deadlines close.',
                ctaLabel: 'Review finance pipeline',
                ctaHref: managementRoutes.payments,
                items: [
                    'Follow up on April tuition invoices',
                    'Confirm national cup registration fees',
                    'Publish branch attendance summary for coaches',
                ],
            },
        ],
    },
    coach: {
        headline: 'Coaching command center',
        description:
            'Run sessions, watch attendance trends, and keep athletes ready for their next competition block.',
        metrics: [
            {
                label: 'Sessions this week',
                value: '14',
                detail: '3 sessions scheduled for today',
                tone: 'info',
            },
            {
                label: 'Attendance rate',
                value: '91%',
                detail: 'Up 4% from last week',
                tone: 'success',
            },
            {
                label: 'Athletes competition-ready',
                value: '27',
                detail: '5 need license renewal',
                tone: 'warning',
            },
        ],
        panels: [
            {
                title: 'Today’s attendance',
                description:
                    'Mark late arrivals, flag absences, and catch drop-off patterns before they affect performance.',
                ctaLabel: 'Record attendance',
                ctaHref: managementRoutes.attendance,
                items: [
                    'Morning sparring roster needs completion',
                    '2 athletes requested make-up sessions',
                    'Parent follow-up needed for 1 repeated absence',
                ],
            },
            {
                title: 'Session planning',
                description:
                    'Balance conditioning, sparring, and event prep across branches with a clear coaching calendar.',
                ctaLabel: 'Open coach sessions',
                ctaHref: managementRoutes.sessions,
                items: [
                    'Finalize Saturday camp agenda',
                    'Assign assistant coach for junior class',
                    'Block recovery day after tournament weekend',
                ],
            },
        ],
    },
    parent: {
        headline: 'Family overview',
        description:
            'See attendance consistency, payment status, and championship activity for your athlete in one dashboard.',
        metrics: [
            {
                label: 'Next payment',
                value: 'Rp650K',
                detail: 'Due on 18 April 2026',
                tone: 'warning',
            },
            {
                label: 'Attendance this month',
                value: '10 / 12',
                detail: 'Two missed sessions',
                tone: 'info',
            },
            {
                label: 'Registrations open',
                value: '2',
                detail: 'Regional and national cup available',
                tone: 'success',
            },
        ],
        panels: [
            {
                title: 'Payments and receipts',
                description:
                    'Track monthly tuition and competition invoices, including what has been paid and what remains.',
                ctaLabel: 'View payment center',
                ctaHref: managementRoutes.payments,
                items: [
                    'April tuition invoice pending',
                    'Receipt available for March tournament fee',
                    'Installment plan active for equipment package',
                ],
            },
            {
                title: 'Competition readiness',
                description:
                    'Check registration windows, coach notes, and the next events your athlete is eligible to join.',
                ctaLabel: 'View championships',
                ctaHref: managementRoutes.championships,
                items: [
                    'Eligibility confirmed for junior division',
                    'Medical form upload still required',
                    'Travel notes pending from branch admin',
                ],
            },
        ],
    },
    athlete: {
        headline: 'Training overview',
        description:
            'Stay on top of practice attendance, upcoming events, and your current progression plan.',
        metrics: [
            {
                label: 'Sessions completed',
                value: '18',
                detail: '4 more to hit the monthly target',
                tone: 'success',
            },
            {
                label: 'Upcoming event',
                value: '1',
                detail: 'Regional cup in 3 weeks',
                tone: 'info',
            },
            {
                label: 'Current geup',
                value: 'GEUP 5',
                detail: 'Assessment window opens next month',
                tone: 'warning',
            },
        ],
        panels: [
            {
                title: 'Attendance habits',
                description:
                    'Keep your training streak alive and watch where missed sessions are slowing progress.',
                ctaLabel: 'Review attendance',
                ctaHref: managementRoutes.attendance,
                items: [
                    '2 late check-ins this month',
                    'Next conditioning block starts Monday',
                    'Coach note added after sparring review',
                ],
            },
            {
                title: 'Event prep',
                description:
                    'See registrations, session plans, and the milestones to hit before competition day.',
                ctaLabel: 'Open championships',
                ctaHref: managementRoutes.championships,
                items: [
                    'Uniform checklist ready',
                    'Weight class confirmation pending',
                    'Parent approval needed for travel',
                ],
            },
        ],
    },
};

export const dashboardSnapshotRows: TableRow[] = [
    {
        id: 'OPS-001',
        workflow: 'Athlete approvals',
        owner: 'Admin team',
        status: badge('In progress', 'info'),
        target: 'Close by 12 Apr',
    },
    {
        id: 'OPS-002',
        workflow: 'Attendance sync',
        owner: 'Coach lead',
        status: badge('Healthy', 'success'),
        target: 'Updated daily',
    },
    {
        id: 'OPS-003',
        workflow: 'Event registration',
        owner: 'Front office',
        status: badge('Needs review', 'warning'),
        target: '2 missing documents',
    },
    {
        id: 'OPS-004',
        workflow: 'Payment collection',
        owner: 'Finance desk',
        status: badge('At risk', 'danger'),
        target: '8 overdue accounts',
    },
];

export const athleteRows: TableRow[] = [
    {
        id: 'ATH-001',
        athlete: 'Raka Pratama',
        branch: 'Jakarta Selatan',
        group: 'Junior Sparring',
        geup: 'GEUP 4',
        status: badge('Active', 'success'),
    },
    {
        id: 'ATH-002',
        athlete: 'Nadia Putri',
        branch: 'Depok',
        group: 'Poomsae Elite',
        geup: 'GEUP 6',
        status: badge('Profile incomplete', 'warning'),
    },
    {
        id: 'ATH-003',
        athlete: 'Bima Arsyad',
        branch: 'Bekasi',
        group: 'Beginner Kids',
        geup: 'GEUP 2',
        status: badge('Awaiting parent link', 'info'),
    },
    {
        id: 'ATH-004',
        athlete: 'Salma Maharani',
        branch: 'Tangerang',
        group: 'Competition Team',
        geup: 'DAN 1',
        status: badge('Competition ready', 'success'),
    },
];

export const paymentRows: TableRow[] = [
    {
        id: 'PAY-2401',
        athlete: 'Raka Pratama',
        type: 'Monthly tuition',
        amount: 'Rp650.000',
        balance: 'Rp0',
        status: badge('Paid', 'success'),
    },
    {
        id: 'PAY-2402',
        athlete: 'Nadia Putri',
        type: 'Championship fee',
        amount: 'Rp1.200.000',
        balance: 'Rp400.000',
        status: badge('Partial', 'warning'),
    },
    {
        id: 'PAY-2403',
        athlete: 'Bima Arsyad',
        type: 'Equipment package',
        amount: 'Rp950.000',
        balance: 'Rp950.000',
        status: badge('Unpaid', 'danger'),
    },
    {
        id: 'PAY-2404',
        athlete: 'Salma Maharani',
        type: 'National cup',
        amount: 'Rp1.500.000',
        balance: 'Rp0',
        status: badge('Paid', 'success'),
    },
];

export const attendanceRows: TableRow[] = [
    {
        id: 'ATT-1101',
        athlete: 'Raka Pratama',
        session: 'Morning conditioning',
        coach: 'Coach Deni',
        checkin: '07:01',
        status: badge('Present', 'success'),
    },
    {
        id: 'ATT-1102',
        athlete: 'Nadia Putri',
        session: 'Poomsae drills',
        coach: 'Coach Maya',
        checkin: '07:15',
        status: badge('Late', 'warning'),
    },
    {
        id: 'ATT-1103',
        athlete: 'Bima Arsyad',
        session: 'Kids fundamentals',
        coach: 'Coach Rizal',
        checkin: '-',
        status: badge('Absent', 'danger'),
    },
    {
        id: 'ATT-1104',
        athlete: 'Salma Maharani',
        session: 'Competition sparring',
        coach: 'Coach Deni',
        checkin: '16:54',
        status: badge('Present', 'success'),
    },
];

export const championshipRows: TableRow[] = [
    {
        id: 'EVT-501',
        event: 'Regional Spring Cup',
        date: '25 Apr 2026',
        location: 'Bandung',
        registration: badge('Open', 'success'),
        slots: '18 / 24 athletes',
    },
    {
        id: 'EVT-502',
        event: 'Jakarta Invitational',
        date: '10 May 2026',
        location: 'Jakarta',
        registration: badge('Closing soon', 'warning'),
        slots: '31 / 36 athletes',
    },
    {
        id: 'EVT-503',
        event: 'National Cup',
        date: '22 Jun 2026',
        location: 'Surabaya',
        registration: badge('Document review', 'info'),
        slots: '12 / 20 athletes',
    },
];

export const sessionRows: TableRow[] = [
    {
        id: 'SES-301',
        session: 'Junior sparring block',
        branch: 'Jakarta Selatan',
        coach: 'Coach Deni',
        schedule: 'Mon, Wed, Fri',
        status: badge('Confirmed', 'success'),
    },
    {
        id: 'SES-302',
        session: 'Poomsae refinement',
        branch: 'Depok',
        coach: 'Coach Maya',
        schedule: 'Tue, Thu',
        status: badge('Draft', 'info'),
    },
    {
        id: 'SES-303',
        session: 'Competition camp',
        branch: 'Bekasi',
        coach: 'Coach Rizal',
        schedule: 'Sat 08:00 - 11:00',
        status: badge('Needs assistant', 'warning'),
    },
];
