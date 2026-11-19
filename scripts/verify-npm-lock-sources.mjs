import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const lockPath = resolve(process.argv[2] ?? 'package-lock.json');
const lock = JSON.parse(await readFile(lockPath, 'utf8'));
const registryOrigin = 'https://registry.npmjs.org';
let verified = 0;

for (const [name, entry] of Object.entries(lock.packages ?? {})) {
    if (!entry.resolved) continue;

    const resolved = new URL(entry.resolved);
    if (resolved.protocol !== 'https:' || resolved.origin !== registryOrigin || typeof entry.integrity !== 'string') {
        throw new Error(`Refusing unverified npm lock source for ${name || '(root package)'}.`);
    }

    verified += 1;
}

if (verified === 0) {
    throw new Error(`No integrity-checked npm packages found in ${lockPath}.`);
}

console.log(`Verified ${verified} integrity-checked package sources in ${lockPath}.`);
