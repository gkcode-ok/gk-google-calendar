<template>
  <div class="gcal-status-surface" :class="{ 'gcal-status-surface--compact': compact }">
    <!-- Barra superior con los 4 colores oficiales de Google -->
    <div class="gcal-brand-bar">
      <span class="gcal-brand-bar__blue" />
      <span class="gcal-brand-bar__red" />
      <span class="gcal-brand-bar__yellow" />
      <span class="gcal-brand-bar__green" />
    </div>

    <div class="gcal-status-body">
      <!-- Cabecera de estado -->
      <div class="gcal-status-header">
        <div class="gcal-logo-box">
          <svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true">
            <path fill="#4285F4" d="M19 4h-1V2h-2v2H8V2H6v2H5C3.89 4 3 4.9 3 6v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/>
            <path fill="#34A853" d="M7 12h5v5H7z"/>
          </svg>
        </div>

        <div class="gcal-status-header__info">
          <div class="d-flex align-center flex-wrap ga-2">
            <h3 class="gcal-status-header__title">Cuenta de Google Calendar</h3>
            <span class="gcal-badge" :class="`gcal-badge--${chip.tone}`">
              <span class="gcal-badge__dot" />
              {{ chip.text }}
            </span>
          </div>
          <p class="gcal-status-header__subtitle">
            {{ statusSubtitle }}
          </p>
        </div>
      </div>

      <!-- Alertas de estado (Error o Autorización pendiente) -->
      <div v-if="data.last_error" class="gcal-banner gcal-banner--error">
        <v-icon size="20" color="#d93025" class=" flex-shrink-0">mdi-alert-circle-outline</v-icon>
        <div class="gcal-banner__content">
          <div class="gcal-banner__title">No se pudo completar la sincronización</div>
          <div class="gcal-banner__text">{{ data.last_error }}</div>
        </div>
      </div>

      <div v-else-if="data.status === 'pending'" class="gcal-banner gcal-banner--info">
        <v-progress-circular indeterminate size="18" width="2" color="#1a73e8" class="flex-shrink-0" />
        <div class="gcal-banner__content">
          <div class="gcal-banner__title">Esperando autorización en Google…</div>
          <div class="gcal-banner__text">
            Completá el inicio de sesión en la ventana emergente de Google. La conexión se activará automáticamente.
          </div>
        </div>
      </div>

      <!-- Detalles cuando está conectado -->
      <div v-if="data.status === 'connected'" class="gcal-meta-grid">
        <div class="gcal-meta-item">
          <div class="gcal-meta-item__icon">
            <v-icon size="18" color="#1a73e8">mdi-calendar-check-outline</v-icon>
          </div>
          <div class="gcal-meta-item__body">
            <span class="gcal-meta-item__label">Calendario activo</span>
            <span class="gcal-meta-item__value">{{ data.calendar_summary || 'Calendario principal' }}</span>
          </div>
        </div>

        <div class="gcal-meta-item">
          <div class="gcal-meta-item__icon">
            <v-icon size="18" color="#1a73e8">mdi-earth</v-icon>
          </div>
          <div class="gcal-meta-item__body">
            <span class="gcal-meta-item__label">Zona horaria</span>
            <span class="gcal-meta-item__value">{{ data.calendar_timezone || 'America/Argentina/Buenos_Aires' }}</span>
          </div>
        </div>

        <div class="gcal-meta-item">
          <div class="gcal-meta-item__icon">
            <v-icon size="18" color="#1a73e8">mdi-sync</v-icon>
          </div>
          <div class="gcal-meta-item__body">
            <span class="gcal-meta-item__label">Última actividad</span>
            <span class="gcal-meta-item__value">{{ formatDate(data.last_sync_at || data.last_connection_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Pasos guía cuando NO está conectado -->
      <div v-else class="gcal-steps">
        <div class="gcal-step">
          <div class="gcal-step__num">1</div>
          <div>
            <div class="gcal-step__title">Autorizá tu cuenta</div>
            <div class="gcal-step__desc">Iniciá sesión de forma segura con Google OAuth 2.0.</div>
          </div>
        </div>
        <div class="gcal-step">
          <div class="gcal-step__num">2</div>
          <div>
            <div class="gcal-step__title">Seleccioná tu calendario</div>
            <div class="gcal-step__desc">Elegí cuál de tus calendarios de Google usar en la aplicación.</div>
          </div>
        </div>
        <div class="gcal-step">
          <div class="gcal-step__num">3</div>
          <div>
            <div class="gcal-step__title">Gestioná tu agenda</div>
            <div class="gcal-step__desc">Creá, editá y visualizá tus eventos en vistas Mes, Semana, Día o Lista.</div>
          </div>
        </div>
      </div>

      <!-- Acciones -->
      <div class="gcal-status-actions">
        <template v-if="data.status === 'connected'">
          <v-btn
            color="#1a73e8"
            variant="flat"
            rounded="pill"
            class="gcal-btn gcal-btn--primary text-none"
            prepend-icon="mdi-calendar-sync-outline"
            @click="emit('select-calendar')"
          >
            Cambiar calendario
          </v-btn>

          <v-btn
            variant="outlined"
            rounded="pill"
            class="gcal-btn gcal-btn--outlined text-none"
            prepend-icon="mdi-refresh"
            :loading="busy"
            @click="emit('verify')"
          >
            Verificar estado
          </v-btn>

          <v-spacer class="d-none d-sm-block" />

          <v-btn
            color="#d93025"
            variant="text"
            rounded="pill"
            class="gcal-btn text-none"
            prepend-icon="mdi-link-variant-off"
            @click="emit('disconnect')"
          >
            Desvincular cuenta
          </v-btn>
        </template>

        <template v-else-if="data.status === 'pending'">
          <v-btn
            color="#1a73e8"
            variant="flat"
            rounded="pill"
            class="gcal-btn gcal-btn--primary text-none"
            :loading="busy"
            prepend-icon="mdi-check-decagram-outline"
            @click="emit('verify')"
          >
            Ya autoricé, verificar ahora
          </v-btn>
          <v-btn
            variant="outlined"
            rounded="pill"
            class="gcal-btn gcal-btn--outlined text-none"
            prepend-icon="mdi-open-in-new"
            @click="emit('connect')"
          >
            Reabrir ventana de Google
          </v-btn>
        </template>

        <template v-else>
          <button
            type="button"
            class="gcal-google-signin-btn"
            :disabled="busy"
            @click="emit('connect')"
          >
            <span class="gcal-google-signin-btn__icon">
              <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/>
                <path fill="#FBBC05" d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.13-1.57.38-2.29V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.98-3.09z"/>
                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09c.95-2.85 3.6-4.96 6.73-4.96z"/>
              </svg>
            </span>
            <span class="gcal-google-signin-btn__label">
              {{ busy ? 'Conectando con Google…' : 'Conectar con Google Calendar' }}
            </span>
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: { type: Object, default: () => ({ status: 'not_configured' }) },
  busy: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['connect', 'verify', 'select-calendar', 'disconnect']);

const CHIPS = {
  connected: { tone: 'success', text: 'Conectado' },
  pending: { tone: 'warning', text: 'Autorización pendiente' },
  error: { tone: 'error', text: 'Requiere atención' },
  disconnected: { tone: 'neutral', text: 'Desconectado' },
  not_configured: { tone: 'neutral', text: 'Sin vincular' },
};

const chip = computed(() => CHIPS[props.data.status] || CHIPS.not_configured);

const statusSubtitle = computed(() => {
  if (props.data.status === 'connected') {
    return 'Tu agenda está sincronizada en tiempo real con tu calendario de Google.';
  }
  if (props.data.status === 'pending') {
    return 'Estamos esperando la confirmación de permisos desde tu cuenta de Google.';
  }
  return 'Vinculá tu cuenta de Google para gestionar tus eventos y reuniones directamente desde aquí.';
});

function formatDate(value) {
  if (!value) return 'Conectado recientemente';
  const d = new Date(value);
  return Number.isNaN(d.getTime()) ? 'Conectado recientemente' : d.toLocaleString('es-AR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<style scoped>
.gcal-status-surface {
  background: #ffffff;
  border: 1px solid #dadce0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.08), 0 1px 3px 1px rgba(60, 64, 67, 0.04);
  font-family: 'Google Sans', Roboto, 'Segoe UI', Arial, sans-serif;
  color: #1f1f1f;
}

.gcal-status-surface--compact {
  border-radius: 12px;
  box-shadow: none;
}

.gcal-brand-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  height: 4px;
}
.gcal-brand-bar__blue { background: #4285F4; }
.gcal-brand-bar__red { background: #EA4335; }
.gcal-brand-bar__yellow { background: #FBBC05; }
.gcal-brand-bar__green { background: #34A853; }

.gcal-status-body {
  padding: 24px;
}

.gcal-status-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}

.gcal-logo-box {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: #f8fafd;
  border: 1px solid #e8eaed;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.gcal-status-header__info {
  flex: 1;
  min-width: 0;
}

.gcal-status-header__title {
  font-size: 18px;
  font-weight: 600;
  color: #1f1f1f;
  margin: 0;
  line-height: 1.3;
}

.gcal-status-header__subtitle {
  font-size: 13.5px;
  color: #5f6368;
  margin: 4px 0 0;
  line-height: 1.45;
}

/* Badges estilo Material 3 */
.gcal-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.1px;
}

.gcal-badge__dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.gcal-badge--success {
  background: #e6f4ea;
  color: #137333;
}
.gcal-badge--success .gcal-badge__dot { background: #1e8e3e; }

.gcal-badge--warning {
  background: #fef7e0;
  color: #b06000;
}
.gcal-badge--warning .gcal-badge__dot { background: #f9ab00; }

.gcal-badge--error {
  background: #fce8e6;
  color: #c5221f;
}
.gcal-badge--error .gcal-badge__dot { background: #d93025; }

.gcal-badge--neutral {
  background: #f1f3f4;
  color: #5f6368;
}
.gcal-badge--neutral .gcal-badge__dot { background: #80868b; }

/* Banners */
.gcal-banner {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 12px;
  margin-bottom: 18px;
}

.gcal-banner--error {
  background: #fce8e6;
  border: 1px solid #f6aea9;
  color: #a50e0e;
}

.gcal-banner--info {
  background: #e8f0fe;
  border: 1px solid #d2e3fc;
  color: #174ea6;
}

.gcal-banner__title {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 2px;
}

.gcal-banner__text {
  font-size: 12.5px;
  opacity: 0.9;
}

/* Metadata Grid cuando está conectado */
.gcal-meta-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 22px;
}

.gcal-meta-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #f8fafd;
  border: 1px solid #e8eaed;
}

.gcal-meta-item__icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #e8f0fe;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.gcal-meta-item__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.gcal-meta-item__label {
  font-size: 11.5px;
  color: #5f6368;
  font-weight: 500;
}

.gcal-meta-item__value {
  font-size: 13.5px;
  font-weight: 600;
  color: #1f1f1f;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Pasos onboarding */
.gcal-steps {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 22px;
}

.gcal-step {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  background: #f8fafd;
  border: 1px solid #edf2fa;
}

.gcal-step__num {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #e8f0fe;
  color: #1a73e8;
  font-size: 12.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.gcal-step__title {
  font-size: 13px;
  font-weight: 600;
  color: #1f1f1f;
  margin-bottom: 2px;
}

.gcal-step__desc {
  font-size: 12px;
  color: #5f6368;
  line-height: 1.4;
}

/* Botones */
.gcal-status-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.gcal-btn {
  font-weight: 500;
  letter-spacing: 0.1px;
}

.gcal-btn--outlined {
  border-color: #dadce0 !important;
  color: #3c4043 !important;
}

/* Botón oficial estilo "Sign in with Google" */
.gcal-google-signin-btn {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  height: 42px;
  padding: 0 20px 0 14px;
  border-radius: 999px;
  border: 1px solid #dadce0;
  background: #1a73e8;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
  box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
}

.gcal-google-signin-btn:hover:not(:disabled) {
  background: #1557b0;
  box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
}

.gcal-google-signin-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.gcal-google-signin-btn__icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
