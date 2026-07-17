<script setup lang="ts">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

type FieldOption = {
    value: string | number;
    label: string;
};

type ModelValue = string | string[];

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        modelValue: ModelValue;
        options: FieldOption[];
        placeholder?: string;
        error?: string;
        help?: string;
        required?: boolean;
        disabled?: boolean;
        showPlaceholder?: boolean;
        multiple?: boolean;
        searchPlaceholder?: string;
    }>(),
    {
        required: false,
        disabled: false,
        multiple: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: ModelValue): void;
}>();

const open = ref(false);
const search = ref('');

const selectedValues = computed(() =>
    Array.isArray(props.modelValue)
        ? props.modelValue.map(String)
        : props.modelValue === ''
          ? []
          : [String(props.modelValue)],
);
const selectedOption = computed(
    () => props.options.find((option) => String(option.value) === String(props.modelValue)) ?? null,
);
const selectedOptions = computed(() =>
    props.options.filter((option) => selectedValues.value.includes(String(option.value))),
);
const filteredOptions = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return props.options;
    return props.options.filter(
        (option) =>
            option.label.toLowerCase().includes(keyword) || String(option.value).toLowerCase().includes(keyword),
    );
});

function optionSelected(value: string | number) {
    return selectedValues.value.includes(String(value));
}

function selectValue(value: string | number) {
    if (!props.multiple) {
        emit('update:modelValue', String(value));
        open.value = false;
        search.value = '';
        return;
    }

    const stringValue = String(value);
    const nextValues = optionSelected(stringValue)
        ? selectedValues.value.filter((entry) => entry !== stringValue)
        : [...selectedValues.value, stringValue];

    emit('update:modelValue', nextValues);
}

function clearValue() {
    emit('update:modelValue', props.multiple ? [] : '');
    search.value = '';
}

function removeValue(value: string | number) {
    if (!props.multiple) {
        clearValue();
        return;
    }

    emit(
        'update:modelValue',
        selectedValues.value.filter((entry) => entry !== String(value)),
    );
}
</script>

<template>
    <div class="relative grid gap-2">
        <div class="flex items-center justify-between gap-3">
            <Label :for="props.id">{{ props.label }}</Label>
            <span v-if="props.required" class="text-xs text-muted-foreground">Required</span>
        </div>

        <button
            :id="props.id"
            type="button"
            :disabled="props.disabled"
            :aria-invalid="Boolean(props.error)"
            class="flex min-h-12 w-full items-center justify-between gap-2 rounded-2xl border bg-background px-3 py-2 text-left text-sm shadow-sm transition focus:ring-2 focus:ring-ring/30 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
            :class="props.error ? 'border-destructive ring-2 ring-destructive/15' : 'border-input hover:border-ring/60'"
            @click="open = !open"
        >
            <span v-if="props.multiple && selectedOptions.length" class="flex max-w-[calc(100%-2rem)] flex-wrap gap-1.5">
                <span
                    v-for="option in selectedOptions"
                    :key="option.value"
                    class="inline-flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-2.5 py-1 font-medium text-primary"
                >
                    {{ option.label }}
                    <X class="size-3" @click.stop="removeValue(option.value)" />
                </span>
            </span>
            <span
                v-else-if="selectedOption"
                class="inline-flex max-w-[calc(100%-2rem)] items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-2.5 py-1 font-medium text-primary"
            >
                {{ selectedOption.label }}
                <X class="size-3" @click.stop="clearValue" />
            </span>
            <span v-else class="text-muted-foreground">{{
                props.placeholder ?? `Select ${props.label.toLowerCase()}`
            }}</span>
            <ChevronDown class="size-4 shrink-0 text-muted-foreground transition" :class="open ? 'rotate-180' : ''" />
        </button>

        <div
            v-if="open"
            class="absolute top-full z-50 mt-2 w-full overflow-hidden rounded-2xl border bg-popover text-popover-foreground shadow-xl"
        >
            <div class="flex items-center gap-2 border-b px-3 py-2">
                <Search class="size-4 text-muted-foreground" />
                <input
                    v-model="search"
                    type="search"
                    class="h-9 min-w-0 flex-1 bg-transparent text-sm outline-none"
                    :placeholder="props.searchPlaceholder ?? 'Cari...'"
                />
            </div>
            <div class="max-h-64 overflow-auto p-1">
                <button
                    v-for="option in filteredOptions"
                    :key="option.value"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-medium hover:bg-muted"
                    @click="selectValue(option.value)"
                >
                    <span
                        class="flex size-5 items-center justify-center rounded-md border"
                        :class="
                            optionSelected(option.value)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input'
                        "
                    >
                        <Check v-if="optionSelected(option.value)" class="size-3" />
                    </span>
                    <span>{{ option.label }}</span>
                </button>
                <p v-if="filteredOptions.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                    No options found.
                </p>
            </div>
            <div v-if="props.multiple && selectedOptions.length" class="border-t px-3 py-2 text-xs text-muted-foreground">
                {{ selectedOptions.length }} selected
            </div>
        </div>

        <p v-if="props.help && !props.error" class="text-xs leading-5 text-muted-foreground">{{ props.help }}</p>
        <InputError :message="props.error" />
    </div>
</template>
