<template>
  <ContenedorIndex comentario="Contenedor Base de Agenda Google" :style="'width:100%;'">
    <template #cabecera />

    <!-- Ícono estilo Google Calendar -->
    <template #icon>
      <div class="gcal-header-logo">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true">
          <path fill="#4285F4" d="M19 4h-1V2h-2v2H8V2H6v2H5C3.89 4 3 4.9 3 6v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/>
          <path fill="#34A853" d="M7 12h5v5H7z"/>
        </svg>
      </div>
    </template>

    <!-- Título + Píldora de estado del calendario actual -->
    <template #titulo>
      <div class="gcal-header-title-wrap">
        <div class="d-flex align-center flex-wrap ga-2">
          <span class="gcal-header-title">Google Calendar</span>

          <button
            type="button"
            class="gcal-account-pill"
            :class="isConnected ? 'gcal-account-pill--connected' : 'gcal-account-pill--disconnected'"
            :title="isConnected ? 'Configuración de cuenta y calendario' : 'Conectar cuenta de Google'"
            @click="showConnectionPanel = !showConnectionPanel"
          >
            <span class="gcal-account-pill__dot" />
            <span class="gcal-account-pill__text">
              {{ isConnected ? (activeCalendarLabel || 'Calendario principal') : 'Sin conectar' }}
            </span>
            <v-icon size="15" class="ml-1">
              {{ showConnectionPanel ? 'mdi-chevron-up' : 'mdi-chevron-down' }}
            </v-icon>
          </button>
        </div>

        <span class="gcal-header-subtitle">
          {{ isConnected
            ? `Sincronizado con Google · ${connectionStatus.calendar_timezone || 'Hora local'}`
            : 'Vinculá tu cuenta de Google para gestionar tus eventos en tiempo real'
          }}
        </span>
      </div>
    </template>

    <!-- Barra de herramientas superior estilo Google Workspace -->
    <template #botonCrear>
      <div class="gcal-header-actions">
        <!-- Botón rápido para elegir calendario cuando está conectado -->
        <v-tooltip v-if="isConnected" text="Cambiar calendario de Google" location="bottom">
          <template #activator="{ props: tooltipProps }">
            <v-btn
              v-bind="tooltipProps"
              icon="mdi-calendar-sync-outline"
              variant="text"
              size="small"
              class="gcal-icon-btn"
              @click="openCalendarSelector"
            />
          </template>
        </v-tooltip>

        <!-- Botón para abrir/cerrar panel de conexión de cuenta Google -->
        <v-tooltip :text="showConnectionPanel ? 'Ocultar configuración de cuenta' : 'Cuenta de Google Calendar'" location="bottom">
          <template #activator="{ props: tooltipProps }">
            <v-btn
              v-bind="tooltipProps"
              :icon="showConnectionPanel ? 'mdi-cog' : 'mdi-cog-outline'"
              :color="showConnectionPanel ? '#1a73e8' : undefined"
              variant="text"
              size="small"
              class="gcal-icon-btn"
              @click="showConnectionPanel = !showConnectionPanel"
            />
          </template>
        </v-tooltip>

        <!-- Selector de vista (Calendario / Lista) estilo Material 3 -->
        <div v-if="isConnected" class="gcal-segmented">
          <button
            type="button"
            class="gcal-segmented__btn"
            :class="{ 'gcal-segmented__btn--active': viewMode === 'calendar' }"
            @click="viewMode = 'calendar'"
          >
            <v-icon size="16">mdi-calendar-month-outline</v-icon>
            <span>Calendario</span>
          </button>
          <button
            type="button"
            class="gcal-segmented__btn"
            :class="{ 'gcal-segmented__btn--active': viewMode === 'list' }"
            @click="viewMode = 'list'"
          >
            <v-icon size="16">mdi-format-list-bulleted</v-icon>
            <span>Agenda</span>
          </button>
        </div>

        <!-- Botón Crear Evento -->
        <Crud v-if="isConnected && objAcceso.creacion" :event="[]" :tipo="'Nuevo'" @refresh="toUpdateHandler" />
      </div>
    </template>

    <template #main />

    <!-- Buscador (solo visible cuando está conectado) -->
    <template #Filtro>
      <template v-if="isConnected">
        <Buscador @refreshBuscador="buscarHandler" :find="filters" :titulo="'Buscar en mi Google Calendar'" />
        <hr class="gcal-divider" />
      </template>
    </template>

    <template #Tabla>
      <div class="gcal-workspace">
        <!-- Panel unificado de Conexión y Configuración de Google Calendar (GoogleCalendarAdmin) -->
        <v-expand-transition>
          <div v-show="!isConnected || showConnectionPanel" class="gcal-connection-wrapper">
            <div v-if="isConnected && showConnectionPanel" class="gcal-panel-bar">
              <span class="gcal-panel-bar__label">
                <v-icon size="16" color="#1a73e8" class="mr-1">mdi-google</v-icon>
                Configuración de conexión con Google Calendar
              </span>
              <v-btn
                variant="text"
                size="small"
                rounded="pill"
                class="text-none"
                prepend-icon="mdi-close"
                @click="showConnectionPanel = false"
              >
                Cerrar panel
              </v-btn>
            </div>

            <GoogleCalendarAdmin
              ref="connectionRef"
              :embedded="true"
              :compact="isConnected"
              @status-change="onStatusChange"
              @calendar-saved="onCalendarSaved"
              @disconnected="onDisconnected"
            />
          </div>
        </v-expand-transition>

        <!-- Contenido principal de la Agenda (cuando la cuenta está conectada) -->
        <template v-if="isConnected">
          <div v-if="error" class="gcal-error-strip">
            <v-icon size="18" color="#d93025">mdi-alert-circle-outline</v-icon>
            <span>{{ error }}</span>
            <v-spacer />
            <v-btn size="small" variant="text" color="#1a73e8" rounded="pill" class="text-none" @click="index">
              Reintentar
            </v-btn>
          </div>

          <!-- VISTA LISTA (AGENDA) -->
          <div v-if="viewMode === 'list'" class="gcal-list-surface">
            <div class="gcal-list-toolbar">
              <div class="d-flex align-center ga-2">
                <v-btn
                  variant="outlined"
                  rounded="pill"
                  size="small"
                  class="gcal-today-btn text-none"
                  @click="goCurrentMonth"
                >
                  Este mes
                </v-btn>
                <v-btn icon="mdi-chevron-left" variant="text" size="small" @click="moveMonth(-1)" />
                <v-btn icon="mdi-chevron-right" variant="text" size="small" @click="moveMonth(1)" />
                <h3 class="gcal-list-month-title">{{ monthLabel }}</h3>
              </div>

              <div class="d-flex align-center ga-2">
                <span class="gcal-count-pill">
                  {{ (object.data || []).length }} {{ (object.data || []).length === 1 ? 'evento' : 'eventos' }}
                </span>
                <v-btn
                  icon="mdi-refresh"
                  variant="text"
                  size="small"
                  title="Actualizar eventos"
                  @click="index"
                />
              </div>
            </div>

            <Tabla
              :fields="fields"
              :data="object['data']"
              ref="vuetable"
              :opciones="TablaOpciones"
              :name="'tablaAgendaGoogle'"
            >
              <template #title="props">
                <div class="gcal-event-cell">
                  <span class="gcal-event-cell__dot" />
                  <div>
                    <div class="gcal-event-cell__title">
                      {{ props.rowData.title }}
                      <v-chip
                        v-if="props.rowData.all_day"
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        class="ml-2"
                      >
                        Todo el día
                      </v-chip>
                    </div>
                    <div v-if="props.rowData.location" class="gcal-event-cell__location">
                      <v-icon size="13" class="mr-1">mdi-map-marker-outline</v-icon>
                      {{ props.rowData.location }}
                    </div>
                  </div>
                </div>
              </template>

              <template #starts_at="props">
                <div class="gcal-date-cell">
                  <v-icon
                    v-if="props.rowData.is_recurring"
                    size="15"
                    color="#1a73e8"
                    class="mr-1"
                    title="Evento recurrente"
                  >
                    mdi-repeat
                  </v-icon>
                  <v-icon size="15" color="#5f6368" class="mr-1">mdi-clock-outline</v-icon>
                  <span>{{ formatDate(props.rowData.starts_at, props.rowData.all_day) }}</span>
                </div>
              </template>

              <template #ends_at="props">
                <span class="gcal-date-cell gcal-date-cell--muted">
                  {{ formatDate(props.rowData.ends_at, props.rowData.all_day) }}
                </span>
              </template>

              <template #accion="props">
                <div class="d-flex justify-center align-center flex-wrap ga-1">
                  <v-tooltip v-if="props.rowData.html_link" text="Abrir en Google Calendar" location="top">
                    <template #activator="{ props: tooltipProps }">
                      <v-btn
                        v-bind="tooltipProps"
                        :href="props.rowData.html_link"
                        target="_blank"
                        rel="noopener"
                        icon="mdi-open-in-new"
                        size="30"
                        variant="tonal"
                        color="primary"
                      />
                    </template>
                  </v-tooltip>

                  <Crud
                    v-if="objAcceso.modificacion"
                    :event="props.rowData"
                    :tipo="'Modificando'"
                    @refresh="toUpdateHandler"
                  />

                  <v-tooltip
                    v-if="objAcceso.eliminacion && props.rowData.recurring_event_id"
                    text="Eliminar evento recurrente"
                    location="top"
                  >
                    <template #activator="{ props: tooltipProps }">
                      <v-btn
                        v-bind="tooltipProps"
                        icon
                        size="30"
                        variant="tonal"
                        elevation="0"
                        class="table-btn-action"
                        @click="askDeleteRecurring(props.rowData)"
                      >
                        <v-icon size="16">mdi-delete</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>

                  <BotonEliminar
                    v-else-if="objAcceso.eliminacion"
                    :id="props.rowData.id"
                    :page="basePage"
                    :tipo="'chico'"
                    @refresh="toUpdateHandler"
                  />
                </div>
              </template>
            </Tabla>
          </div>

          <!-- VISTA CALENDARIO ESTILO GOOGLE (MES / SEMANA / DÍA) -->
          <div v-else class="gcal-calendar-surface">
            <GoogleStyleCalendar
              :events="calendarEvents"
              @range-change="onRangeChange"
              @date-click="onDateClick"
              @event-click="onEventClick"
            />
          </div>
        </template>
      </div>
    </template>
  </ContenedorIndex>

  <v-dialog v-model="dialogCrud" fullscreen transition="dialog-bottom-transition" persistent>
    <div v-if="dialogCrud" class="h-100 bg-white">
      <CrudComponent :event="selected" :tipo="selected.id ? 'Modificando' : 'Nuevo'" @close="closeCrud" class="h-100" />
    </div>
  </v-dialog>

  <RecurrenceScopeDialog v-model="scopeDialog" title="Eliminar evento recurrente" danger @confirm="deleteRecurring" />
  <Load :load="load" />
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, getCurrentInstance } from 'vue';
import {
  acceso as accesoUtil,
  buscar as buscarUtil,
  toUpdate as toUpdateUtil,
  getData
} from "@GKModule/ComponentFuc";

