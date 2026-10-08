<template>
  <div class="gcal">
    <div class="gcal-toolbar">
      <div class="gcal-toolbar-left">
        <v-btn variant="outlined" class="gcal-today text-none" @click="goToday">Hoy</v-btn>
        <v-btn icon="mdi-chevron-left" variant="text" size="small" aria-label="Anterior" @click="move(-1)" />
        <v-btn icon="mdi-chevron-right" variant="text" size="small" aria-label="Siguiente" @click="move(1)" />
        <h2 class="gcal-title">{{ title }}</h2>
      </div>

      <v-btn-toggle v-model="view" mandatory density="comfortable" variant="outlined" divided class="gcal-views">
        <v-btn value="month" class="text-none">Mes</v-btn>
        <v-btn value="week" class="text-none">Semana</v-btn>
        <v-btn value="day" class="text-none">Día</v-btn>
      </v-btn-toggle>
    </div>

    <!-- VISTA MES -->
    <div v-if="view === 'month'" class="gcal-month" :style="{ height }">
      <div class="gcal-month-head">
        <div v-for="name in weekdayNames" :key="name" class="gcal-month-head-cell">{{ name }}</div>
      </div>

      <div class="gcal-month-grid">
        <div v-for="day in days" :key="key(day)" class="gcal-cell"
          :class="{ 'gcal-cell--other': day.getMonth() !== cursor.getMonth() }" @click="emit('date-click', day, false)">
          <button type="button" class="gcal-daynum" :class="{ 'gcal-daynum--today': isToday(day) }"
            @click.stop="openDay(day)">
            {{ day.getDate() === 1 ? `${day.getDate()} ${monthShort(day)}` : day.getDate() }}
          </button>

          <div v-for="ev in visibleEvents(day)" :key="ev.id + key(day)" class="gcal-chip"
            :class="{ 'gcal-chip--filled': ev.allDay }" :style="chipStyle(ev)" :title="ev.title"
            @click.stop="emit('event-click', ev)">
            <span v-if="!ev.allDay" class="gcal-dot" :style="{ background: colorOf(ev) }" />
            <span v-if="!ev.allDay" class="gcal-chip-time">{{ hourLabel(ev.start) }}</span>
            <span class="gcal-chip-title">{{ ev.title }}</span>
            <v-icon v-if="ev.raw?.is_recurring" size="12" class="ml-1">mdi-repeat</v-icon>
          </div>

          <button v-if="hiddenCount(day) > 0" type="button" class="gcal-more" @click.stop="openDay(day)">
            {{ hiddenCount(day) }} más
          </button>
        </div>
      </div>
    </div>

    <!-- VISTAS SEMANA / DÍA -->
    <div v-else class="gcal-time" :style="{ height }">
      <div class="gcal-time-head" :style="{ gridTemplateColumns: columns }">
        <div class="gcal-gutter" />
        <div v-for="day in days" :key="key(day)" class="gcal-time-head-cell" @click="openDay(day)">
          <div class="gcal-time-head-name" :class="{ 'gcal-time-head-name--today': isToday(day) }">
            {{ weekdayShort(day) }}
          </div>
          <div class="gcal-daynum gcal-daynum--big" :class="{ 'gcal-daynum--today': isToday(day) }">
            {{ day.getDate() }}
          </div>
        </div>
      </div>

      <div v-if="hasAllDay" class="gcal-allday" :style="{ gridTemplateColumns: columns }">
        <div class="gcal-gutter gcal-allday-label">Todo el día</div>
        <div v-for="day in days" :key="key(day)" class="gcal-allday-cell">
          <div v-for="ev in allDayEvents(day)" :key="ev.id" class="gcal-chip gcal-chip--filled" :style="chipStyle(ev)"
            :title="ev.title" @click.stop="emit('event-click', ev)">
            <span class="gcal-chip-title">{{ ev.title }}</span>
          </div>
        </div>
      </div>

      <div ref="scroller" class="gcal-time-scroll">
        <div class="gcal-time-body" :style="{ gridTemplateColumns: columns, height: HOUR_PX * 24 + 'px' }">
          <div class="gcal-gutter gcal-hours">
            <div v-for="h in 24" :key="h" class="gcal-hour-label" :style="{ height: HOUR_PX + 'px' }">
              <span v-if="h > 1">{{ String(h - 1).padStart(2, '0') }}:00</span>
            </div>
          </div>

          <div v-for="day in days" :key="key(day)" class="gcal-day-col" :style="{ backgroundSize: `100% ${HOUR_PX}px` }"
            @click="onSlotClick($event, day)">
            <div v-for="item in timedLayout(day)" :key="item.ev.id" class="gcal-block" :style="blockStyle(item)"
              :title="item.ev.title" @click.stop="emit('event-click', item.ev)">
              <div class="gcal-block-title">
                {{ item.ev.title }}
                <v-icon v-if="item.ev.raw?.is_recurring" size="12">mdi-repeat</v-icon>
              </div>
              <div class="gcal-block-time">{{ hourLabel(item.ev.start) }} – {{ hourLabel(item.ev.end) }}</div>
            </div>

            <div v-if="isToday(day)" class="gcal-now" :style="{ top: nowTop + 'px' }"><span /></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

