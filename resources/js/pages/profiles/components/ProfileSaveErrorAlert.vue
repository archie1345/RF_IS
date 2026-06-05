<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

defineProps<{
    title: string;
    message: string;
    fields: string[];
}>();

const emit = defineEmits<{
    (e: 'clear'): void;
}>();
</script>

<template>
    <Alert variant="destructive" class="shadow-sm">
        <AlertCircle class="size-4" />
        <AlertTitle>{{ title }}</AlertTitle>
        <AlertDescription>
            <p>{{ message }}</p>
            <ul v-if="fields.length" class="list-inside list-disc">
                <li v-for="field in fields" :key="field">{{ field }}</li>
            </ul>
            <Button type="button" size="sm" variant="outline" class="mt-3" @click="emit('clear')"> Dismiss </Button>
        </AlertDescription>
    </Alert>
</template>
