<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(defineProps<{
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
}>(), {
    type: 'text',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);
const visibleMonth = ref(dateFromValue(props.modelValue) ?? new Date());
const rangeStart = ref('');
const rangeEnd = ref('');
const timeHour = ref('00');
const timeMinute = ref('00');

const customMode = computed<'date' | 'date-range' | 'time' | null>(() => {
    if (props.type === 'date') return 'date';
    if (props.type === 'date-range' || props.type === 'daterange') return 'date-range';
    if (props.type === 'time') return 'time';
    return null;
});
const usesCustomPicker = computed(() => Boolean(customMode.value));
const monthTitle = computed(() => visibleMonth.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const hourOptions = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, '0'));
const minuteOptions = Array.from({ length: 12 }, (_, index) => String(index * 5).padStart(2, '0'));

const displayValue = computed(() => {
    if (customMode.value === 'date') return formatDisplayDate(props.modelValue) || props.placeholder || 'Select date';
    if (customMode.value === 'date-range') {
        const parsed = parseRange(props.modelValue);
        if (parsed.start && parsed.end) return `${formatDisplayDate(parsed.start)} – ${formatDisplayDate(parsed.end)}`;
        if (parsed.start) return `${formatDisplayDate(parsed.start)} – End date`;
        return props.placeholder || 'Select date range';
    }
    if (customMode.value === 'time') return props.modelValue || props.placeholder || 'Select time';
    return props.modelValue;
});

const calendarDays = computed(() => {
    const year = visibleMonth.value.getFullYear();
    const month = visibleMonth.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const firstOffset = (firstDay.getDay() + 6) % 7;
    const startDate = new Date(year, month, 1 - firstOffset);
    const today = toDateValue(new Date());

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + index);
        const value = toDateValue(date);
        const day = date.getDay();

        return {
            key: value,
            value,
            day: date.getDate(),
            inMonth: date.getMonth() === month,
            isToday: value === today,
            isWeekend: day === 0 || day === 6,
            isSelected: isSelectedDate(value),
            isInRange: isDateInsideRange(value),
        };
    });
});

watch(
    () => props.modelValue,
    (value) => {
        const date = dateFromValue(value);
        if (date) visibleMonth.value = date;
        if (customMode.value === 'date-range') {
            const parsed = parseRange(value);
            rangeStart.value = parsed.start;
            rangeEnd.value = parsed.end;
        }
        if (customMode.value === 'time') {
            const [hour = '00', minute = '00'] = value.split(':');
            timeHour.value = normalizedHour(hour);
            timeMinute.value = normalizedMinute(minute);
        }
    },
    { immediate: true },
);

