<script setup lang="ts">
import FileUploadField from '@/components/forms/FileUploadField.vue';

const props = defineProps<{
    id: string;
    label: string;
    accept?: string;
    error?: string;
    currentFileName?: string | null;
    currentFileUrl?: string | null;
}>();

const model = defineModel<File | null>({ default: null });
</script>

<template>
    <FileUploadField
        :id="props.id"
        v-model="model"
        :label="props.label"
        :accept="props.accept"
        :error="props.error"
        :current-file-name="props.currentFileName"
        :current-file-url="props.currentFileUrl"
    />
</template>
