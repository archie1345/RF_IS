<script setup lang="ts">
import { ReceiptText } from 'lucide-vue-next';

type PaymentTransactionHistoryEntry = {
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

const props = withDefaults(
    defineProps<{
        entries: PaymentTransactionHistoryEntry[];
        title?: string;
        emptyText?: string;
        showVerifier?: boolean;
        bordered?: boolean;
    }>(),
    {
        title: 'Transaction History',
        emptyText: 'No approved installments yet.',
        showVerifier: true,
        bordered: true,
    },
);
</script>

<template>
    <section
        class="grid gap-3 text-sm"
        :class="props.bordered ? 'rounded-lg border border-border bg-muted/30 p-3' : ''"
        aria-live="polite"
    >
        <div class="flex items-center gap-2 font-medium">
            <ReceiptText class="size-4" />
            <span>{{ props.title }}</span>
        </div>

        <div v-if="props.entries.length === 0" class="text-xs leading-5 text-muted-foreground">
            {{ props.emptyText }}
        </div>

        <div
            v-for="history in props.entries"
            :key="history.id"
            class="grid gap-1 border-t border-border pt-3 first:border-t-0 first:pt-0"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-medium">{{ history.amount }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ history.date
                    }}<template v-if="props.showVerifier && history.verified_by">
                        by {{ history.verified_by }}</template
                    >
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span v-if="history.type">{{ history.type }}</span>
                <span v-if="history.method">{{ history.method }}</span>
                <a
                    v-if="history.proof_url"
                    :href="history.proof_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-medium text-foreground underline underline-offset-4"
                >
                    Proof snapshot
                </a>
            </div>
            <p v-if="history.notes" class="text-xs leading-5 whitespace-pre-line text-muted-foreground">
                {{ history.notes }}
            </p>
        </div>
    </section>
</template>
