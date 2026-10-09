<template>
  <div class="month-calendar">

    <!-- =========================
         HEADER DEL CALENDARIO
    ========================== -->
    <div class="calendar-toolbar">

      <div class="calendar-toolbar-left">
        <v-btn
          variant="outlined"
          density="comfortable"
          class="today-btn"
          @click="goToday"
        >
          Hoy
        </v-btn>

        <div class="calendar-navigation">
          <v-btn
            icon="mdi-chevron-left"
            variant="text"
            density="comfortable"
            class="nav-btn"
            aria-label="Mes anterior"
            @click="move(-1)"
          />

          <v-btn
            icon="mdi-chevron-right"
            variant="text"
            density="comfortable"
            class="nav-btn"
            aria-label="Mes siguiente"
            @click="move(1)"
          />
        </div>
      </div>

      <div class="calendar-title">
        <span class="calendar-month">
          {{ monthName }}
        </span>

        <span class="calendar-year">
          {{ currentYear }}
        </span>
      </div>

      <div class="calendar-toolbar-right">
        <v-chip
          v-if="events.length"
          size="small"
          variant="tonal"
          color="primary"
          class="event-counter"
        >
          <v-icon start size="14">
            mdi-calendar-check-outline
          </v-icon>

          {{ events.length }}
          {{ events.length === 1 ? 'evento' : 'eventos' }}
        </v-chip>
      </div>
    </div>

    <!-- =========================
         CALENDARIO
    ========================== -->
    <div
      class="calendar-wrapper"
      :style="{ height }"
    >
      <VCalendar
        ref="calendar"
        v-model="selectedDate"
        :events="events"
        view-mode="month"
        @update:model-value="onDateSelected"
      >

        <!-- =========================
             EVENTO
        ========================== -->
        <template #event="{ event }">

          <v-tooltip
            location="top"
            :open-delay="250"
            :close-delay="100"
            max-width="340"
          >
            <template #activator="{ props: tooltipProps }">

              <div
                v-bind="tooltipProps"
                class="calendar-event"
                :style="eventStyle(event)"
                @click.stop="handleEventClick(event)"
              >

                <div class="event-accent"></div>

                <div class="event-content">

                  <div class="event-main">

                    <span
                      v-if="!event.allDay"
                      class="event-time"
                    >
                      {{ formatEventTime(event.start) }}
                    </span>

                    <span class="event-title">
                      {{ event.title || 'Sin título' }}
                    </span>

                  </div>

                  <div
                    v-if="event.raw?.location"
                    class="event-location"
                  >
                    <v-icon size="11">
                      mdi-map-marker-outline
                    </v-icon>

                    <span>
                      {{ event.raw.location }}
                    </span>
                  </div>

                </div>

              </div>

            </template>

            <!-- =========================
                 TOOLTIP
            ========================== -->
            <div class="event-tooltip">

              <div class="tooltip-header">

                <div
                  class="tooltip-color"
                  :style="{ backgroundColor: eventColor(event) }"
                />

                <div class="tooltip-title">
                  {{ event.title || 'Sin título' }}
                </div>

              </div>

              <div class="tooltip-info">

                <!-- Horario -->
                <div class="tooltip-row">

                  <v-icon size="17">
                    {{ event.allDay
                      ? 'mdi-calendar-outline'
                      : 'mdi-clock-outline'
                    }}
                  </v-icon>

                  <span>
                    {{ formatEventDate(event) }}
                  </span>

                </div>

                <!-- Ubicación -->
                <div
                  v-if="event.raw?.location"
                  class="tooltip-row"
                >
                  <v-icon size="17">
                    mdi-map-marker-outline
                  </v-icon>

                  <span>
                    {{ event.raw.location }}
                  </span>
                </div>

                <!-- Descripción -->
                <div
                  v-if="event.raw?.description"
                  class="tooltip-description"
                >
                  {{ event.raw.description }}
                </div>

              </div>

              <div
                v-if="event.raw?.html_link"
                class="tooltip-footer"
              >
                <span>
                  Abrir en Google Calendar
                </span>

                <v-icon size="14">
                  mdi-open-in-new
                </v-icon>
              </div>

            </div>
          </v-tooltip>

        </template>

      </VCalendar>
    </div>

  </div>
