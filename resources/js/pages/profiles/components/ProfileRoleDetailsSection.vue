<script setup lang="ts">
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        canEdit?: boolean;
        editing?: boolean;
        processing?: boolean;
        saveLabel?: string;
        divided?: boolean;
    }>(),
    {
        description: '',
        canEdit: false,
        editing: false,
        processing: false,
        saveLabel: 'Save Changes',
        divided: false,
    },
);

const emit = defineEmits<{
    edit: [];
    save: [];
    cancel: [];
}>();
</script>

<template>
    <section :class="divided ? 'border-t border-border/70 pt-5' : ''">
        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h4 class="font-semibold">{{ title }}</h4>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <Button
                v-if="canEdit && !editing"
                type="button"
                variant="outline"
                size="sm"
                class="w-full sm:w-auto"
                @click="emit('edit')"
            >
                Edit
            </Button>
        </div>

        <form class="space-y-3" @submit.prevent="emit('save')">
            <slot />

            <div v-if="editing" class="mt-4 flex flex-col gap-2 sm:flex-row">
                <Button type="submit" :disabled="processing" size="sm" class="w-full sm:w-auto">
                    {{ saveLabel }}
                </Button>
                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto" @click="emit('cancel')">
                    Cancel
                </Button>
            </div>
        </form>
    </section>
</template>
