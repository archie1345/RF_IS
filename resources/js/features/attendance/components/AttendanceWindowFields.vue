<script setup lang="ts">
import FormInputField from '@/components/forms/FormInputField.vue';

const props = defineProps<{
    opensAt: string;
    closesAt: string;
    opensAtError?: string;
    closesAtError?: string;
    sessionDate?: string | null;
    sessionStartTime?: string | null;
    sessionEndTime?: string | null;
}>();

const emit = defineEmits<{
    (event: 'update:opensAt', value: string): void;
    (event: 'update:closesAt', value: string): void;
}>();
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[1fr_1fr]">

        <FormInputField
            id="attendance_opens_at"
            :model-value="props.opensAt"
            label="QR opens at"
            type="datetime-local"
            required
            :error="props.opensAtError"
            help="Must be at or after the session start and before the session ends."
            @update:model-value="emit('update:opensAt', $event)"
        />
        <FormInputField
            id="attendance_closes_at"
            :model-value="props.closesAt"
            label="QR closes at"
            type="datetime-local"
            required
            :error="props.closesAtError"
            help="Must be after opening time and no later than the session end."
            @update:model-value="emit('update:closesAt', $event)"
        />
    </div>
</template>