/**
 * Calendario estilo Google Calendar con vistas Mes, Semana y Día.
 *
 * events: [{ id, title, start: Date, end: Date, allDay, color, raw }]
 * (en eventos allDay, `end` es inclusivo)
 *
 * Emite:
 * - range-change { from, to, view, date }  (fechas 'YYYY-MM-DD' inclusivas del rango visible)
 * - date-click (date, hasTime)
 * - event-click (event)
 */
const props = defineProps({
  events: { type: Array, default: () => [] },
  height: { type: String, default: '720px' },
  initialDate: { type: Date, default: () => new Date() },
});

const emit = defineEmits(['range-change', 'date-click', 'event-click']);

const HOUR_PX = 48;
const MAX_CHIPS = 3;
const DEFAULT_COLOR = '#1a73e8';

const view = ref('month');
const cursor = ref(startOfDay(props.initialDate));
const scroller = ref(null);
const now = ref(new Date());
let timer = null;

const pad = (n) => String(n).padStart(2, '0');
const key = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

function startOfDay(d) {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function addDays(d, n) {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
}

// Semana de lunes a domingo.
function startOfWeek(d) {
  return addDays(startOfDay(d), -((d.getDay() + 6) % 7));
}

const days = computed(() => {
  if (view.value === 'day') return [cursor.value];
  if (view.value === 'week') {
    const start = startOfWeek(cursor.value);
    return Array.from({ length: 7 }, (_, i) => addDays(start, i));
  }
  const first = new Date(cursor.value.getFullYear(), cursor.value.getMonth(), 1);
  const start = startOfWeek(first);
  return Array.from({ length: 42 }, (_, i) => addDays(start, i));
});

const columns = computed(() => `56px repeat(${days.value.length}, minmax(0, 1fr))`);

const fmt = (options, d) => new Intl.DateTimeFormat('es-AR', options).format(d);
const weekdayNames = computed(() => days.value.slice(0, 7).map((d) => fmt({ weekday: 'short' }, d).replace('.', '').toUpperCase()));
const weekdayShort = (d) => fmt({ weekday: 'short' }, d).replace('.', '').toUpperCase();
const monthShort = (d) => fmt({ month: 'short' }, d).replace('.', '');
const hourLabel = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const isToday = (d) => key(d) === key(now.value);
const colorOf = (ev) => ev.color || DEFAULT_COLOR;

const title = computed(() => {
  if (view.value === 'month') {
    const t = fmt({ month: 'long', year: 'numeric' }, cursor.value);
    return t.charAt(0).toUpperCase() + t.slice(1);
  }
  if (view.value === 'day') {
    const t = fmt({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }, cursor.value);
    return t.charAt(0).toUpperCase() + t.slice(1);
  }
  const a = days.value[0];
  const b = days.value[6];
  if (a.getMonth() === b.getMonth()) {
    return `${a.getDate()} – ${b.getDate()} de ${fmt({ month: 'long' }, a)} de ${a.getFullYear()}`;
  }
  return `${a.getDate()} ${monthShort(a)} – ${b.getDate()} ${monthShort(b)} ${b.getFullYear()}`;
});

/* ===== Eventos por día ===== */

const eventsByDay = computed(() => {
  const map = new Map();
  for (const ev of props.events) {
    if (!(ev.start instanceof Date) || Number.isNaN(ev.start.getTime())) continue;
    let end = ev.end instanceof Date && !Number.isNaN(ev.end.getTime()) ? ev.end : ev.start;
    // Un evento que termina exactamente a las 00:00 no ocupa ese día.
    if (!ev.allDay && end > ev.start && end.getHours() === 0 && end.getMinutes() === 0) {
      end = new Date(end.getTime() - 1);
    }
    for (let d = startOfDay(ev.start); d <= end; d = addDays(d, 1)) {
      const k = key(d);
      if (!map.has(k)) map.set(k, []);
      map.get(k).push(ev);
    }
  }
  for (const list of map.values()) {
    list.sort((a, b) => Number(b.allDay) - Number(a.allDay) || a.start - b.start);
  }
  return map;
});

const dayEvents = (day) => eventsByDay.value.get(key(day)) || [];
const visibleEvents = (day) => dayEvents(day).slice(0, MAX_CHIPS);
const hiddenCount = (day) => Math.max(0, dayEvents(day).length - MAX_CHIPS);
const allDayEvents = (day) => dayEvents(day).filter((e) => e.allDay);
const hasAllDay = computed(() => days.value.some((d) => allDayEvents(d).length > 0));

function chipStyle(ev) {
  return ev.allDay ? { background: colorOf(ev) } : {};
}

/* ===== Vista semana / día ===== */

function timedLayout(day) {
  const dayStart = startOfDay(day).getTime();
  const dayEnd = addDays(day, 1).getTime();

  const items = dayEvents(day)
    .filter((e) => !e.allDay)
    .map((ev) => {
      const s = Math.max(ev.start.getTime(), dayStart);
      const e = Math.min(Math.max(ev.end.getTime(), ev.start.getTime() + 30 * 60000), dayEnd);
      return { ev, start: (s - dayStart) / 60000, end: (e - dayStart) / 60000, col: 0, cols: 1 };
    })
    .sort((a, b) => a.start - b.start || b.end - a.end);

  let cluster = [];
  let clusterEnd = -1;
  let columnEnds = [];

  const close = () => {
    cluster.forEach((i) => { i.cols = columnEnds.length; });
    cluster = [];
    columnEnds = [];
  };

  for (const item of items) {
    if (cluster.length && item.start >= clusterEnd) close();
    let col = columnEnds.findIndex((end) => end <= item.start);
    if (col === -1) {
      col = columnEnds.length;
      columnEnds.push(0);
    }
    columnEnds[col] = item.end;
    item.col = col;
    clusterEnd = Math.max(clusterEnd, item.end);
    cluster.push(item);
  }
  close();

  return items;
}

function blockStyle(item) {
  const width = 100 / item.cols;
  return {
    top: (item.start / 60) * HOUR_PX + 'px',
    height: Math.max(((item.end - item.start) / 60) * HOUR_PX - 2, 20) + 'px',
    left: item.col * width + '%',
    width: `calc(${width}% - 2px)`,
    background: colorOf(item.ev),
  };
}

function onSlotClick(event, day) {
  const rect = event.currentTarget.getBoundingClientRect();
  const minutes = Math.floor(((event.clientY - rect.top) / HOUR_PX) * 2) * 30;
  const date = startOfDay(day);
  date.setMinutes(minutes);
  emit('date-click', date, true);
}

const nowTop = computed(() => ((now.value.getHours() * 60 + now.value.getMinutes()) / 60) * HOUR_PX);

/* ===== Navegación ===== */

function move(step) {
  const c = cursor.value;
  if (view.value === 'month') cursor.value = new Date(c.getFullYear(), c.getMonth() + step, 1);
  else if (view.value === 'week') cursor.value = addDays(c, step * 7);
  else cursor.value = addDays(c, step);
}

function goToday() {
  cursor.value = startOfDay(new Date());
}

function openDay(day) {
  cursor.value = startOfDay(day);
  view.value = 'day';
}

function emitRange() {
  const list = days.value;
  emit('range-change', {
    from: key(list[0]),
    to: key(list[list.length - 1]),
    view: view.value,
    date: key(cursor.value),
  });
}

function scrollToWorkday() {
  nextTick(() => {
    if (scroller.value) scroller.value.scrollTop = HOUR_PX * 7;
  });
}

watch(() => `${view.value}|${days.value[0].getTime()}|${days.value.length}`, () => {
  emitRange();
  if (view.value !== 'month') scrollToWorkday();
});

onMounted(() => {
  emitRange();
  timer = setInterval(() => { now.value = new Date(); }, 60000);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.gcal {
  --gcal-border: #dadce0;
  --gcal-text: #3c4043;
  --gcal-muted: #70757a;
  --gcal-blue: #1a73e8;
  display: flex;
  flex-direction: column;
  background: #fff;
  color: var(--gcal-text);
  font-family: 'Google Sans', Roboto, 'Segoe UI', Arial, sans-serif;
}

.gcal-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 8px 4px 12px;
}

.gcal-toolbar-left {
  display: flex;
  align-items: center;
  gap: 4px;
}

.gcal-today {
  border-color: var(--gcal-border);
  color: var(--gcal-text);
  margin-right: 8px;
  font-weight: 500;
}

.gcal-title {
  font-size: 22px;
  font-weight: 400;
  margin: 0 0 0 12px;
  white-space: nowrap;
}

.gcal-views {
  border-color: var(--gcal-border);
}

.gcal-month,
.gcal-time {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--gcal-border);
  border-radius: 8px;
  overflow: hidden;
}

/* ===== Mes ===== */
.gcal-month-head,
.gcal-month-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
}