import GoogleCalendarAdmin from '../Conexion/index.vue';
import Crud from './Crud/button.vue';
import CrudComponent from './Crud/crud.vue';
import BotonEliminar from "@Components/Otros/BtnDelete/index.vue";
import GoogleStyleCalendar from '../../Calendar/GoogleStyleCalendar.vue';
import RecurrenceScopeDialog from './Crud/RecurrenceScopeDialog.vue';

const { appContext } = getCurrentInstance();
const $gkaxios = appContext.config.globalProperties.$gkaxios;
const dataContext = reactive(getData());

const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const basePage = '/home/GoogleCalendar/agenda';

const connectionRef = ref(null);
const connectionStatus = ref({ status: 'not_configured' });
const showConnectionPanel = ref(false);

const object = ref({ data: [] });
const objAcceso = ref({});
const load = ref(false);
const error = ref('');
const calendarName = ref('');
const viewMode = ref('calendar');
const month = ref(new Date().getMonth() + 1);
const year = ref(new Date().getFullYear());
const range = ref(null);
const scopeDialog = ref(false);
const rowToDelete = ref(null);
const dialogCrud = ref(false);
const selected = ref({});

const isConnected = computed(() => connectionStatus.value?.status === 'connected');
const activeCalendarLabel = computed(() => connectionStatus.value?.calendar_summary || calendarName.value || 'Calendario principal');
const monthLabel = computed(() => `${MONTHS[month.value - 1]} ${year.value}`);

