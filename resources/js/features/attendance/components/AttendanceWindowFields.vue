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
        <div
            class="rounded-lg border border-border bg-muted/30 p-3 text-xs leading-5 text-muted-foreground lg:col-span-2"
        >
            <p class="font-medium text-foreground">Session window</p>
            <p>
                {{ props.sessionDate ?? 'Session date' }}
                <span v-if="props.sessionStartTime || props.sessionEndTime">
                    · {{ props.sessionStartTime ?? 'start' }}–{{ props.sessionEndTime ?? 'end' }}
                </span>
            </p>
            <p>
                QR opening and closing times must stay inside this session window. Closing time must be after opening
                time.
            </p>
        </div>

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