.gcal-month-head-cell {
  padding: 8px 0;
  text-align: center;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.8px;
  color: var(--gcal-muted);
  border-bottom: 1px solid var(--gcal-border);
}

.gcal-month-grid {
  flex: 1;
  grid-template-rows: repeat(6, minmax(0, 1fr));
}

.gcal-cell {
  border-right: 1px solid var(--gcal-border);
  border-bottom: 1px solid var(--gcal-border);
  padding: 2px 4px;
  overflow: hidden;
  cursor: pointer;
  min-width: 0;
}

.gcal-cell:nth-child(7n) {
  border-right: none;
}

.gcal-cell:nth-last-child(-n + 7) {
  border-bottom: none;
}

.gcal-cell--other .gcal-daynum {
  color: var(--gcal-muted);
}

.gcal-daynum {
  display: block;
  margin: 2px auto 2px;
  min-width: 26px;
  height: 26px;
  line-height: 26px;
  padding: 0 4px;
  border-radius: 13px;
  border: none;
  background: transparent;
  font-size: 12px;
  font-weight: 500;
  text-align: center;
  color: var(--gcal-text);
  cursor: pointer;
}

.gcal-daynum:hover {
  background: #f1f3f4;
}

.gcal-daynum--today,
.gcal-daynum--today:hover {
  background: var(--gcal-blue);
  color: #fff;
}