// Las fechas de día completo (YYYY-MM-DD) se interpretan como locales; su fin en Google es exclusivo.
function parseDate(value, exclusiveEnd = false) {
  if (typeof value === 'string' && value.length === 10) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d - (exclusiveEnd ? 1 : 0));
  }
  return new Date(value);
}

const calendarEvents = computed(() => (object.value.data || []).map((ev) => ({
  id: ev.id,
  title: ev.title,
  start: parseDate(ev.starts_at),
  end: parseDate(ev.ends_at || ev.starts_at, ev.all_day),
  color: '#1a73e8',
  allDay: ev.all_day,
  raw: ev,
})));

const filters = reactive({
  params: { title: '' },
  data_select: {},
  filters: [
    { span_text: "Título del Evento", input_type: "text", input_name: "title" },
  ]
});

const TablaOpciones = reactive({
  divtablaClass: "fixTableHead divscrollJuan col-sm-12",
  theadStyle: "font-size:13px;",
  tbodyStyle: "font-size:12.5px; vertical-align:middle;",
  tablaClass: "table table-striped tablaJuanClass"
});

const fields = reactive([
  { nombre: 'title', titulo: 'Evento' },
  { nombre: 'starts_at', titulo: 'Inicio' },
  { nombre: 'ends_at', titulo: 'Fin' },
  { nombre: 'accion', titulo: 'Acciones', tdstyle: "text-align: center" }
]);

