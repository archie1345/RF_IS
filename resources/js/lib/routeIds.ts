const DISPLAY_ID_PATTERN = /^(?:(?:ATT|SCA|SES|ATHREG|USR)-)?([1-9]\d*|[0-9A-Z]{26})$/i;

/** Convert a table display ID into the positive integer expected by Wayfinder. */
export function routeId(value: unknown): number | string | null {
    if (typeof value === 'number') {
        return Number.isSafeInteger(value) && value > 0 ? value : null;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const trimmed = value.trim();
    if (/^[0-9A-Z]{26}$/i.test(trimmed) || /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(trimmed)) {
        return trimmed;
    }

    const match = DISPLAY_ID_PATTERN.exec(trimmed);

    if (!match) {
        return null;
    }

    const matchedStr = match[1];
    
    if (/^[0-9A-Z]{26}$/i.test(matchedStr)) {
        return matchedStr.toUpperCase();
    }

    const parsedId = Number(matchedStr);
    return Number.isSafeInteger(parsedId) ? parsedId : null;
}
