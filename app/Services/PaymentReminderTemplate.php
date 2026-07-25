<?php

namespace App\Services;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Schema;

class PaymentReminderTemplate
{
    public const KEY = 'payment_whatsapp_reminder';

    public const DEFAULT_BODY = 'Halo {name}, tagihan {invoice_number} ({payment_type}) sebesar {total_amount} masih memiliki sisa {remaining_amount} dan jatuh tempo {due_date}. Silakan lakukan pembayaran lalu unggah bukti di {payment_url}. Terima kasih.';

    public const PLACEHOLDERS = [
        'name' => 'Nama penerima tagihan',
        'invoice_number' => 'Nomor invoice',
        'payment_type' => 'Kategori pembayaran',
        'total_amount' => 'Total tagihan',
        'remaining_amount' => 'Sisa tagihan',
        'due_date' => 'Tanggal jatuh tempo',
        'payment_url' => 'Tautan halaman pembayaran',
    ];

    private ?string $cachedBody = null;

    public function body(): string
    {
        if ($this->cachedBody !== null) {
            return $this->cachedBody;
        }

        if (! Schema::hasTable('message_templates')) {
            return $this->cachedBody = self::DEFAULT_BODY;
        }

        $body = MessageTemplate::query()
            ->where('key', self::KEY)
            ->value('body');

        return $this->cachedBody = filled($body) ? (string) $body : self::DEFAULT_BODY;
    }

    /** @param array<string, string> $values */
    public function render(array $values): string
    {
        $replacements = collect(array_keys(self::PLACEHOLDERS))
            ->mapWithKeys(fn (string $placeholder): array => [
                '{'.$placeholder.'}' => $values[$placeholder] ?? '-',
            ])
            ->all();

        return strtr($this->body(), $replacements);
    }

    /** @return array<int, string> */
    public function unsupportedPlaceholders(string $body): array
    {
        preg_match_all('/\{([a-z_]+)\}/i', $body, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $placeholder): string => strtolower($placeholder))
            ->reject(fn (string $placeholder): bool => array_key_exists($placeholder, self::PLACEHOLDERS))
            ->unique()
            ->values()
            ->all();
    }

    public function clearCache(): void
    {
        $this->cachedBody = null;
    }
}
