<template>
  <ContenedorCrud
    :modal="'Crud'"
    comentario="Contenedor Base del Evento de Google Calendar"
  >

    <!-- =====================================================
         TÍTULO
    ====================================================== -->
    <template #titulo>
      <div class="crud-title">

        <div class="crud-title-icon">
          <v-icon size="20">
            {{ props.tipo === 'Nuevo'
              ? 'mdi-calendar-plus'
              : 'mdi-calendar-edit'
            }}
          </v-icon>
        </div>

        <div class="d-flex flex-column">

          <span class="crud-title-main">
            {{ tipo }} evento
          </span>

          <span class="crud-title-subtitle">
            Google Calendar
          </span>

        </div>

      </div>
    </template>


    <!-- =====================================================
         CERRAR
    ====================================================== -->
    <template #btnClose>

      <v-tooltip text="Cerrar">

        <template #activator="{ props: tooltipProps }">

          <v-btn
            v-bind="tooltipProps"
            icon="mdi-close"
            variant="text"
            size="small"
            class="close-btn"
            @click="closeModal"
          />

        </template>

      </v-tooltip>

    </template>


    <!-- =====================================================
         FORMULARIO
    ====================================================== -->
    <template #form>

      <div class="event-form">

        <!-- =================================================
             ENCABEZADO
        ================================================== -->
        <div class="event-header">

          <div class="event-header-icon">
            <v-icon size="24">
              mdi-calendar-clock-outline
            </v-icon>
          </div>

          <div class="event-header-content">

            <div class="event-header-title">
              {{ obj.title || 'Nuevo evento' }}
            </div>

            <div class="event-header-subtitle">
              {{ eventSummary }}
            </div>

          </div>

          <v-chip
            v-if="obj.all_day"
            size="small"
            color="primary"
            variant="tonal"
            class="event-status-chip"
          >
            <v-icon start size="14">
              mdi-calendar-today
            </v-icon>

            Todo el día
          </v-chip>

        </div>


        <!-- =================================================
             INFORMACIÓN PRINCIPAL
        ================================================== -->
        <div class="form-section">

          <div class="section-title">

            <div class="section-icon">
              <v-icon size="17">
                mdi-information-outline
              </v-icon>
            </div>

            <div>
              <div class="section-title-text">
                Información del evento
              </div>

              <div class="section-description">
                Definí los datos principales del evento.
              </div>
            </div>

          </div>


          <div class="section-content">

            <!-- TÍTULO -->
            <v-text-field
              v-model="obj.title"
              label="Título del evento"
              placeholder="Ej. Reunión con cliente"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              required
              prepend-inner-icon="mdi-format-title"
              class="form-control"
            />


            <!-- FECHAS -->
            <v-row class="mt-1">

              <v-col
                cols="12"
                md="6"
              >

                <v-text-field
                  v-model="obj.starts_at"
                  label="Inicio"
                  type="datetime-local"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  required
                  prepend-inner-icon="mdi-clock-start-outline"
                  class="form-control"
                />

              </v-col>


              <v-col
                cols="12"
                md="6"
              >

                <v-text-field
                  v-model="obj.ends_at"
                  label="Fin"
                  type="datetime-local"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="mdi-clock-end-outline"
                  class="form-control"
                />

              </v-col>

            </v-row>


            <!-- DÍA COMPLETO -->
            <div
              v-if="obj.all_day"
              class="all-day-info"
            >

              <v-icon
                size="18"
                color="primary"
              >
                mdi-calendar-today
              </v-icon>

              <div>

                <div class="all-day-title">
                  Evento de día completo
                </div>

                <div class="all-day-text">
                  El evento proviene de Google Calendar como evento de día completo.
                </div>

              </div>

            </div>

          </div>

        </div>


        <!-- =================================================
             RECURRENCIA
        ================================================== -->
        <div class="form-section">

          <div class="section-title">

            <div class="section-icon">
              <v-icon size="17">
                mdi-repeat
              </v-icon>
            </div>

            <div>

              <div class="section-title-text">
                Recurrencia
              </div>

              <div class="section-description">
                Configurá si el evento se repite y cuándo finaliza.
              </div>

            </div>

          </div>


          <div class="section-content">

            <v-alert
              v-if="obj.recurrence?.readonly"
              type="info"
              variant="tonal"
              density="comfortable"
            >
              Este evento tiene una regla de repetición personalizada creada en Google Calendar.
              Se conservará sin cambios.
            </v-alert>

            <v-progress-linear
              v-else-if="loadingRecurrence"
              indeterminate
              color="primary"
            />

            <RecurrenceForm
              v-else
              v-model="obj.recurrence"
              :starts-at="obj.starts_at"
            />

          </div>

        </div>


        <!-- =================================================
             NOTIFICACIONES
        ================================================== -->
        <div class="form-section">

          <div class="section-title">

            <div class="section-icon">
              <v-icon size="17">
                mdi-bell-outline
              </v-icon>
            </div>

            <div>

              <div class="section-title-text">
                Notificaciones
              </div>

              <div class="section-description">
                Recibí un aviso antes del inicio del evento.
              </div>

            </div>

          </div>


          <div class="section-content">

            <ReminderForm v-model="obj.reminders" />

          </div>

        </div>


        <!-- =================================================
             UBICACIÓN
        ================================================== -->
        <div class="form-section">

          <div class="section-title">

            <div class="section-icon">
              <v-icon size="17">
                mdi-map-marker-outline
              </v-icon>
            </div>

            <div>

              <div class="section-title-text">
                Ubicación
              </div>

              <div class="section-description">
                Indicá dónde se realizará el evento.
              </div>

            </div>

          </div>


          <div class="section-content">

            <v-text-field
              v-model="obj.location"
              label="Ubicación"
              placeholder="Ej. Oficina central, Santa Fe"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              prepend-inner-icon="mdi-map-marker-outline"
              class="form-control"
            />

          </div>

        </div>


        <!-- =================================================
             DESCRIPCIÓN
        ================================================== -->
        <div class="form-section">

          <div class="section-title">

            <div class="section-icon">
              <v-icon size="17">
                mdi-text-box-outline
              </v-icon>
            </div>

            <div>

              <div class="section-title-text">
                Descripción
              </div>

              <div class="section-description">
                Agregá información adicional para el evento.
              </div>

            </div>

          </div>


          <div class="section-content">

            <v-textarea
              v-model="obj.description"
              label="Descripción"
              placeholder="Detalles, notas o información adicional..."
              variant="outlined"
              density="comfortable"
              rows="5"
              auto-grow
              hide-details="auto"
              class="form-control"
            />

          </div>

        </div>


        <!-- =================================================
             GOOGLE CALENDAR
        ================================================== -->
        <div
          v-if="obj.html_link"
          class="google-card"
        >

          <div class="google-card-icon">

            <v-icon size="22">
              mdi-google
            </v-icon>

          </div>


          <div class="google-card-content">

            <div class="google-card-title">
              Evento sincronizado con Google Calendar
            </div>

            <div class="google-card-description">
              Podés abrir este evento directamente en Google Calendar.
            </div>

          </div>


          <v-btn
            :href="obj.html_link"
            target="_blank"
            rel="noopener"
            variant="tonal"
            color="primary"
            size="small"
            append-icon="mdi-open-in-new"
            class="google-open-btn"
          >
            Abrir
          </v-btn>

        </div>


        <!-- =================================================
             INFORMACIÓN
        ================================================== -->
        <v-alert
          v-if="!obj.html_link && tipo !== 'Nuevo'"
          type="info"
          variant="tonal"
          density="comfortable"
          class="sync-alert"
        >

          <template #prepend>

            <v-icon>
              mdi-information-outline
            </v-icon>

          </template>

          Este evento todavía no tiene un enlace directo de Google Calendar.

        </v-alert>


      </div>

    </template>


    <!-- =====================================================
         BOTONES
    ====================================================== -->
    <template #btnCrud>

      <div class="crud-actions">

        <v-btn
          v-if="tipo !== 'Nuevo' && obj.id"
          variant="tonal"
          color="error"
          rounded="pill"
          class="text-none mr-auto"
          prepend-icon="mdi-delete-outline"
          :loading="saving"
          @click="askDelete"
        >
          Eliminar
        </v-btn>

        <v-btn variant="tonal" rounded="pill" class="text-none" @click="closeModal">
          Salir
        </v-btn>

        <v-btn
          color="primary"
          variant="flat"
          rounded="pill"
          class="text-none font-weight-bold"
          :loading="saving"
          @click="askSave"
        >
          {{ tipo === 'Nuevo' ? 'Guardar' : 'Modificar' }}
        </v-btn>

      </div>

      <Load :load="saving" />
      <Alerta :msj="alerta" />

      <RecurrenceScopeDialog
        v-model="scopeDialog.open"
        :title="scopeDialog.action === 'delete' ? 'Eliminar evento recurrente' : 'Modificar evento recurrente'"
        :danger="scopeDialog.action === 'delete'"
        @confirm="onScopeConfirm"
      />

    </template>

  </ContenedorCrud>
