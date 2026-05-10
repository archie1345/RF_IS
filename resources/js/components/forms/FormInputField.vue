<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(defineProps<{
    id: string;
    label: string;
    modelValue: string;
    type?: string;
    placeholder?: string;
    error?: string;
    help?: string;
    required?: boolean;
    disabled?: boolean;
    autocomplete?: string;
    inputmode?: string;
    min?: string | number;
    max?: string | number;
    step?: string | number;
}>(), {
    type: 'text',
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
            <Label :for="id">{{ label }}</Label>
            <span v-if="props.required" class="text-xs text-muted-foreground">Required</span>
        </div>
        <Input
            :id="id"
            :type="props.type"
            :model-value="props.modelValue"
            :placeholder="props.placeholder"
            :required="props.required"
            :disabled="props.disabled"
            :autocomplete="props.autocomplete"
            :inputmode="props.inputmode"
            :min="props.min"
            :max="props.max"
            :step="props.step"
            :aria-invalid="Boolean(props.error)"
            :class="[
                'h-10 rounded-lg border-input bg-background text-foreground [color-scheme:light] dark:[color-scheme:dark]',
                props.type === 'date' || props.type === 'time' ? 'min-w-[10.5rem] pr-10' : '',
            ]"
            @update:model-value="emit('update:modelValue', String($event ?? ''))"
        />
        <p v-if="props.help && !props.error" class="text-xs leading-5 text-muted-foreground">{{ props.help }}</p>
        <InputError :message="props.error" />
    </div>
</template>
