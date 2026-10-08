<template>
  <div class="reminders">
    <div v-if="modelValue === null" class="reminders-default">
      <v-icon size="18">mdi-bell-outline</v-icon>
      <span>Se usarán las notificaciones predeterminadas del calendario.</span>
    </div>

    <div v-for="(item, index) in items" :key="index" class="reminders-row">
      <v-select v-model="item.method" :items="METHODS" item-title="title" item-value="value" variant="outlined"
        density="compact" hide-details class="reminders-method" @update:model-value="publish" />

      <v-text-field v-model.number="item.amount" type="number" min="0" variant="outlined" density="compact"
        hide-details class="reminders-amount" @update:model-value="publish" />

      <v-select v-model="item.unit" :items="UNITS" item-title="title" item-value="value" variant="outlined"
        density="compact" hide-details class="reminders-unit" @update:model-value="publish" />

      <span class="reminders-suffix">antes</span>

      <v-btn icon="mdi-close" variant="text" size="small" aria-label="Quitar notificación" @click="remove(index)" />
    </div>

    <div class="reminders-actions">
      <v-btn v-if="items.length < MAX" variant="text" color="primary" class="text-none" prepend-icon="mdi-plus"
        @click="add">
        Agregar notificación
      </v-btn>

      <v-btn v-if="modelValue !== null" variant="text" class="text-none" @click="reset">
        Usar predeterminadas
      </v-btn>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

/**
 * Notificaciones del evento estilo Google Calendar.
 * v-model: null (predeterminadas del calendario) o [{ method: 'popup'|'email', minutes }].
 */
const props = defineProps({
  modelValue: { type: Array, default: null },
});

const emit = defineEmits(['update:modelValue']);

const MAX = 5;

const METHODS = [
  { title: 'Notificación', value: 'popup' },
  { title: 'Correo electrónico', value: 'email' },
];

const UNITS = [
  { title: 'minutos', value: 1 },
  { title: 'horas', value: 60 },
  { title: 'días', value: 1440 },
  { title: 'semanas', value: 10080 },
];

const items = ref([]);

// Elige la unidad más grande que divide exactamente los minutos.
function fromMinutes(reminder) {
  const minutes = Number(reminder.minutes) || 0;
  const unit = [...UNITS].reverse().find((u) => minutes > 0 && minutes % u.value === 0)?.value ?? 1;
  return { method: reminder.method, amount: minutes / unit, unit };
}

function toMinutes(item) {
  return Math.max(0, Math.round(Number(item.amount) || 0)) * item.unit;
}

function serialize() {
  return items.value.map((item) => ({ method: item.method, minutes: toMinutes(item) }));
}

function publish() {
  emit('update:modelValue', serialize());
}

function add() {
  items.value.push({ method: 'popup', amount: 10, unit: 1 });
  publish();
}

function remove(index) {
  items.value.splice(index, 1);
  publish();
}

function reset() {
  items.value = [];
  emit('update:modelValue', null);
}

watch(() => props.modelValue, (value) => {
  if (value === null) {
    items.value = [];
  } else if (JSON.stringify(value) !== JSON.stringify(serialize())) {
    items.value = value.map(fromMinutes);
  }
}, { immediate: true });
</script>

<style scoped>
.reminders {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.reminders-default {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #5f6368;
}

.reminders-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.reminders-method {
  max-width: 220px;
}

.reminders-amount {
  max-width: 100px;
}

.reminders-unit {
  max-width: 150px;
}

.reminders-suffix {
  font-size: 14px;
  color: #3c4043;
}

.reminders-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
