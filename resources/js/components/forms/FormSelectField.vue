<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
type FieldOption = {
    value: string | number;
    label: string;
};

defineProps<{
    id: string;
    label: string;
    modelValue: string;
    options: FieldOption[];
    placeholder?: string;
    error?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <select
            :id="id"
            :value="modelValue"
            class="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30 [color-scheme:light] dark:[color-scheme:dark]"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option value="" class="bg-background text-foreground">{{ placeholder ?? `Select ${label.toLowerCase()}` }}</option>
            <option v-for="option in options" :key="option.value" :value="String(option.value)" class="bg-background text-foreground">
                {{ option.label }}
            </option>
        </select>
        <InputError :message="error" />
    </div>
</template>
