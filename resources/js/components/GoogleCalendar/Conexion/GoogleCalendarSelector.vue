<template>
  <v-dialog v-model="model" max-width="480">
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <v-icon color="primary">mdi-calendar-check</v-icon>
        Elegir calendario
      </v-card-title>
      <v-card-text>
        <v-progress-linear v-if="loading" indeterminate class="mb-3" />
        <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
        <v-radio-group v-else v-model="selected" hide-details>
          <v-radio v-for="item in calendars" :key="item.id" :value="item.id" :label="item.summary + (item.primary ? ' (principal)' : '')" />
        </v-radio-group>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cancelar</v-btn>
        <v-btn color="primary" variant="flat" :disabled="!selected" :loading="saving" @click="save">Guardar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { getCurrentInstance, ref, watch } from 'vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({ current: { type: String, default: null } });
const emit = defineEmits(['saved']);

const $gkaxios = getCurrentInstance()?.proxy?.$gkaxios;
const calendars = ref([]);
const selected = ref(null);
const loading = ref(false);
const saving = ref(false);
const error = ref('');

watch(model, async (open) => {
  if (!open) return;
  error.value = '';
  loading.value = true;
  try {
    const result = await $gkaxios.get('/home/GoogleCalendar/calendarios');
    if (result?.status !== 0) throw new Error(result?.descripcion);
    calendars.value = result.data || [];
    selected.value = props.current || calendars.value.find((c) => c.primary)?.id || null;
  } catch (e) {
    error.value = e?.response?.data?.descripcion || e?.message || 'No se pudieron cargar los calendarios.';
  } finally {
    loading.value = false;
  }
});

async function save() {
  saving.value = true;
  try {
    const result = await $gkaxios.post('/home/GoogleCalendar/calendario-principal', { calendar_id: selected.value });
    if (result?.status !== 0) throw new Error(result?.descripcion);
    emit('saved', result.data);
    model.value = false;
  } catch (e) {
    error.value = e?.response?.data?.descripcion || e?.message || 'No se pudo guardar el calendario.';
  } finally {
    saving.value = false;
  }
}
</script>