</template>


<script setup>
import { ref, computed } from 'vue';
import { VCalendar } from 'vuetify/labs/VCalendar';


/**
 * Calendario mensual reutilizable.
 *
 * events:
 * [
 *   {
 *     id,
 *     title,
 *     start,
 *     end,
 *     color,
 *     allDay,
 *     raw
 *   }
 * ]
 *
 * Emite:
 * - date-click
 * - event-click
 * - month-change
 */

const props = defineProps({
  events: {
    type: Array,
    default: () => []
  },

  height: {
    type: String,
    default: '600px'
  },

  initialMonth: {
    type: Number,
    default: null
  },

  initialYear: {
    type: Number,
    default: null
  }
});


const emit = defineEmits([
  'date-click',
  'event-click',
  'month-change'
]);


const MONTHS = [
  'Enero',
  'Febrero',
  'Marzo',
  'Abril',
  'Mayo',
  'Junio',
  'Julio',
  'Agosto',
  'Septiembre',
  'Octubre',
  'Noviembre',
  'Diciembre'
];


const today = new Date();

const startDate = new Date(
  props.initialYear ?? today.getFullYear(),
  props.initialMonth ?? today.getMonth(),
  1
);


const calendar = ref(null);

const currentMonth = ref(
  startDate.getMonth()
);

const currentYear = ref(
  startDate.getFullYear()
);

const selectedDate = ref([
  startDate
]);


const monthName = computed(() => {
  return MONTHS[currentMonth.value];
});


/*
 * Evita que un cambio programático
 * de fecha dispare date-click.
 */
let updatingView = false;


/* =========================================================
   NAVEGACIÓN
========================================================= */

function move(step) {

  updatingView = true;

  if (step < 0) {
    calendar.value?.prev();
  } else {
    calendar.value?.next();
  }

  const date = new Date(
    currentYear.value,
    currentMonth.value + step,
    1
  );

  currentMonth.value = date.getMonth();

  currentYear.value = date.getFullYear();

  emit('month-change', {
    month: currentMonth.value,
    year: currentYear.value
  });

  setTimeout(() => {
    updatingView = false;
  }, 100);
}


/* =========================================================
   IR A HOY
========================================================= */

function goToday() {

  updatingView = true;

  const now = new Date();

  currentMonth.value = now.getMonth();

  currentYear.value = now.getFullYear();

  selectedDate.value = [now];

  /*
   * Si la versión de Vuetify expone today()
   * lo utilizamos.
   */
  if (calendar.value?.today) {
    calendar.value.today();
  }

  emit('month-change', {
    month: currentMonth.value,
    year: currentYear.value
  });

  setTimeout(() => {
    updatingView = false;
  }, 100);
}


/* =========================================================
   RESET
========================================================= */

function reset() {

  updatingView = true;

  const now = new Date();

  currentMonth.value = now.getMonth();

  currentYear.value = now.getFullYear();

  selectedDate.value = [now];

  if (calendar.value?.today) {
    calendar.value.today();
  }

  emit('month-change', {
    month: currentMonth.value,
    year: currentYear.value
  });

  setTimeout(() => {
    updatingView = false;
  }, 100);
}


/* =========================================================
   CLICK SOBRE DÍA
========================================================= */

function onDateSelected(value) {

  if (updatingView || !value) {
    return;
  }

  const raw = Array.isArray(value)
    ? value[0]
    : value;

  if (raw) {
    emit(
      'date-click',
      new Date(raw)
    );
  }
}


/* =========================================================
   CLICK SOBRE EVENTO
========================================================= */

function handleEventClick(event) {

  emit(
    'event-click',
    event
  );
}


