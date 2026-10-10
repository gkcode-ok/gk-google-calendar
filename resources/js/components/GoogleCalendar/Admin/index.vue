<template>
    <ContenedorIndex comentario="Habilitar empresas para Google Calendar" :style="'width:100%;'">
        <template #cabecera />
        <template #icon>
            <v-icon size="25">mdi-calendar-sync</v-icon>
        </template>
        <template #titulo>
            <span style="font-weight: 900 !important;">Google Calendar - Empresas</span>
        </template>
        <template #botonCrear />


        <template #Filtro>
            <v-card variant="outlined" class="pa-3 mb-2">
                <div class="d-flex align-center mb-2">
                    <v-icon class="mr-2">mdi-key-variant</v-icon>
                    <strong>Token de registro de GK Servicios</strong>
                    <v-chip class="ml-3" size="small" :color="configured ? 'green' : 'orange'" variant="tonal">
                        {{ configured ? 'Configurado' : 'Pendiente' }}
                    </v-chip>
                </div>
                <div class="text-caption mb-2">
                    Ingresa el token administrativo de GK Servicios (INTEGRATIONS_ADMIN_TOKEN). Se genera el token de registro y se detectan automaticamente los IDs de google / calendar.
                    El token administrativo se usa una sola vez y no se guarda.
                </div>
                <div class="d-flex ga-2">
                    <v-text-field v-model="adminToken" type="password" density="compact" hide-details
                        label="Token administrativo" autocomplete="off" />
                    <v-btn color="primary" :disabled="adminToken.trim().length < 10" @click="generarToken">
                        {{ configured ? 'Regenerar' : 'Generar' }}
                    </v-btn>
                </div>
            </v-card>
            <Buscador @refreshBuscador="buscarHandler" :find="filters" :titulo="'Buscador'" />
            <hr />
        </template>
        <template #TotalPag>
            <TotalPag :data="object" @totalpage="totalPaginaHandler" />
        </template>
        <template #Tabla>
            <Tabla :fields="fields" :data="object['data']" ref="vuetable" :opciones="TablaOpciones"
                :name="'tablaGoogleCalendarTenant'" @ordenby="ordenbyHandler">
                <template #status="props">
                    <ChipEstado :data="props.rowData.status" :tipo="[
                        { id: 'enabled', color: 'green', nombre: 'Habilitada' },
                        { id: 'disabled', color: 'grey', nombre: 'Deshabilitada' },
                        { id: 'error', color: 'red', nombre: 'Error' },
                        { id: 'not_registered', color: 'orange', nombre: 'Sin registrar' }
                    ]" />
                    <div v-if="props.rowData.last_error" class="text-caption text-error">{{ props.rowData.last_error }}</div>
                </template>
                <template #accion="props">
                    <v-btn v-if="props.rowData.status !== 'enabled' && objAcceso.modificacion" size="small" color="primary"
                        variant="tonal" prepend-icon="mdi-check" :disabled="!configured"
                        @click="cambiar(props.rowData, 'habilitar')">
                        Habilitar
                    </v-btn>
                    <v-btn v-if="props.rowData.status === 'enabled' && objAcceso.modificacion" size="small" color="error"
                        variant="tonal" prepend-icon="mdi-close" @click="cambiar(props.rowData, 'deshabilitar')">
                        Deshabilitar
                    </v-btn>
                </template>
            </Tabla>
        </template>
        <template #Paginacion>
            <Paginacion :data="object" @click="paginationHandler" />
        </template>
    </ContenedorIndex>

    <Load :load="load" />
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">{{ snackbar.text }}</v-snackbar>
</template>

<script setup>
import { ref, reactive, onMounted, getCurrentInstance } from 'vue';

import {
    ordenby as ordenbyUtil,
    totalPagina as totalPaginaUtil,
    acceso as accesoUtil,
    buscar as buscarUtil,
    pagination as paginationUtil,
    getData
} from "@GKModule/ComponentFuc";

import ChipEstado from '@Components/Otros/ChipEstado/index.vue';

const object = ref({ data: [] });
const objAcceso = ref({ lectura: true, creacion: true, modificacion: true, eliminar: true });
const configured = ref(true);
const adminToken = ref('');
const catalog = reactive({ provider_id: '', service_id: '', saved: false });
const load = ref(false);
const snackbar = reactive({ show: false, text: '', color: 'success' });