const formatDate = (value, allDay) => {
  if (!value) return '';
  const date = new Date(value);
  return allDay
    ? date.toLocaleDateString('es-AR')
    : date.toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

function onStatusChange(nextStatus) {
  const wasConnected = isConnected.value;
  connectionStatus.value = nextStatus || { status: 'not_configured' };
  if (connectionStatus.value.calendar_summary) {
    calendarName.value = connectionStatus.value.calendar_summary;
  }
  if (!wasConnected && isConnected.value) {
    showConnectionPanel.value = false;
    index();
  }
}

function onCalendarSaved(nextStatus) {
  connectionStatus.value = nextStatus || connectionStatus.value;
  calendarName.value = nextStatus?.calendar_summary || calendarName.value;
  showConnectionPanel.value = false;
  index();
}

function onDisconnected(nextStatus) {
  connectionStatus.value = nextStatus || { status: 'disconnected' };
  object.value = { data: [] };
  calendarName.value = '';
}

function openCalendarSelector() {
  connectionRef.value?.openSelector();
}

const index = async () => {
  if (!isConnected.value) return;

  load.value = true;
  error.value = '';
  try {
    const buscadorParam = dataContext.buscador[1] ?? '';
    const period = viewMode.value === 'calendar' && range.value
      ? `from=${range.value.from}&to=${range.value.to}`
      : `filter_month=${month.value}&filter_year=${year.value}`;
    const url = `${basePage}?${period}&${buscadorParam}`;
    const result = await $gkaxios.get(url);
    if (result.status == 0) {
      object.value = { data: Array.isArray(result.data?.data) ? result.data.data : [] };
      calendarName.value = result.data?.calendar || connectionStatus.value?.calendar_summary || '';
    } else {
      object.value = { data: [] };
      error.value = result.descripcion;
    }
  } catch (e) {
    error.value = e?.response?.data?.descripcion || 'No se pudieron cargar los eventos de Google Calendar.';
  }
  load.value = false;
};

function moveMonth(step) {
  const date = new Date(year.value, month.value - 1 + step, 1);
  month.value = date.getMonth() + 1;
  year.value = date.getFullYear();
  index();
}

function goCurrentMonth() {
  const now = new Date();
  month.value = now.getMonth() + 1;
  year.value = now.getFullYear();
  index();
}

function onRangeChange(value) {
  range.value = value;
  index();
}

function onDateClick(date, hasTime) {
  const start = new Date(date);
  if (!hasTime) start.setHours(new Date().getHours() + 1, 0, 0, 0);
  selected.value = { title: '', description: '', location: '', starts_at: start, ends_at: '' };
  dialogCrud.value = true;
}

function onEventClick(event) {
  if (!event?.raw) return;
  selected.value = { ...event.raw };
  dialogCrud.value = true;
}

function askDeleteRecurring(row) {
  rowToDelete.value = row;
  scopeDialog.value = true;
}

async function deleteRecurring(scope) {
  scopeDialog.value = false;
  const row = rowToDelete.value;
  if (!row) return;
  load.value = true;
  try {
    const params = new URLSearchParams({
      scope,
      recurring_event_id: row.recurring_event_id,
      original_starts_at: row.original_starts_at || row.starts_at,
    });
    const result = await $gkaxios.delete(`${basePage}/${encodeURIComponent(row.id)}?${params}`);
    if (result.status != 0) error.value = result.descripcion;
  } catch (e) {
    error.value = e?.response?.data?.descripcion || 'No se pudo eliminar el evento.';
  }
  load.value = false;
  index();
}

function closeCrud() {
  dialogCrud.value = false;
  index();
}

watch(viewMode, (mode) => {
  if (mode === 'list') index();
});

onMounted(() => {
  accesoUtil.call({ ...dataContext, objAcceso: objAcceso.value }, "AgendaGoogle");
});

dataContext.index = index;

function buscarHandler(item) { buscarUtil.call(dataContext, item); }
function toUpdateHandler() { toUpdateUtil.call(dataContext); }
</script>

<style scoped>
.gcal-workspace {
  font-family: 'Google Sans', Roboto, 'Segoe UI', Arial, sans-serif;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Header estilo Google Workspace */
.gcal-header-logo {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: #ffffff;
  border: 1px solid #e8eaed;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 2px rgba(60, 64, 67, 0.08);
}

.gcal-header-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.gcal-header-title {
  font-size: 18px;
  font-weight: 700;
  color: #1f1f1f;
  letter-spacing: -0.2px;
}

.gcal-header-subtitle {
  font-size: 12px;
  color: #5f6368;
}

/* Píldora interactiva de cuenta/calendario activo */
.gcal-account-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

.gcal-account-pill__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.gcal-account-pill__text {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gcal-account-pill--connected {
  background: #e6f4ea;
  color: #137333;
  border-color: #ceead6;
}
.gcal-account-pill--connected .gcal-account-pill__dot {
  background: #1e8e3e;
}
.gcal-account-pill--connected:hover {
  background: #ceead6;
}

.gcal-account-pill--disconnected {
  background: #fef7e0;
  color: #b06000;
  border-color: #fde293;
}
.gcal-account-pill--disconnected .gcal-account-pill__dot {
  background: #f9ab00;
}
.gcal-account-pill--disconnected:hover {
  background: #fde293;
}

/* Acciones en el header */
.gcal-header-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.gcal-icon-btn {
  color: #5f6368;
}

/* Botón segmentado Material 3 */
.gcal-segmented {
  display: inline-flex;
  align-items: center;
  background: #f1f3f4;
  padding: 3px;
  border-radius: 999px;
  border: 1px solid #dadce0;
}

.gcal-segmented__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 30px;
  padding: 0 13px;
  border-radius: 999px;
  border: none;
  background: transparent;
  color: #5f6368;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.gcal-segmented__btn--active {
  background: #ffffff;
  color: #1a73e8;
  box-shadow: 0 1px 2px rgba(60, 64, 67, 0.18);
}

.gcal-divider {
  border-color: #e8eaed;
  opacity: 1;
  margin: 8px 0;
}

/* Contenedor colapsable del panel de cuenta */
.gcal-connection-wrapper {
  margin-bottom: 4px;
}

.gcal-panel-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 4px 8px;
}

.gcal-panel-bar__label {
  display: inline-flex;
  align-items: center;
  font-size: 12.5px;
  font-weight: 600;
  color: #5f6368;
}

/* Franja de error */
.gcal-error-strip {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-radius: 12px;
  background: #fce8e6;
  border: 1px solid #f6aea9;
  color: #a50e0e;
  font-size: 13px;
}

/* Superficie de vista Lista */
.gcal-list-surface {
  background: #ffffff;
  border: 1px solid #dadce0;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 1px 2px rgba(60, 64, 67, 0.06);
}

.gcal-list-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding-bottom: 14px;
  margin-bottom: 12px;
  border-bottom: 1px solid #e8eaed;
}

