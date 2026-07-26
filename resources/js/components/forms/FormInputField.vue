<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
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
    }>(),
    {
        type: 'text',
        required: false,
        disabled: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const rangeStart = ref('');
const rangeEnd = ref('');
const resolvedStep = computed(() => props.step ?? (props.type === 'number' ? 'any' : undefined));

function isRangeType(type: string): boolean {
    return type === 'date-range' || type === 'daterange';
}

function parseRange(value: string): { start: string; end: string } {
    const matches = value.match(/\d{4}-\d{2}-\d{2}/g) ?? [];
    return { start: matches[0] ?? '', end: matches[1] ?? '' };
}

function updateRange(): void {
    if (!rangeStart.value && !rangeEnd.value) {
        emit('update:modelValue', '');
        return;
    }

    emit('update:modelValue', rangeEnd.value ? `${rangeStart.value} – ${rangeEnd.value}` : rangeStart.value);
}

watch(
    () => props.modelValue,
    (value) => {
        if (!isRangeType(props.type)) return;
        const parsed = parseRange(value);
        rangeStart.value = parsed.start;
        rangeEnd.value = parsed.end;
    },
    { immediate: true },
);
</script>

<template>
    <div class="grid min-w-0 gap-2">
        <div class="flex min-w-0 items-center justify-between gap-3">
            <Label :for="id" class="min-w-0 break-words">{{ label }}</Label>
            <span v-if="props.required" class="shrink-0 text-xs text-muted-foreground">Wajib</span>
        </div>

        <div v-if="isRangeType(props.type)" class="grid min-w-0 gap-3 sm:grid-cols-2">
            <label class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground">
                Tanggal mulai
                <Input
                    :id="`${id}-start`"
                    v-model="rangeStart"
                    type="date"
                    :required="props.required"
                    :disabled="props.disabled"
                    :min="props.min"
                    :max="rangeEnd || props.max"
                    :aria-invalid="Boolean(props.error)"
                    class="h-11 min-w-0 w-full rounded-lg border-input bg-background text-foreground"
                    @update:model-value="updateRange"
                />
            </label>
            <label class="grid min-w-0 gap-1.5 text-xs font-medium text-muted-foreground">
                Tanggal selesai
                <Input
                    :id="`${id}-end`"
                    v-model="rangeEnd"
                    type="date"
                    :required="props.required"
                    :disabled="props.disabled"
                    :min="rangeStart || props.min"
                    :max="props.max"
                    :aria-invalid="Boolean(props.error)"
                    class="h-11 min-w-0 w-full rounded-lg border-input bg-background text-foreground"
                    @update:model-value="updateRange"
                />
            </label>
        </div>

        <Input
            v-else
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
            :step="resolvedStep"
            :aria-invalid="Boolean(props.error)"
            class="h-11 min-w-0 w-full rounded-lg border-input bg-background text-foreground"
            @update:model-value="emit('update:modelValue', String($event ?? ''))"
        />

        <p v-if="props.help && !props.error" class="break-words text-xs leading-5 text-muted-foreground">
            {{ props.help }}
        </p>
        <InputError :message="props.error" />
    </div>
</template>
