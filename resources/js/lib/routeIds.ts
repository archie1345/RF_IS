const DISPLAY_ID_PATTERN = /^(?:(?:ATT|SCA|SES|ATHREG)-)?([1-9]\d*)$/;

/** Convert a table display ID into the positive integer expected by Wayfinder. */
export function routeId(value: unknown): number | null {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : null;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const match = DISPLAY_ID_PATTERN.exec(value.trim());

    if (!match) {
        return null;
    }

    const parsedId = Number(match[1]);

    return Number.isSafeInteger(parsedId) ? parsedId : null;
}
