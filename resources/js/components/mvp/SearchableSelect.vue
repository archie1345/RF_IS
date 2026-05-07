<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Check, ChevronsUpDown, Search } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type SelectValue = string | number;

type SelectOption = {
    value: SelectValue;
    label: string;
};

const props = defineProps<{
    modelValue: SelectValue | '' | null;
    options: SelectOption[];
    placeholder?: string;
    title?: string;
    description?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: SelectValue | ''];
}>();

const isOpen = ref(false);
const search = ref('');

const selectedOption = computed(() =>
    props.options.find((option) => String(option.value) === String(props.modelValue)),
);

const filteredOptions = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(query));
});

watch(isOpen, (open) => {
    if (!open) {
        search.value = '';
    }
});

function selectOption(value: SelectValue) {
    emit('update:modelValue', value);
    isOpen.value = false;
}

function clearSelection() {
    emit('update:modelValue', '');
    isOpen.value = false;
}
</script>

<template>
    <div class="grid gap-2">
        <Button
            type="button"
            variant="outline"
            class="h-9 w-full justify-between font-normal"
            :disabled="disabled"
            @click="isOpen = true"
        >
            <span class="truncate text-left">
                {{ selectedOption?.label ?? placeholder ?? 'Select option' }}
            </span>
            <ChevronsUpDown class="ml-2 size-4 shrink-0 opacity-60" />
        </Button>

        <Dialog v-model:open="isOpen">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ title ?? 'Select option' }}</DialogTitle>
                    <DialogDescription>
                        {{ description ?? 'Search and choose from the available options.' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            class="pl-9"
                            :placeholder="searchPlaceholder ?? 'Search options'"
                        />
                    </div>

                    <div class="rounded-2xl border border-border/70">
                        <div v-if="filteredOptions.length > 0" class="max-h-80 space-y-1 overflow-y-auto p-2">
                            <button
                                v-for="option in filteredOptions"
                                :key="String(option.value)"
                                type="button"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left text-sm transition hover:bg-muted"
                                :class="String(option.value) === String(modelValue) ? 'bg-muted' : ''"
                                @click="selectOption(option.value)"
                            >
                                <span class="truncate pr-3">{{ option.label }}</span>
                                <Check
                                    v-if="String(option.value) === String(modelValue)"
                                    class="size-4 shrink-0 text-foreground"
                                />
                            </button>
                        </div>

                        <div v-else class="px-4 py-8 text-center text-sm text-muted-foreground">
                            {{ emptyText ?? 'No matching options found.' }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm text-muted-foreground">
                            {{ filteredOptions.length }} option{{ filteredOptions.length === 1 ? '' : 's' }}
                        </p>
                        <Button
                            v-if="modelValue !== '' && modelValue !== null"
                            type="button"
                            variant="outline"
                            @click="clearSelection"
                        >
                            Clear
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