.gcal-daynum--big {
  font-size: 22px;
  min-width: 46px;
  height: 46px;
  line-height: 46px;
  border-radius: 23px;
  font-weight: 400;
}

.gcal-chip {
  display: flex;
  align-items: center;
  height: 20px;
  margin-bottom: 2px;
  padding: 0 6px;
  border-radius: 4px;
  font-size: 12px;
  line-height: 20px;
  overflow: hidden;
  white-space: nowrap;
  cursor: pointer;
}

.gcal-chip:hover {
  background: #f1f3f4;
}

.gcal-chip--filled {
  color: #fff;
}

.gcal-chip--filled:hover {
  filter: brightness(0.92);
}

.gcal-dot {
  flex: none;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 6px;
}

.gcal-chip-time {
  flex: none;
  margin-right: 4px;
  color: var(--gcal-muted);
}

.gcal-chip-title {
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}

.gcal-more {
  display: block;
  width: 100%;
  padding: 0 6px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 12px;
  font-weight: 500;
  color: var(--gcal-text);
  cursor: pointer;
  border-radius: 4px;
}

.gcal-more:hover {
  background: #f1f3f4;
}

/* ===== Semana / Día ===== */
.gcal-time-head,
.gcal-allday,
.gcal-time-body {
  display: grid;
}

.gcal-time-head {
  border-bottom: 1px solid var(--gcal-border);
  padding-right: 8px;
}

.gcal-time-head-cell {
  text-align: center;
  padding: 6px 0 4px;
  border-left: 1px solid var(--gcal-border);
  cursor: pointer;
}

.gcal-time-head-name {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.8px;
  color: var(--gcal-muted);
}

.gcal-time-head-name--today {
  color: var(--gcal-blue);
}

.gcal-allday {
  border-bottom: 1px solid var(--gcal-border);
  padding-right: 8px;
}

.gcal-allday-label {
  font-size: 10px;
  color: var(--gcal-muted);
  padding: 6px 4px 0 0;
  text-align: right;
}

.gcal-allday-cell {
  border-left: 1px solid var(--gcal-border);
  padding: 2px;
  min-width: 0;
}

.gcal-time-scroll {
  flex: 1;
  overflow-y: auto;
}

.gcal-time-body {
  position: relative;
}

.gcal-gutter {
  min-width: 0;
}

.gcal-hour-label {
  position: relative;
  font-size: 10px;
  color: var(--gcal-muted);
  text-align: right;
  padding-right: 8px;
}

.gcal-hour-label span {
  position: relative;
  top: -6px;
}

.gcal-day-col {
  position: relative;
  border-left: 1px solid var(--gcal-border);
  background-image: linear-gradient(to bottom, var(--gcal-border) 1px, transparent 1px);
  cursor: pointer;
}

.gcal-block {
  position: absolute;
  box-sizing: border-box;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid #fff;
  color: #fff;
  font-size: 12px;
  overflow: hidden;
  cursor: pointer;
}

.gcal-block:hover {
  filter: brightness(0.92);
  z-index: 2;
}

.gcal-block-title {
  font-weight: 500;
  line-height: 15px;
}

.gcal-block-time {
  font-size: 11px;
  opacity: 0.9;
}

.gcal-now {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background: #ea4335;
  pointer-events: none;
  z-index: 3;
}

.gcal-now span {
  position: absolute;
  left: -6px;
  top: -5px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #ea4335;
}
</style>
