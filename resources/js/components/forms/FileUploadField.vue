<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    id: string;
    label: string;
    accept?: string;
    error?: string;
    currentFileName?: string | null;
    currentFileUrl?: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: File | null): void;
}>();

function onFileChange(event: Event) {
    emit('update:modelValue', (event.target as HTMLInputElement).files?.[0] ?? null);
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="props.id">{{ props.label }}</Label>
        <a
            v-if="props.currentFileUrl"
            :href="props.currentFileUrl"
            target="_blank"
            class="text-sm font-medium underline underline-offset-4"
        >
            Current file: {{ props.currentFileName ?? 'Open file' }}
        </a>
        <input
            :id="props.id"
            type="file"
            :accept="props.accept"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
            :aria-invalid="Boolean(props.error)"
            @change="onFileChange"
        />
        <InputError :message="props.error" />
    </div>
</template>
