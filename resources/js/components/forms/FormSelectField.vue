<script setup lang="ts">
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import type { FieldOption, ModelValue } from './FormSelectField.types';

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

const rootRef = ref<HTMLElement | null>(null);
const open = ref(false);
const search = ref('');
const instanceId = `select-${props.id}-${Math.random().toString(36).slice(2)}`;
const closeEventName = 'rf-select-opened';

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

function optionSelected(value: string | number): boolean {
    return selectedValues.value.includes(String(value));
}

function closeDropdown(): void {
    open.value = false;
    search.value = '';
}

function announceDropdownOpen(): void {
    window.dispatchEvent(new CustomEvent(closeEventName, { detail: { instanceId } }));
}

function toggleDropdown(): void {
    if (props.disabled) return;
    if (open.value) {
        closeDropdown();
        return;
    }

    announceDropdownOpen();
    open.value = true;
}

function handleOtherDropdownOpened(event: Event): void {
    const detail = (event as CustomEvent<{ instanceId?: string }>).detail;
    if (detail?.instanceId !== instanceId) closeDropdown();
}

function handleOutsidePointerDown(event: PointerEvent): void {
    if (!open.value) return;
    const target = event.target;
    if (!(target instanceof Node) || rootRef.value?.contains(target)) return;
    closeDropdown();
}

onMounted(() => {
    window.addEventListener(closeEventName, handleOtherDropdownOpened);
    document.addEventListener('pointerdown', handleOutsidePointerDown);
});

onBeforeUnmount(() => {
    window.removeEventListener(closeEventName, handleOtherDropdownOpened);
    document.removeEventListener('pointerdown', handleOutsidePointerDown);
});

function selectValue(value: string | number): void {
    if (!props.multiple) {
        emit('update:modelValue', String(value));
        closeDropdown();
        return;
    }

    const stringValue = String(value);
    const nextValues = optionSelected(stringValue)
        ? selectedValues.value.filter((entry) => entry !== stringValue)
        : [...selectedValues.value, stringValue];
    emit('update:modelValue', nextValues);
}

function clearValue(): void {
    emit('update:modelValue', props.multiple ? [] : '');
    search.value = '';
}

function removeValue(value: string | number): void {
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
    <div ref="rootRef" class="relative grid min-w-0 gap-2">
        <div class="flex min-w-0 items-center justify-between gap-3">
            <Label :for="props.id" class="min-w-0 break-words">{{ props.label }}</Label>
            <span v-if="props.required" class="shrink-0 text-xs text-muted-foreground">Wajib</span>
        </div>

        <button
            :id="props.id"
            type="button"
            :disabled="props.disabled"
            :aria-invalid="Boolean(props.error)"
            :aria-expanded="open"
            class="flex min-h-11 w-full min-w-0 items-center justify-between gap-2 rounded-xl border bg-background px-3 py-2 text-left text-sm shadow-sm transition focus:ring-2 focus:ring-ring/30 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
            :class="props.error ? 'border-destructive ring-2 ring-destructive/15' : 'border-input hover:border-ring/60'"
            @click="toggleDropdown"
        >
            <span v-if="props.multiple && selectedOptions.length" class="flex min-w-0 flex-1 flex-wrap gap-1.5">
                <span
                    v-for="option in selectedOptions"
                    :key="option.value"
                    class="inline-flex max-w-full items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-2.5 py-1 font-medium text-primary"
                >
                    <span class="min-w-0 break-words">{{ option.label }}</span>
                    <X class="size-3 shrink-0" @click.stop="removeValue(option.value)" />
                </span>
            </span>
            <span
                v-else-if="selectedOption"
                class="inline-flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-2.5 py-1 font-medium text-primary"
            >
                <span class="min-w-0 flex-1 truncate">{{ selectedOption.label }}</span>
                <X class="size-3 shrink-0" @click.stop="clearValue" />
            </span>
            <span v-else class="min-w-0 flex-1 truncate text-muted-foreground">
                {{ props.placeholder ?? `Pilih ${props.label.toLowerCase()}` }}
            </span>
            <ChevronDown class="size-4 shrink-0 text-muted-foreground transition" :class="open ? 'rotate-180' : ''" />
        </button>

        <div
            v-if="open"
            class="absolute top-full right-0 left-0 z-50 mt-2 max-h-[min(24rem,55dvh)] min-w-0 overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-xl"
        >
            <div class="flex items-center gap-2 border-b px-3 py-2">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <input
                    v-model="search"
                    type="search"
                    class="h-10 min-w-0 flex-1 bg-transparent text-base outline-none sm:text-sm"
                    :placeholder="props.searchPlaceholder ?? 'Cari...'"
                />
            </div>
            <div class="max-h-[min(19rem,45dvh)] overflow-y-auto overscroll-contain p-1">
                <button
                    v-for="option in filteredOptions"
                    :key="option.value"
                    type="button"
                    class="flex min-h-11 w-full min-w-0 items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-muted"
                    @click="selectValue(option.value)"
                >
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-md border"
                        :class="
                            optionSelected(option.value)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input'
                        "
                    >
                        <Check v-if="optionSelected(option.value)" class="size-3" />
                    </span>
                    <span class="min-w-0 break-words">{{ option.label }}</span>
                </button>
                <p v-if="filteredOptions.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                    Tidak ada pilihan yang cocok.
                </p>
            </div>
            <div
                v-if="props.multiple && selectedOptions.length"
                class="border-t px-3 py-2 text-xs text-muted-foreground"
            >
                {{ selectedOptions.length }} dipilih
            </div>
        </div>

        <p v-if="props.help && !props.error" class="text-xs leading-5 break-words text-muted-foreground">
            {{ props.help }}
        </p>
        <InputError :message="props.error" />
    </div>
</template>