/* =========================================================
   COLOR DEL EVENTO
========================================================= */

function eventColor(event) {

  return event?.color || '#1a73e8';
}


/* =========================================================
   ESTILO DEL EVENTO
========================================================= */

function eventStyle(event) {

  const color = eventColor(event);

  return {
    '--event-color': color,
    '--event-bg': hexToRgba(color, 0.12),
    '--event-hover': hexToRgba(color, 0.20)
  };
}


/* =========================================================
   HORA DEL EVENTO
========================================================= */

function formatEventTime(value) {

  if (!value) {
    return '';
  }

  const date = new Date(value);

  return date.toLocaleTimeString(
    'es-AR',
    {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }
  );
}


/* =========================================================
   FECHA PARA TOOLTIP
========================================================= */

function formatEventDate(event) {

  if (!event?.start) {
    return '';
  }

  const start = new Date(event.start);

  if (event.allDay) {

    return start.toLocaleDateString(
      'es-AR',
      {
        weekday: 'long',
        day: '2-digit',
        month: 'long'
      }
    );
  }

  const end = event.end
    ? new Date(event.end)
    : null;

  const date = start.toLocaleDateString(
    'es-AR',
    {
      weekday: 'long',
      day: '2-digit',
      month: 'long'
    }
  );

  const startTime = start.toLocaleTimeString(
    'es-AR',
    {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }
  );

  if (!end) {
    return `${date} · ${startTime}`;
  }

  const endTime = end.toLocaleTimeString(
    'es-AR',
    {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
    }
  );

  return `${date} · ${startTime} – ${endTime}`;
}


/* =========================================================
   HEX → RGBA
========================================================= */

function hexToRgba(hex, alpha = 1) {

  if (!hex) {
    return `rgba(26, 115, 232, ${alpha})`;
  }

  const clean = hex.replace('#', '');

  if (clean.length !== 6) {
    return `rgba(26, 115, 232, ${alpha})`;
  }

  const r = parseInt(
    clean.substring(0, 2),
    16
  );

  const g = parseInt(
    clean.substring(2, 4),
    16
  );

  const b = parseInt(
    clean.substring(4, 6),
    16
  );

  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}


defineExpose({
  reset
});
</script>


<style scoped>

/* =========================================================
   CONTENEDOR
========================================================= */

.month-calendar {
  width: 100%;
  min-width: 0;
  color: rgb(var(--v-theme-on-surface));
}


/* =========================================================
   TOOLBAR
========================================================= */

.calendar-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 54px;
  margin-bottom: 12px;
}


.calendar-toolbar-left {
  display: flex;
  align-items: center;
  gap: 8px;
}


.calendar-toolbar-right {
  display: flex;
  align-items: center;
}


.calendar-navigation {
  display: flex;
  align-items: center;
}


.today-btn {
  min-width: 64px;
  height: 36px;
  border-radius: 8px;
  text-transform: none;
  font-size: 13px;
  font-weight: 600;
}


.nav-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
}


.calendar-title {
  display: flex;
  align-items: baseline;
  gap: 7px;
}


.calendar-month {
  font-size: 20px;
  font-weight: 600;
  letter-spacing: -0.25px;
  text-transform: capitalize;
}


.calendar-year {
  font-size: 18px;
  font-weight: 400;
  opacity: 0.65;
}


.event-counter {
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}


/* =========================================================
   CALENDARIO
========================================================= */

.calendar-wrapper {
  width: 100%;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), 0.35);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}


/*
 * Ajustes generales del calendario de Vuetify.
 */

.calendar-wrapper :deep(.v-calendar-month__week) {
  min-height: 105px;
}


.calendar-wrapper :deep(.v-calendar-month__day) {
  min-height: 105px;
  padding: 4px;
  transition: background-color 0.15s ease;
}


.calendar-wrapper :deep(.v-calendar-month__day:hover) {
  background: rgba(var(--v-theme-primary), 0.025);
}


