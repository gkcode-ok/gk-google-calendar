<template>
  <v-card variant="outlined" class="gcal-card pa-4">
    <div class="d-flex align-center ga-3 mb-3">
      <v-icon size="32" color="primary">mdi-google</v-icon>
      <div class="flex-grow-1">
        <div class="font-weight-bold">Google Calendar</div>
        <div class="text-caption text-medium-emphasis">Sincroniza tu agenda con tu calendario de Google.</div>
      </div>
    </div>

    <v-alert v-if="data.last_error" type="error" variant="tonal" density="compact" class="mb-3">{{ data.last_error }}</v-alert>
    <v-alert v-else-if="data.status === 'pending'" type="info" variant="tonal" density="compact" class="mb-3">
      Autoriza el acceso en la ventana de Google y luego verifica la autorizacion.
    </v-alert>

    <div v-if="data.status === 'connected'" class="gcal-grid mb-3">
      <div><span class="text-caption text-medium-emphasis">Calendario</span><div>{{ data.calendar_summary || 'Principal' }}</div></div>
      <div><span class="text-caption text-medium-emphasis">Zona horaria</span><div>{{ data.calendar_timezone || '-' }}</div></div>
      <div><span class="text-caption text-medium-emphasis">Ultima sincronizacion</span><div>{{ formatDate(data.last_sync_at) }}</div></div>
    </div>

    <div class="d-flex flex-wrap ga-2">
      <template v-if="data.status === 'connected'">
        <v-btn color="primary" variant="tonal" prepend-icon="mdi-calendar-check" @click="emit('select-calendar')">Elegir calendario</v-btn>
        <v-btn color="error" variant="text" prepend-icon="mdi-link-off" @click="emit('disconnect')">Desconectar</v-btn>
      </template>
      <template v-else-if="data.status === 'pending'">
        <v-btn color="primary" :loading="busy" prepend-icon="mdi-refresh" @click="emit('verify')">Verificar autorizacion</v-btn>
        <v-btn variant="text" @click="emit('connect')">Reintentar conexion</v-btn>
      </template>
      <v-btn v-else color="primary" :loading="busy" prepend-icon="mdi-google" @click="emit('connect')">Conectar Google Calendar</v-btn>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: { type: Object, default: () => ({ status: 'not_configured' }) },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(['connect', 'verify', 'select-calendar', 'disconnect']);

const CHIPS = {
  connected: { state: 'green', text: 'Conectado' },
  pending: { state: 'yellow', text: 'Pendiente de autorizacion' },
  error: { state: 'red', text: 'Con error' },
  disconnected: { state: 'grey', text: 'Desconectado' },
  not_configured: { state: 'grey', text: 'No configurado' },
};
const chip = computed(() => CHIPS[props.data.status] || CHIPS.not_configured);

function formatDate(value) {
  return value ? new Date(value).toLocaleString('es-AR') : 'Sin sincronizaciones';
}
</script>

<style scoped>
.gcal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
</style>
