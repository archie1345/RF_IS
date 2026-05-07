<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

withDefaults(defineProps<{
    id: string;
    label: string;
    modelValue: string;
    type?: string;
    placeholder?: string;
    error?: string;
}>(), {
    type: 'text',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">{{ label }}</Label>
        <Input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :class="[
                'h-10 rounded-lg border-input bg-background text-foreground [color-scheme:light] dark:[color-scheme:dark]',
                type === 'date' || type === 'time' ? 'min-w-[10.5rem] pr-10' : '',
            ]"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <InputError :message="error" />
    </div>
</template>