</template>


<script setup>

import {
  ref,
  reactive,
  watch,
  computed,
  getCurrentInstance
} from 'vue';

import RecurrenceForm from './RecurrenceForm.vue';
import ReminderForm from './ReminderForm.vue';
import RecurrenceScopeDialog from './RecurrenceScopeDialog.vue';


const props = defineProps({

  tipo: {
    type: String,
    default: ''
  },

  event: {
    type: Object,
    default: () => ({})
  }

});


const emit = defineEmits([
  'refresh',
  'close'
]);


const obj = ref({});

const { proxy } = getCurrentInstance();

const saving = ref(false);
const loadingRecurrence = ref(false);
const alerta = ref({ mensaje: '', visible: false, tipo: 0 });
const scopeDialog = reactive({ open: false, action: 'save' });

const page = ref(
  '/home/GoogleCalendar/agenda'
);


/* =========================================================
   RESUMEN DEL EVENTO
========================================================= */

const eventSummary = computed(() => {

  if (!obj.value.starts_at) {
    return 'Configurá la fecha y horario del evento';
  }

  const date = new Date(obj.value.starts_at);

  if (Number.isNaN(date.getTime())) {
    return 'Configurá la fecha y horario del evento';
  }

  return date.toLocaleDateString(
    'es-AR',
    {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    }
  );

});


