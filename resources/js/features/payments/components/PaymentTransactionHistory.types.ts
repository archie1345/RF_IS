export type PaymentTransactionHistoryEntry = {
    id: number | string;
    amount: string;
    date: string;
    method?: string | null;
    type?: string | null;
    verified_by?: string | null;
    notes?: string | null;
    proof_notes?: string | null;
    proof_url?: string | null;
};
