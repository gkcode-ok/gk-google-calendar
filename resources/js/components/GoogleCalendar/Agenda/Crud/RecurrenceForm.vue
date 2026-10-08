<template>
  <div class="recurrence">
    <v-select v-model="frequency" :items="options" item-title="title" item-value="value" label="Repetir"
      variant="outlined" density="comfortable" hide-details="auto" prepend-inner-icon="mdi-repeat" />

    <template v-if="frequency">
      <div class="recurrence-row">
        <span class="recurrence-label">Cada</span>
        <v-text-field v-model.number="rule.interval" type="number" min="1" max="999" variant="outlined"
          density="compact" hide-details class="recurrence-interval" />
        <span class="recurrence-label">{{ unitLabel }}</span>
      </div>

      <div v-if="frequency === 'WEEKLY'" class="recurrence-days">
        <v-btn v-for="day in DAYS" :key="day.value" :variant="rule.by_day.includes(day.value) ? 'flat' : 'outlined'"
          :color="rule.by_day.includes(day.value) ? 'primary' : undefined" icon size="34" :title="day.title"
          @click="toggleDay(day.value)">
          {{ day.short }}
        </v-btn>
      </div>

      <div class="recurrence-end">
        <div class="recurrence-label mb-1">Finaliza</div>
        <v-radio-group v-model="rule.end_type" hide-details density="compact">
          <v-radio value="never" label="Nunca" />
          <div class="recurrence-end-row">
            <v-radio value="until" label="El" />
            <v-text-field v-model="rule.until" type="date" variant="outlined" density="compact" hide-details
              :disabled="rule.end_type !== 'until'" class="recurrence-end-input" />
          </div>
          <div class="recurrence-end-row">
            <v-radio value="count" label="Después de" />
            <v-text-field v-model.number="rule.count" type="number" min="1" max="999" variant="outlined"
              density="compact" hide-details :disabled="rule.end_type !== 'count'" class="recurrence-end-input" />
            <span class="recurrence-label">repeticiones</span>
          </div>
        </v-radio-group>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

/**
 * Selector de recurrencia estilo Google Calendar.
 * v-model: null (no se repite) o { freq, interval, by_day, end_type, until, count, readonly }.
 */
const props = defineProps({
  modelValue: { type: Object, default: null },
  startsAt: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const DAYS = [
  { value: 'MO', short: 'L', title: 'Lunes' },
  { value: 'TU', short: 'M', title: 'Martes' },
  { value: 'WE', short: 'X', title: 'Miércoles' },
  { value: 'TH', short: 'J', title: 'Jueves' },
  { value: 'FR', short: 'V', title: 'Viernes' },
  { value: 'SA', short: 'S', title: 'Sábado' },
  { value: 'SU', short: 'D', title: 'Domingo' },
];

const UNITS = { DAILY: 'día(s)', WEEKLY: 'semana(s)', MONTHLY: 'mes(es)', YEARLY: 'año(s)' };

const options = [
  { title: 'No se repite', value: '' },
  { title: 'Diariamente', value: 'DAILY' },
  { title: 'Semanalmente', value: 'WEEKLY' },
  { title: 'Mensualmente', value: 'MONTHLY' },
  { title: 'Anualmente', value: 'YEARLY' },
];

const rule = reactive(normalize(props.modelValue));

function normalize(value) {
  return {
    freq: value?.freq || '',
    interval: value?.interval || 1,
    by_day: [...(value?.by_day || [])],
    end_type: value?.end_type || 'never',
    until: value?.until || '',
    count: value?.count || 10,
    readonly: !!value?.readonly,
  };
}

// Día de la semana (MO..SU) de la fecha de inicio del evento.
const startDay = computed(() => {
  const date = new Date(props.startsAt);
  return Number.isNaN(date.getTime()) ? 'MO' : DAYS[(date.getDay() + 6) % 7].value;
});

const frequency = computed({
  get: () => rule.freq,
  set: (value) => {
    rule.freq = value;
    if (value === 'WEEKLY' && !rule.by_day.length) rule.by_day = [startDay.value];
    publish();
  },
});

const unitLabel = computed(() => UNITS[rule.freq] || '');

function toggleDay(day) {
  const index = rule.by_day.indexOf(day);
  if (index === -1) rule.by_day.push(day);
  else if (rule.by_day.length > 1) rule.by_day.splice(index, 1);
}

function publish() {
  emit('update:modelValue', rule.freq ? { ...rule, by_day: [...rule.by_day] } : null);
}

watch(rule, publish, { deep: true });

watch(() => props.modelValue, (value) => {
  const next = normalize(value);
  if (JSON.stringify(next) !== JSON.stringify({ ...rule, by_day: [...rule.by_day] })) {
    Object.assign(rule, next);
  }
});
</script>

<style scoped>
.recurrence {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.recurrence-row,
.recurrence-end-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.recurrence-label {
  font-size: 14px;
  color: #3c4043;
}

.recurrence-interval {
  max-width: 90px;
}

.recurrence-days {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.recurrence-end-input {
  max-width: 190px;
}
</style>