function toDateValue(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function dateFromValue(value: string): Date | null {
    const match = value.match(/\d{4}-\d{2}-\d{2}/);
    if (!match) return null;
    const [year, month, day] = match[0].split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDisplayDate(value: string): string {
    const date = dateFromValue(value);
    return date ? date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '';
}

function parseRange(value: string): { start: string; end: string } {
    const matches = value.match(/\d{4}-\d{2}-\d{2}/g) ?? [];
    return { start: matches[0] ?? '', end: matches[1] ?? '' };
}

function normalizedHour(value: string): string {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? String(Math.min(Math.max(parsed, 0), 23)).padStart(2, '0') : '00';
}

function normalizedMinute(value: string): string {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) return '00';
    const rounded = Math.min(Math.max(Math.round(parsed / 5) * 5, 0), 55);
    return String(rounded).padStart(2, '0');
}

function isSelectedDate(value: string): boolean {
    if (customMode.value === 'date') return props.modelValue === value;
    if (customMode.value === 'date-range') return value === rangeStart.value || value === rangeEnd.value;
    return false;
}

function isDateInsideRange(value: string): boolean {
    if (customMode.value !== 'date-range' || !rangeStart.value || !rangeEnd.value) return false;
    return value > rangeStart.value && value < rangeEnd.value;
}

function selectDate(value: string) {
    if (customMode.value === 'date') {
        emit('update:modelValue', value);
        open.value = false;
        return;
    }

    if (customMode.value !== 'date-range') return;

    if (!rangeStart.value || (rangeStart.value && rangeEnd.value) || value < rangeStart.value) {
        rangeStart.value = value;
        rangeEnd.value = '';
        emit('update:modelValue', value);
        return;
    }

    rangeEnd.value = value;
    emit('update:modelValue', `${rangeStart.value} – ${rangeEnd.value}`);
    open.value = false;
}

function moveMonth(amount: number) {
    visibleMonth.value = new Date(visibleMonth.value.getFullYear(), visibleMonth.value.getMonth() + amount, 1);
}

function updateTime() {
    emit('update:modelValue', `${timeHour.value}:${timeMinute.value}`);
}
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between gap-3">
            <Label :for="id">{{ label }}</Label>
            <span v-if="props.required" class="text-xs text-muted-foreground">Required</span>
        </div>

        <div v-if="usesCustomPicker" class="relative">
            <button
                :id="id"
                type="button"
                :disabled="props.disabled"
                :aria-invalid="Boolean(props.error)"
                class="flex h-10 w-full items-center justify-between rounded-lg border border-input bg-background px-3 text-left text-sm text-foreground shadow-sm transition hover:border-ring/50 focus:ring-2 focus:ring-ring/25 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                @click="open = !open"
            >
                <span :class="props.modelValue ? '' : 'text-muted-foreground'">{{ displayValue }}</span>
                <span class="text-muted-foreground">{{ customMode === 'time' ? '⌄' : '×' }}</span>
            </button>

            <div
                v-if="open"
                class="absolute z-40 mt-2 w-full min-w-[20rem] rounded-xl border border-border bg-card p-4 shadow-xl sm:w-[22rem]"
            >
                <template v-if="customMode === 'time'">
                    <div class="grid gap-4">
                        <div class="grid grid-cols-2 gap-3">
                            <label class="grid gap-2 text-xs font-semibold text-muted-foreground">
                                Hour
                                <select
                                    v-model="timeHour"
                                    class="h-11 rounded-xl border border-input bg-background px-3 text-sm text-foreground focus:ring-2 focus:ring-ring/25 focus:outline-none"
                                    @change="updateTime"
                                >
                                    <option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}</option>
                                </select>
                            </label>
                            <label class="grid gap-2 text-xs font-semibold text-muted-foreground">
                                Minute
                                <select
                                    v-model="timeMinute"
                                    class="h-11 rounded-xl border border-input bg-background px-3 text-sm text-foreground focus:ring-2 focus:ring-ring/25 focus:outline-none"
                                    @change="updateTime"
                                >
                                    <option v-for="minute in minuteOptions" :key="minute" :value="minute">{{ minute }}</option>
                                </select>
                            </label>
                        </div>
                        <div class="rounded-2xl border border-border bg-muted/40 p-4 text-center text-3xl font-black tracking-tight">
                            {{ timeHour }}:{{ timeMinute }}
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-lg border px-3 py-2 text-sm font-semibold" @click="open = false">Done</button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <button type="button" class="rounded-lg px-3 py-2 text-xl hover:bg-muted" @click="moveMonth(-1)">‹</button>
                        <div class="text-sm font-black">{{ monthTitle }}</div>
                        <button type="button" class="rounded-lg px-3 py-2 text-xl hover:bg-muted" @click="moveMonth(1)">›</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-muted-foreground">
                        <div v-for="weekday in weekdays" :key="weekday" class="py-2">{{ weekday }}</div>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-sm">
                        <button
                            v-for="day in calendarDays"
                            :key="day.key"
                            type="button"
                            class="h-10 rounded-xl transition hover:bg-primary/15"
                            :class="[
                                day.inMonth ? 'text-foreground' : 'text-muted-foreground/40',
                                day.isWeekend && day.inMonth ? 'text-destructive' : '',
                                day.isInRange ? 'bg-primary/10 text-primary' : '',
                                day.isSelected ? 'bg-primary text-primary-foreground shadow-sm ring-2 ring-primary/25' : '',
                                day.isToday && !day.isSelected ? 'ring-1 ring-primary/40' : '',
                            ]"
                            @click="selectDate(day.value)"
                        >
                            {{ day.day }}
                        </button>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span v-if="customMode === 'date-range'">{{ displayValue }}</span>
                        <button type="button" class="rounded-lg border px-3 py-2 text-sm font-semibold text-foreground" @click="open = false">Close</button>
                    </div>
                </template>
            </div>
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
            :step="props.step"
            :aria-invalid="Boolean(props.error)"
            class="h-10 rounded-lg border-input bg-background text-foreground"
            @update:model-value="emit('update:modelValue', String($event ?? ''))"
        />
        <p v-if="props.help && !props.error" class="text-xs leading-5 text-muted-foreground">{{ props.help }}</p>
        <InputError :message="props.error" />
    </div>
</template>
