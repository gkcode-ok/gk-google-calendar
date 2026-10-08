<template>
  <ContenedorIndex comentario="Configuracion de Google Calendar" :style="'width:100%;'" class="ContenedorIndex">
    <template #cabecera />
    <template #icon><v-icon size="22" color="primary">mdi-google</v-icon></template>
    <template #titulo>
      <div class="gcal-title">
        <span class="gcal-title__heading">Google Calendar</span>
        <span class="gcal-title__caption">Sincroniza tu agenda con Google</span>
      </div>
    </template>
    <template #main />
    <template #Tabla>
      <div class="gcal-page">
        <GoogleCalendarStatusCard
          :data="status"
          :busy="busy"
          @connect="conectar"
          @verify="verificar"
          @select-calendar="selectorOpen = true"
          @disconnect="disconnectOpen = true"
        />
      </div>
    </template>
  </ContenedorIndex>

  <GoogleCalendarSelector v-model="selectorOpen" :current="status.calendar_id" @saved="onSaved" />
  <GoogleCalendarDisconnectDialog v-model="disconnectOpen" :loading="disconnecting" @confirm="desconectar" />

  <Load :load="loading" />
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3200">{{ snackbar.text }}</v-snackbar>
</template>

<script setup>
import { getCurrentInstance, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import Load from '@Components/Otros/Load/index.vue';
import GoogleCalendarStatusCard from './GoogleCalendarStatusCard.vue';
import GoogleCalendarSelector from './GoogleCalendarSelector.vue';
import GoogleCalendarDisconnectDialog from './GoogleCalendarDisconnectDialog.vue';

const $gkaxios = getCurrentInstance()?.proxy?.$gkaxios;

const POLL_INTERVAL_MS = 3000;
const POLL_MAX_ATTEMPTS = 40;

const loading = ref(false);
const busy = ref(false);
const disconnecting = ref(false);
const selectorOpen = ref(false);
const disconnectOpen = ref(false);
const status = ref({ status: 'not_configured' });
const snackbar = reactive({ show: false, text: '', color: 'success' });
let pollTimer = null;

onMounted(loadEstado);
onBeforeUnmount(stopPolling);

async function loadEstado() {
  loading.value = true;
  try {
    const result = await $gkaxios.get('/home/GoogleCalendar/estado');
    if (result?.status === 0) status.value = result.data;
  } catch (e) {
    notify('No se pudo cargar el estado de Google Calendar.', 'error');
  } finally {
    loading.value = false;
  }
}

async function conectar() {
  busy.value = true;

  // Abrimos inmediatamente la ventana para evitar bloqueo del navegador
  const ventana = window.open('', '_blank');

  try {
    const result = await $gkaxios.post('/home/GoogleCalendar/conectar', {});
    console.log('Navegando a la URL de autorización:', result);
    console.log('Respuesta conectar Google:', result);
    console.log('Authorization URL:', result?.authorization_url);
    console.log('Authorization URL data:', result?.data?.authorization_url);

    if (result?.data) {
      status.value = result.data;
    }

    if (result?.status !== 0) {
      throw new Error(result?.descripcion || 'No se pudo iniciar la conexión.');
    }

    const authorizationUrl =
      result?.authorization_url ||
      result?.data?.authorization_url;

    if (!authorizationUrl) {
      ventana?.close();

      throw new Error(
        'GK Servicios no devolvió la URL de autorización de Google.'
      );
    }

    // Ahora navegamos la pestaña que ya estaba abierta
    ventana.location.href = result?.data?.authorization_url;

    startPolling();

  } catch (e) {
    ventana?.close();

    notify(
      e?.response?.data?.descripcion ||
      e?.message ||
      'No se pudo iniciar la conexión.',
      'error'
    );
  } finally {
    busy.value = false;
  }
}

// Consulta el estado mientras el usuario autoriza en la ventana de Google.
function startPolling() {
  stopPolling();
  let attempts = 0;
  pollTimer = setInterval(async () => {
    attempts++;
    const connected = await verificar(true);
    if (connected || attempts >= POLL_MAX_ATTEMPTS) stopPolling();
  }, POLL_INTERVAL_MS);
}

function stopPolling() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = null;
}

async function verificar(silent = false) {
  try {
    const result = await $gkaxios.get('/home/GoogleCalendar/oauth-status');
    if (result?.data) status.value = result.data;
    if (result?.status !== 0) throw new Error(result?.descripcion);
    if (status.value.status === 'connected') {
      stopPolling();
      notify('Google Calendar conectado correctamente.');
      selectorOpen.value = true;
      return true;
    }
    if (silent !== true) notify(result.descripcion, 'warning');
  } catch (e) {
    if (silent !== true) notify(e?.response?.data?.descripcion || e?.message || 'No se pudo verificar la autorizacion.', 'error');
  }
  return false;
}

function onSaved(data) {
  status.value = data;
  notify('Calendario guardado correctamente.');
}

async function desconectar() {
  disconnecting.value = true;
  try {
    const result = await $gkaxios.put('/home/GoogleCalendar/desconectar');
    if (result?.status !== 0) throw new Error(result?.descripcion);
    status.value = result.data;
    disconnectOpen.value = false;
    notify('Google Calendar fue desconectado correctamente.');
  } catch (e) {
    notify(e?.response?.data?.descripcion || e?.message || 'No se pudo desconectar Google Calendar.', 'error');
  } finally {
    disconnecting.value = false;
  }
}

function notify(text, color = 'success') {
  snackbar.text = text;
  snackbar.color = color;
  snackbar.show = true;
}
</script>

<style scoped>
.gcal-title { display: flex; flex-direction: column; }
.gcal-title__heading { font-weight: 900; font-size: 1rem; }
.gcal-title__caption { font-size: .74rem; color: #607086; }
.gcal-page { padding: 6px 2px; }
</style>