/* =========================================================
   HEADER DE LOS DÍAS
========================================================= */

.calendar-wrapper :deep(.v-calendar-weekly__head) {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.35);
}


.calendar-wrapper :deep(.v-calendar-weekly__head-weekday) {
  padding: 10px 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  opacity: 0.6;
}


/* =========================================================
   NÚMERO DEL DÍA
========================================================= */

.calendar-wrapper :deep(.v-calendar-month__day-label) {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 2px 5px 4px;
}


.calendar-wrapper :deep(.v-calendar-month__day-label .v-btn) {
  width: 28px;
  height: 28px;
  min-width: 28px;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 500;
}


/*
 * Día actual.
 */

.calendar-wrapper :deep(.v-calendar-month__day--current) {
  background: rgba(var(--v-theme-primary), 0.025);
}


.calendar-wrapper :deep(.v-calendar-month__day--current .v-calendar-month__day-label .v-btn) {
  background: rgb(var(--v-theme-primary));
  color: white;
  font-weight: 700;
}


/* =========================================================
   EVENTO
========================================================= */

.calendar-event {
  position: relative;
  display: flex;
  align-items: stretch;
  min-height: 24px;
  margin: 2px 2px 3px;
  border-radius: 5px;
  overflow: hidden;
  cursor: pointer;
  background: var(--event-bg);
  color: rgb(var(--v-theme-on-surface));
  transition:
    transform 0.12s ease,
    box-shadow 0.12s ease,
    background-color 0.12s ease;
}


.calendar-event:hover {
  z-index: 10;
  transform: translateY(-1px);
  background: var(--event-hover);
  box-shadow:
    0 2px 6px rgba(0, 0, 0, 0.12);
}


/*
 * Barra lateral característica
 * de Google Calendar.
 */

.event-accent {
  width: 3px;
  min-width: 3px;
  background: var(--event-color);
}


.event-content {
  min-width: 0;
  width: 100%;
  padding: 3px 5px;
}


.event-main {
  display: flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
}


.event-time {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 700;
  color: var(--event-color);
}


.event-title {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 11px;
  font-weight: 600;
  line-height: 17px;
}


.event-location {
  display: flex;
  align-items: center;
  gap: 2px;
  min-width: 0;
  margin-top: 1px;
  opacity: 0.65;
  font-size: 9px;
  line-height: 12px;
}


.event-location span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}


/* =========================================================
   TOOLTIP
========================================================= */

.event-tooltip {
  width: 310px;
  padding: 14px;
}


.tooltip-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 12px;
}


.tooltip-color {
  width: 4px;
  min-width: 4px;
  height: 22px;
  border-radius: 4px;
}


.tooltip-title {
  font-size: 14px;
  font-weight: 700;
  line-height: 20px;
}


.tooltip-info {
  display: flex;
  flex-direction: column;
  gap: 9px;
}


.tooltip-row {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  font-size: 12px;
  line-height: 18px;
  opacity: 0.9;
}


.tooltip-row .v-icon {
  flex-shrink: 0;
  margin-top: 1px;
}


.tooltip-description {
  margin-top: 3px;
  padding-top: 9px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  font-size: 12px;
  line-height: 18px;
  opacity: 0.8;
}


.tooltip-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  font-size: 11px;
  font-weight: 600;
  color: #8ab4f8;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

  .calendar-toolbar {
    flex-wrap: wrap;
    gap: 8px;
  }

  .calendar-title {
    order: -1;
    width: 100%;
    justify-content: center;
  }

  .calendar-toolbar-right {
    display: none;
  }

  .calendar-wrapper :deep(.v-calendar-month__week),
  .calendar-wrapper :deep(.v-calendar-month__day) {
    min-height: 82px;
  }

  .event-location {
    display: none;
  }

  .event-title {
    font-size: 10px;
  }

  .event-time {
    font-size: 9px;
  }
}

</style>
```