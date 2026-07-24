<script setup lang="ts">
import { QrCode } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        enabled?: boolean;
        imageUrl?: string | null;
        label?: string | null;
        instructions?: string | null;
        compact?: boolean;
    }>(),
    {
        enabled: true,
        imageUrl: null,
        label: 'Pembayaran QRIS',
        instructions: null,
        compact: false,
    },
);

const configured = computed(() => Boolean(props.imageUrl));
</script>

<template>
    <section
        v-if="props.enabled"
        class="grid gap-4 rounded-xl border border-border bg-card p-4 md:grid-cols-[auto_1fr] md:items-center"
        :class="props.compact ? 'md:grid-cols-[9rem_1fr]' : 'md:grid-cols-[12rem_1fr]'"
        aria-labelledby="qris-payment-title"
    >
        <div
            class="mx-auto flex aspect-square w-full max-w-48 items-center justify-center overflow-hidden rounded-xl border bg-background p-3"
        >
            <img
                v-if="configured"
                :src="String(props.imageUrl)"
                :alt="`${props.label || 'Pembayaran QRIS'} resmi`"
                class="h-full w-full object-contain"
            />
            <div v-else class="grid place-items-center gap-2 text-center text-muted-foreground" aria-label="Placeholder QRIS belum dikonfigurasi">
                <QrCode class="size-20" aria-hidden="true" />
                <span class="text-xs font-semibold uppercase tracking-wider">QRIS placeholder</span>
            </div>
        </div>

        <div class="grid gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pembayaran statis</p>
                <h2 id="qris-payment-title" class="text-lg font-semibold">{{ props.label || 'Pembayaran QRIS' }}</h2>
            </div>
            <p v-if="configured" class="text-sm leading-6 text-muted-foreground">
                {{ props.instructions || 'Pindai QRIS, lakukan pembayaran sesuai nominal tagihan, lalu unggah bukti untuk direview admin.' }}
            </p>
            <div v-else class="rounded-lg border border-dashed border-amber-500/50 bg-amber-500/10 p-3 text-sm leading-6">
                QRIS resmi belum diunggah. Gambar ini hanya placeholder dan tidak dapat digunakan untuk pembayaran.
            </div>
            <p class="text-xs text-muted-foreground">
                Pembayaran QRIS belum terhubung ke Midtrans. Saldo hanya berubah setelah bukti disetujui admin.
            </p>
        </div>
    </section>
</template>