.gcal-today-btn {
  border-color: #dadce0 !important;
  color: #3c4043 !important;
  font-weight: 500;
}

.gcal-list-month-title {
  font-size: 18px;
  font-weight: 500;
  color: #1f1f1f;
  margin: 0 0 0 6px;
  text-transform: capitalize;
}

.gcal-count-pill {
  padding: 4px 12px;
  border-radius: 999px;
  background: #e8f0fe;
  color: #1a73e8;
  font-size: 12px;
  font-weight: 600;
}

/* Celdas de eventos en lista */
.gcal-event-cell {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.gcal-event-cell__dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #1a73e8;
  margin-top: 5px;
  flex-shrink: 0;
}

.gcal-event-cell__title {
  font-weight: 600;
  color: #1f1f1f;
  font-size: 13.5px;
}

.gcal-event-cell__location {
  display: flex;
  align-items: center;
  font-size: 12px;
  color: #5f6368;
  margin-top: 2px;
}

.gcal-date-cell {
  display: inline-flex;
  align-items: center;
  font-size: 13px;
  color: #3c4043;
}

.gcal-date-cell--muted {
  color: #5f6368;
}

/* Superficie de vista Calendario */
.gcal-calendar-surface {
  background: #ffffff;
  border: 1px solid #dadce0;
  border-radius: 16px;
  padding: 12px 16px 16px;
  box-shadow: 0 1px 2px rgba(60, 64, 67, 0.06);
}
</style>
