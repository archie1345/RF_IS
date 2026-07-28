export type PaymentHistoryEntry = {
    id: number | string;
    amount: string;
    date: string;
    method: string;
    type: string;
    verified_by: string;
    notes?: string;
    proof_notes?: string;
    proof_url?: string | null;
};