/* =========================================================
   CARGAR EVENTO
========================================================= */

watch(
  () => props.event,

  (event) => {

    if (
      event &&
      Object.keys(event).length > 0
    ) {

      obj.value = {
        ...event,

        starts_at: toLocalInput(
          event.starts_at
        ),

        ends_at: toLocalInput(
          event.ends_at
        ),

        previous_starts_at: toLocalInput(
          event.starts_at
        ),

        recurrence: null
      };

      loadRecurrence(event);

    } else {

      const now = new Date();

      now.setHours(
        now.getHours() + 1,
        0,
        0,
        0
      );

      obj.value = {

        title: '',

        description: '',

        location: '',

        starts_at: toLocalInput(now),

        ends_at: '',

        recurrence: null,

        reminders: null

      };

    }

  },

  {
    immediate: true
  }

);


/* =========================================================
   DATE → DATETIME LOCAL
========================================================= */

function toLocalInput(value) {

  if (!value) {
    return '';
  }


  if (value instanceof Date) {

    const pad = (n) =>
      String(n).padStart(2, '0');


    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(value.getHours())}:${pad(value.getMinutes())}`;

  }


  /*
   * Google Calendar puede enviar
   * YYYY-MM-DD para eventos all-day.
   */

  if (value.length === 10) {

    return `${value}T09:00`;

  }


  return value.slice(0, 16);

}


/* =========================================================
   RECURRENCIA
========================================================= */

async function loadRecurrence(event) {

  if (!event.id || !event.is_recurring) {
    return;
  }

  loadingRecurrence.value = true;

  try {

    const query = event.recurring_event_id
      ? `?recurring_event_id=${encodeURIComponent(event.recurring_event_id)}`
      : '';

    const result = await proxy.$gkaxios.get(
      `${page.value}/${encodeURIComponent(event.id)}${query}`
    );

    if (result?.status === 0 && obj.value.id === event.id) {
      obj.value.recurrence = result.data?.recurrence ?? null;
    }

  } finally {

    loadingRecurrence.value = false;

  }

}


/* =========================================================
   GUARDAR / ELIMINAR
========================================================= */

const isRecurringInstance = computed(
  () => props.tipo !== 'Nuevo' && !!obj.value.recurring_event_id
);

function askSave() {

  if (isRecurringInstance.value) {
    scopeDialog.action = 'save';
    scopeDialog.open = true;
    return;
  }

  save(null);

}

function askDelete() {

  if (isRecurringInstance.value) {
    scopeDialog.action = 'delete';
    scopeDialog.open = true;
    return;
  }

  if (window.confirm('¿Está seguro que desea eliminar este evento?')) {
    remove(null);
  }

}

function onScopeConfirm(scope) {

  scopeDialog.open = false;

  if (scopeDialog.action === 'delete') {
    remove(scope);
  } else {
    save(scope);
  }

}

async function save(scope) {

  saving.value = true;

  try {

    const payload = { ...obj.value, scope };
    const nuevo = props.tipo === 'Nuevo';

    const result = nuevo
      ? await proxy.$gkaxios.post(page.value, payload)
      : await proxy.$gkaxios.put(`${page.value}/${encodeURIComponent(obj.value.id)}`, payload);

    alerta.value = { ...result.alerta, tipo: 2 };

    if (result.status === 0) {
      refresh();
    }

  } catch (error) {

    console.error('Error al guardar el evento:', error);

    alerta.value = { mensaje: 'Ha ocurrido un error inesperado.', visible: true, tipo: 1 };

  } finally {

    saving.value = false;

  }

}

async function remove(scope) {

  saving.value = true;

  try {

    const params = new URLSearchParams();

    if (scope) {
      params.set('scope', scope);
      params.set('recurring_event_id', obj.value.recurring_event_id);
      params.set('original_starts_at', obj.value.original_starts_at || obj.value.starts_at);
    }

    const query = params.toString() ? `?${params.toString()}` : '';

    const result = await proxy.$gkaxios.delete(
      `${page.value}/${encodeURIComponent(obj.value.id)}${query}`
    );

    alerta.value = { ...result.alerta, tipo: 2 };

    if (result.status === 0) {
      refresh();
    }

  } catch (error) {

    console.error('Error al eliminar el evento:', error);

    alerta.value = { mensaje: 'Ha ocurrido un error inesperado al eliminar.', visible: true, tipo: 1 };

  } finally {

    saving.value = false;

  }

}


/* =========================================================
   REFRESH
========================================================= */

function refresh() {

  setTimeout(() => {

    closeModal();

    emit(
      'refresh',
      'agenda-google'
    );

  }, 200);

}

/* =========================================================
   CERRAR
========================================================= */

function closeModal() {

  emit(
    'close',
    false
  );

}

</script>


<style scoped>

/* =========================================================
   TÍTULO DEL CRUD
========================================================= */

.crud-title {
  display: flex;
  align-items: center;
  gap: 10px;
}


.crud-title-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 36px;
  height: 36px;

  border-radius: 10px;

  color: rgb(var(--v-theme-primary));

  background: rgba(
    var(--v-theme-primary),
    0.10
  );
}


.crud-title-main {
  font-size: 15px;
  font-weight: 700;
  line-height: 20px;
}


.crud-title-subtitle {
  font-size: 11px;
  opacity: 0.55;
  line-height: 15px;
}


.close-btn {
  border-radius: 50%;
}


/* =========================================================
   FORMULARIO
========================================================= */

.event-form {
  width: 100%;
  max-width: 980px;
  margin: 0 auto;

  padding: 8px 4px 30px;
}


/* =========================================================
   HEADER
========================================================= */

.event-header {
  display: flex;
  align-items: center;
  gap: 14px;

  padding: 18px 20px;
  margin-bottom: 18px;

  border: 1px solid rgba(
    var(--v-border-color),
    0.35
  );

  border-radius: 14px;

  background: rgba(
    var(--v-theme-surface),
    0.75
  );
}


.event-header-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 46px;
  height: 46px;

  flex-shrink: 0;

  border-radius: 12px;

  color: rgb(var(--v-theme-primary));

  background: rgba(
    var(--v-theme-primary),
    0.10
  );
}


.event-header-content {
  min-width: 0;
  flex: 1;
}


.event-header-title {
  overflow: hidden;

  font-size: 17px;
  font-weight: 700;

  text-overflow: ellipsis;
  white-space: nowrap;
}


.event-header-subtitle {
  margin-top: 2px;

  font-size: 12px;

  text-transform: capitalize;

  opacity: 0.60;
}


.event-status-chip {
  flex-shrink: 0;
}


/* =========================================================
   SECCIONES
========================================================= */

.form-section {
  margin-bottom: 16px;

  border: 1px solid rgba(
    var(--v-border-color),
    0.35
  );

  border-radius: 14px;

  overflow: hidden;

  background: rgb(
    var(--v-theme-surface)
  );
}


.section-title {
  display: flex;
  align-items: center;
  gap: 11px;

  padding: 15px 18px;

  border-bottom: 1px solid rgba(
    var(--v-border-color),
    0.25
  );

  background: rgba(
    var(--v-theme-on-surface),
    0.018
  );
}


.section-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 32px;
  height: 32px;

  flex-shrink: 0;

  border-radius: 8px;

  color: rgb(var(--v-theme-primary));

  background: rgba(
    var(--v-theme-primary),
    0.09
  );
}


.section-title-text {
  font-size: 13px;
  font-weight: 700;
}


.section-description {
  margin-top: 1px;

  font-size: 11px;

  opacity: 0.55;
}


.section-content {
  padding: 18px;
}


/* =========================================================
   CAMPOS
========================================================= */

.form-control :deep(.v-field) {
  border-radius: 9px;
}


.form-control :deep(.v-field__input) {
  font-size: 13px;
}


.form-control :deep(.v-label) {
  font-size: 13px;
}


/* =========================================================
   DÍA COMPLETO
========================================================= */

.all-day-info {
  display: flex;
  align-items: flex-start;
  gap: 10px;

  margin-top: 8px;
  padding: 12px 14px;

  border-radius: 9px;

  background: rgba(
    var(--v-theme-primary),
    0.07
  );
}


.all-day-title {
  font-size: 12px;
  font-weight: 700;
}


.all-day-text {
  margin-top: 2px;

  font-size: 11px;

  opacity: 0.65;
}


/* =========================================================
   GOOGLE CARD
========================================================= */

.google-card {
  display: flex;
  align-items: center;
  gap: 13px;

  margin-top: 4px;
  margin-bottom: 16px;

  padding: 14px 16px;

  border: 1px solid rgba(
    var(--v-theme-primary),
    0.20
  );

  border-radius: 12px;

  background: rgba(
    var(--v-theme-primary),
    0.045
  );
}


.google-card-icon {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 38px;
  height: 38px;

  flex-shrink: 0;

  border-radius: 10px;

  color: rgb(var(--v-theme-primary));

  background: rgba(
    var(--v-theme-primary),
    0.10
  );
}


.google-card-content {
  flex: 1;
  min-width: 0;
}


.google-card-title {
  font-size: 12px;
  font-weight: 700;
}


.google-card-description {
  margin-top: 2px;

  font-size: 11px;

  opacity: 0.60;
}


.google-open-btn {
  flex-shrink: 0;
  text-transform: none;
  border-radius: 8px;
}


/* =========================================================
   ALERTA
========================================================= */

.sync-alert {
  margin-top: 4px;
  border-radius: 10px;
  font-size: 12px;
}


/* =========================================================
   ACCIONES
========================================================= */

.crud-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  width: 100%;
  padding: 0 16px 16px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 600px) {

  .event-form {
    padding: 4px 0 20px;
  }


  .event-header {
    padding: 14px;
  }


  .event-header-icon {
    width: 40px;
    height: 40px;
  }


  .event-header-title {
    font-size: 15px;
  }


  .event-status-chip {
    display: none;
  }


  .section-content {
    padding: 14px;
  }


  .section-title {
    padding: 13px 14px;
  }


  .google-card {
    align-items: flex-start;
    flex-wrap: wrap;
  }


  .google-card-content {
    width: calc(100% - 55px);
  }


  .google-open-btn {
    margin-left: 51px;
  }

}

</style>
```