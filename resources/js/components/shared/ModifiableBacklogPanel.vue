<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

type BacklogGroup = {
    module: string;
    items: string[];
};

const props = defineProps<{
    storageKey: string;
    groups: BacklogGroup[];
}>();

const editable = ref<BacklogGroup[]>(JSON.parse(JSON.stringify(props.groups)));

const persisted = computed(() => {
    try {
        const raw = localStorage.getItem(props.storageKey);
        return raw ? (JSON.parse(raw) as BacklogGroup[]) : null;
    } catch {
        return null;
    }
});

if (persisted.value && persisted.value.length > 0) {
    editable.value = persisted.value;
}

watch(
    editable,
    (value) => {
        localStorage.setItem(props.storageKey, JSON.stringify(value));
    },
    { deep: true },
);

function resetToDefault() {
    editable.value = JSON.parse(JSON.stringify(props.groups));
}
</script>

<template>
    <div class="grid gap-4">
        <p class="text-xs text-muted-foreground">MODIFIABLE: Edit text below directly. It is saved in your browser local storage.</p>
        <div
            v-for="group in editable"
            :key="group.module"
            class="rounded-xl border border-border/70 p-4"
        >
            <p class="text-sm font-semibold tracking-wide">{{ group.module }}</p>
            <div class="mt-2 grid gap-2">
                <div v-for="(item, index) in group.items" :key="`${group.module}-${index}`" class="grid gap-1">
                    <label class="text-xs text-muted-foreground">Item {{ index + 1 }}</label>
                    <input
                        v-model="group.items[index]"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                </div>
            </div>
        </div>
        <div class="flex justify-end">
            <Button type="button" variant="outline" @click="resetToDefault">Reset to defaults</Button>
        </div>
    </div>
</template>
