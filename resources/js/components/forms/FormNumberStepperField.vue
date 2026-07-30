<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        modelValue: string;
        min?: number;
        step?: number;
        placeholder?: string;
        error?: string;
    }>(),
    {
        min: 0,
        step: 0.1,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function updateValue(value: number) {
    const fixed = Number.isInteger(props.step) ? String(value) : value.toFixed(1);
    emit('update:modelValue', fixed);
}

function parseCurrentValue() {
    const parsed = Number(props.modelValue);
    return Number.isFinite(parsed) ? parsed : props.min;
}

function decrement() {
    const next = Math.max(props.min, parseCurrentValue() - props.step);
    updateValue(next);
}

function increment() {
    const next = parseCurrentValue() + props.step;
    updateValue(next);
}
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <div class="flex items-center gap-2">
            <Button type="button" variant="outline" class="h-10 w-10 p-0 text-base" @click="decrement">-</Button>
            <Input
                :id="id"
                type="number"
                :model-value="modelValue"
                :min="min"
                :step="step"
                :placeholder="placeholder"
                class="h-10 [appearance:textfield] rounded-lg border-input bg-background text-foreground [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                @update:model-value="emit('update:modelValue', String($event))"
            />
            <Button type="button" variant="outline" class="h-10 w-10 p-0 text-base" @click="increment">+</Button>
        </div>
        <InputError :message="error" />
    </div>
</template>
