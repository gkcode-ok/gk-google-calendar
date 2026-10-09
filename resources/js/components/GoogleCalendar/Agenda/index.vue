<template>
    <ContenedorIndex comentario="Contenedor Base de Agenda Google" :style="'width:100%;'">
        <template #cabecera />

        <template #icon>
            <v-icon size="25" color="primary">mdi-google</v-icon>
        </template>
        <template #titulo>
            <div class="d-flex flex-column">
                <span style="font-weight: 900 !important;">Agenda Google</span>
                <span class="text-caption text-medium-emphasis">{{ calendarName }}</span>
            </div>
        </template>

        <template #botonCrear>
            <div class="d-flex align-center">
                <v-btn-toggle v-model="viewMode" mandatory density="comfortable" variant="outlined" divided class="mr-2">
                    <v-btn value="list" icon="mdi-format-list-bulleted" />
                    <v-btn value="calendar" icon="mdi-calendar-month" />
                </v-btn-toggle>
                <Crud v-if="objAcceso.creacion" :event="[]" :tipo="'Nuevo'" @refresh="toUpdateHandler" />
            </div>
        </template>

        <template #main />

        <template #Filtro>
            <Buscador @refreshBuscador="buscarHandler" :find="filters" :titulo="'Filtros de Búsqueda'" />
            <hr />
        </template>

        <template #Tabla>
            <v-alert v-if="error" type="warning" variant="tonal" class="mb-3">{{ error }}</v-alert>

            <template v-else-if="viewMode === 'list'">
                <div class="d-flex justify-space-between align-center mb-3">
                    <v-btn icon="mdi-chevron-left" variant="tonal" size="small" @click="moveMonth(-1)" />
                    <h3 class="text-h6 font-weight-bold mb-0 text-capitalize">{{ monthLabel }}</h3>
                    <v-btn icon="mdi-chevron-right" variant="tonal" size="small" @click="moveMonth(1)" />
                </div>

                <Tabla :fields="fields" :data="object['data']" ref="vuetable" :opciones="TablaOpciones"
                    :name="'tablaAgendaGoogle'">
                    <template #title="props">
                        <div class="font-weight-medium">
                            {{ props.rowData.title }}
                            <div v-if="props.rowData.location" class="text-caption text-grey-darken-1 mt-1">
                                <v-icon size="12">mdi-map-marker</v-icon> {{ props.rowData.location }}
                            </div>
                        </div>
                    </template>

                    <template #starts_at="props">
                        <div class="d-flex align-center">
                            <v-icon v-if="props.rowData.is_recurring" size="small" class="mr-1" title="Evento recurrente">mdi-repeat</v-icon>
                            <v-icon size="small" color="primary" class="mr-1">mdi-calendar-clock</v-icon>
                            {{ formatDate(props.rowData.starts_at, props.rowData.all_day) }}
                        </div>
                    </template>

                    <template #ends_at="props">
                        {{ formatDate(props.rowData.ends_at, props.rowData.all_day) }}
                    </template>

                    <template #accion="props">
                        <div class="d-flex justify-center flex-wrap gap-2">
                            <v-btn v-if="props.rowData.html_link" :href="props.rowData.html_link" target="_blank" rel="noopener"
                                icon="mdi-open-in-new" size="30" variant="tonal" />
                            <Crud v-if="objAcceso.modificacion" :event="props.rowData" :tipo="'Modificando'"
                                @refresh="toUpdateHandler" />
                            <v-tooltip v-if="objAcceso.eliminacion && props.rowData.recurring_event_id" text="Eliminar" location="top">
                                <template #activator="{ props: tooltipProps }">
                                    <v-btn v-bind="tooltipProps" icon size="30" variant="tonal" elevation="0"
                                        class="table-btn-action" @click="askDeleteRecurring(props.rowData)">
                                        <v-icon size="16">mdi-delete</v-icon>
                                    </v-btn>
                                </template>
                            </v-tooltip>
                            <BotonEliminar v-else-if="objAcceso.eliminacion" :id="props.rowData.id" :page="basePage" :tipo="'chico'"
                                @refresh="toUpdateHandler" />
                        </div>
                    </template>
                </Tabla>
            </template>

            <GoogleStyleCalendar v-else :events="calendarEvents" @range-change="onRangeChange" @date-click="onDateClick"
                @event-click="onEventClick" />
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

const object = ref({ data: [] });
const objAcceso = ref({});
const load = ref(false);
const error = ref('');
const calendarName = ref('');
const viewMode = ref('list');
const month = ref(new Date().getMonth() + 1);
const year = ref(new Date().getFullYear());
const range = ref(null);
const scopeDialog = ref(false);
const rowToDelete = ref(null);
const dialogCrud = ref(false);
const selected = ref({});

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
    theadStyle: "font-size:14px;",
    tbodyStyle: "font-size:12px; vertical-align:middle; ",
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

const index = async () => {
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
            calendarName.value = result.data?.calendar || '';
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
    index();
});

dataContext.index = index;

function buscarHandler(item) { buscarUtil.call(dataContext, item); }
function toUpdateHandler() { toUpdateUtil.call(dataContext); }
</script>
