export function normalizeWhatsAppNumber(value: unknown): string | null {
    let digits = String(value ?? '').replace(/\D+/g, '');

    if (!digits) return null;
    if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;

    return digits;
}

export function buildWhatsAppUrl(number: unknown, message: string): string | null {
    const normalizedNumber = normalizeWhatsAppNumber(number);

    if (!normalizedNumber) return null;

    return `https://wa.me/${normalizedNumber}?text=${encodeURIComponent(message)}`;
}