const { appContext } = getCurrentInstance();
const funciones = appContext.config.globalProperties.$funciones;
const $gkaxios = appContext.config.globalProperties.$gkaxios;
const dataContext = reactive(getData());

const TablaOpciones = reactive({
    divtablaClass: "fixTableHead divscrollJuan col-sm-12",
    theadStyle: "font-size:14px;",
    tbodyStyle: "font-size:12px; vertical-align:middle; ",
    tablaClass: "table table-striped tablaJuanClass"
});

const page = ref('/GoogleCalendarTenant/data?');

const fields = reactive([
    { nombre: 'id', titulo: '#', tdstyle: "text-align: center", thstyle: "width:8%" },
    { nombre: 'name', titulo: 'Empresa', order: 'ASC', title: true },
    { nombre: 'slug', titulo: 'Slug' },
    { nombre: 'status', titulo: 'Google Calendar' },
    { nombre: 'registered_at', titulo: 'Registrada' },
    { nombre: 'accion', titulo: '' }
]);

const filters = reactive({
    params: { name: '', status: '' },
    data_select: {
        status_select: [
            { id: '', value: 'Estado - Todos -', selected: 'selected' },
            { id: 'enabled', value: 'Habilitada' },
            { id: 'disabled', value: 'Deshabilitada' },
            { id: 'error', value: 'Error' },
            { id: 'not_registered', value: 'Sin registrar' }
        ],
    },
    filters: [
        { span_text: "Empresa", input_type: "text", input_name: "name" },
        { span_text: "Estado", input_type: 'select', input_name: 'value', data_select: 'status_select', select_model: "status" },
    ]
});

const index = async () => {
    try {
        load.value = true;
        const buscadorParam = dataContext.buscador[1] ?? '';
        const currentUrl = (dataContext.page && dataContext.page !== "") ? dataContext.page : page.value;
        const url = `${currentUrl}${dataContext.totalpag}&${buscadorParam}${dataContext.orderby}`;
        const result = await $gkaxios.get(url);
        if (result.status == 0) {
            configured.value = result.configured !== false;
            if (result.catalog) Object.assign(catalog, result.catalog);
            object.value = {
                ...result.data,
                data: result.data && Array.isArray(result.data.data) ? result.data.data : []
            };
        }
    } catch (error) {
        console.error(error);
    }
    load.value = false;
};

const generarToken = async () => {
    load.value = true;
    try {
        const result = await $gkaxios.post('/GoogleCalendarTenant/generar-token', { admin_token: adminToken.value, name: 'Nexora' });
        snackbar.color = result.status == 0 ? 'success' : 'error';
        snackbar.text = result.descripcion || 'No se pudo generar el token.';
        if (result.status == 0) {
            adminToken.value = '';
            configured.value = true;
            catalog.saved = true;
        }
    } catch (error) {
        snackbar.color = 'error';
        snackbar.text = 'No se pudo generar el token.';
    }
    snackbar.show = true;
    load.value = false;
};
const cambiar = async (row, accion) => {
    load.value = true;
    try {
        const method = accion === 'habilitar' ? 'post' : 'put';
        const result = await $gkaxios[method](`/GoogleCalendarTenant/${row.id}/${accion}`, {});
        snackbar.color = result.status == 0 ? 'success' : 'error';
        snackbar.text = result.descripcion || (result.status == 0 ? 'Operacion realizada.' : 'No se pudo completar la operacion.');
    } catch (error) {
        snackbar.color = 'error';
        snackbar.text = 'No se pudo completar la operacion.';
    }
    snackbar.show = true;
    load.value = false;
    await index();
};

onMounted(async () => {
    load.value = true;
    dataContext.totalpag = 'totalpage=' + funciones.totalPag();
    dataContext.orderby = '&orderby=name ASC';
    accesoUtil.call({ ...dataContext, objAcceso: objAcceso.value }, "GoogleCalendarTenant");
    if (objAcceso.value.lectura) {
        await index();
    }
    load.value = false;
});

dataContext.index = index;

function buscarHandler(item) { buscarUtil.call(dataContext, item); }
function ordenbyHandler(item) { ordenbyUtil.call(dataContext, item); }
function totalPaginaHandler(item) { totalPaginaUtil.call(dataContext, item); }
function paginationHandler(item) { paginationUtil.call(dataContext, item); }
</script>
