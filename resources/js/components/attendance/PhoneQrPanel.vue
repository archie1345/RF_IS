<template>
    <div class="rounded-2xl border bg-card p-4 shadow-sm">
        <div v-if="isActive && scanUrl" class="grid gap-3">
            <p class="font-black">Active check-in link</p>
            <p class="break-all rounded-2xl bg-muted/60 p-3 text-xs text-muted-foreground">{{ scanUrl }}</p>
            <Button as-child size="sm" variant="outline"><a :href="scanUrl" target="_blank">Open test page</a></Button>
            <p class="text-xs text-muted-foreground">Generated: {{ generatedAt ?? '-' }}</p>
        </div>
        <div v-else class="grid gap-2 py-8 text-center">
            <p class="font-black">No active check-in link</p>
            <p class="text-sm text-muted-foreground">Generate a window first.</p>
            <p v-if="revokedAt" class="text-xs text-muted-foreground">Last revoked: {{ revokedAt }}</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    scanUrl: string | null;
    isActive: boolean;
    generatedAt: string | null;
    revokedAt: string | null;
}>();
</script>
