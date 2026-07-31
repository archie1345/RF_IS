<?php

namespace App\Services;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PublicContactSettings
{
    public const CONTACT_KEY = 'public_admin_whatsapp';

    public const BUBBLE_ENABLED_KEY = 'public_whatsapp_bubble_enabled';

    private const CACHE_KEY = 'public-contact-settings.v1';

    /** @var array{contact_number: string, bubble_enabled: bool}|null */
    private ?array $resolved = null;

    /** @return array{contact_number: string, bubble_enabled: bool} */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            function (): array {
                if (! Schema::hasTable('message_templates')) {
                    return $this->defaults();
                }

                $settings = MessageTemplate::query()
                    ->whereIn('key', [self::CONTACT_KEY, self::BUBBLE_ENABLED_KEY])
                    ->pluck('body', 'key');

                $rawEnabled = strtolower(trim((string) ($settings[self::BUBBLE_ENABLED_KEY] ?? '1')));

                return [
                    'contact_number' => trim((string) ($settings[self::CONTACT_KEY] ?? '')),
                    'bubble_enabled' => ! in_array($rawEnabled, ['0', 'false', 'off', 'no', 'disabled'], true),
                ];
            },
        );
    }

    public function contactNumber(): string
    {
        return $this->all()['contact_number'];
    }

    public function bubbleEnabled(): bool
    {
        return $this->all()['bubble_enabled'];
    }

    public function clearCache(): void
    {
        $this->resolved = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{contact_number: string, bubble_enabled: bool} */
    private function defaults(): array
    {
        return [
            'contact_number' => '',
            'bubble_enabled' => true,
        ];
    }

    public function getAdminPhoneNumber(): ?string
    {
        return $this->contactNumber() ?: null;
    }
}
