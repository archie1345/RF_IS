<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
type FieldOption = {
    value: string | number;
    label: string;
};

const props = withDefaults(defineProps<{
    id: string;
    label: string;
    modelValue: string;
    options: FieldOption[];
    placeholder?: string;
    error?: string;
    help?: string;
    required?: boolean;
    disabled?: boolean;
}>(), {
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between gap-3">
            <Label :for="props.id">{{ props.label }}</Label>
            <span v-if="props.required" class="text-xs text-muted-foreground">Required</span>
        </div>
        <select
            :id="props.id"
            :value="props.modelValue"
            :required="props.required"
            :disabled="props.disabled"
            :aria-invalid="Boolean(props.error)"
            class="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30 [color-scheme:light] dark:[color-scheme:dark]"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option value="" class="bg-background text-foreground">{{ props.placeholder ?? `Select ${props.label.toLowerCase()}` }}</option>
            <option v-for="option in props.options" :key="option.value" :value="String(option.value)" class="bg-background text-foreground">
                {{ option.label }}
            </option>
        </select>
        <p v-if="props.help && !props.error" class="text-xs leading-5 text-muted-foreground">{{ props.help }}</p>
        <InputError :message="props.error" />
    </div>
</template>
