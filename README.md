# GK-Google-Calendar (`gkcode-ok/gk-google-calendar`)

Paquete de integración de **Google Calendar** mediante OAuth 2.0 y la API intermedia **GK Servicios** para proyectos basados en **GK Nexora** (Laravel + Vue 3). Permite administrar empresas habilitadas (tenants), vincular la cuenta de Google de cada usuario, elegir su calendario principal y ofrecer el calendario actual del usuario dentro de la aplicación (vistas Mes/Semana/Día/Lista, eventos simples y recurrentes, recordatorios y sincronización con la agenda local).

---

## 📋 Requisitos previos

El proyecto anfitrión (ej. `GK-Nexora` o `nexora-estudio`) debe contar con:
- PHP `^8.2`
- Laravel `^10.0 | ^11.0 | ^12.0`
- Clases base del ecosistema GK:
  - `App\Http\Controllers\BaseController`
  - `App\Models\ModelGeneral`
  - `App\Models\System\Company`
  - `App\Models\System\AgendaEvent`
  - `App\Models\Generic\User`
  - `App\Traits\BelongsToCompany`
- Middlewares del ecosistema GK: `['web', 'auth', 'verified', 'estadoverificar', 'blockip', 'isnotclient']` y `ensure.root` (para el panel de administración de tenants).

---

## 🚀 1. Instalación por Composer

### A. Agregar el repositorio en `composer.json` del proyecto principal

En el `composer.json` de tu proyecto Laravel (por ejemplo `GK-Nexora` o `nexora-estudio`), agrega el repositorio VCS:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/gkcode-ok/gk-google-calendar.git"
    }
]
```

*(Si estás probándolo localmente antes de subirlo a GitHub, puedes usar `"type": "path"` y `"url": "../gk-google-calendar"`).*

### B. Instalar el paquete

Ejecuta en la terminal del proyecto principal:

```bash
composer require gkcode-ok/gk-google-calendar:dev-main
```

> **Nota:** Gracias al *Package Auto-Discovery* de Laravel, el proveedor `GKCode\GoogleCalendar\GoogleCalendarServiceProvider` se registrará automáticamente sin necesidad de agregarlo manualmente en `config/app.php`. Además, registra automáticamente la relación `gkServiciosTenant` sobre `App\Models\System\Company`.

---

## 🗄️ 2. Migraciones de Base de Datos

El paquete carga automáticamente sus migraciones desde `database/migrations`:
1. `2026_10_04_120000_create_google_calendar_connections_table` (`google_calendar_connections`)
2. `2026_10_04_120100_add_google_calendar_fields_to_agenda_events_table` (campos de sincronización en `agenda_events`)
3. `2026_10_04_130000_create_gk_servicios_tenants_table` (`gk_servicios_tenants`)
4. `2026_10_05_200000_create_gk_servicios_settings_table` (`gk_servicios_settings`)
5. `2026_10_05_210000_add_catalog_ids_to_gk_servicios_settings_table` (IDs de catálogo en `gk_servicios_settings`)

Ejecuta las migraciones:

```bash
php artisan migrate
```

*(Opcional)* Si deseas publicar el archivo de configuración `config/google-calendar.php` en tu proyecto:

```bash
php artisan vendor:publish --tag=google-calendar-config
```

---

## ⚙️ 3. Configuración de Servicios y Variables de Entorno (`.env`)

### A. Configuración del paquete (`config/google-calendar.php`)
El paquete fusiona automáticamente su configuración bajo la clave `google-calendar`:

```php
return [
    'timezone' => env(
        'GK_SERVICIOS_TIMEZONE',
        'America/Argentina/Buenos_Aires'
    ),

    'gkservicios' => [
        'base_url' => rtrim((string) env('GK_SERVICIOS_URL', 'https://gkservicios.gk-code.com/api/v1'), '/'),
        'registration_token' => env('GK_SERVICIOS_REGISTRATION_TOKEN'),
        'google_calendar_provider_id' => (int) env('GK_SERVICIOS_GOOGLE_PROVIDER_ID', 1),
        'google_calendar_service_id' => (int) env('GK_SERVICIOS_GOOGLE_CALENDAR_SERVICE_ID', 1),
        'timezone' => env('GK_SERVICIOS_TIMEZONE', 'America/Argentina/Buenos_Aires'),
    ],
];
```

### B. Variables en `.env`
Agrega las variables de conexión con **GK Servicios** en el archivo `.env` del proyecto principal:

```dotenv
GK_SERVICIOS_URL=https://gkservicios.gk-code.com/api/v1
GK_SERVICIOS_REGISTRATION_TOKEN=
GK_SERVICIOS_GOOGLE_PROVIDER_ID=1
GK_SERVICIOS_GOOGLE_CALENDAR_SERVICE_ID=1
GK_SERVICIOS_TIMEZONE=America/Argentina/Buenos_Aires
```

> **Nota:** El `registration_token` y los IDs del catálogo (`provider_id` / `service_id`) también pueden generarse y guardarse directamente desde la pantalla de administración **Google Calendar - Empresas** (`GoogleCalendarTenant`) ingresando el token administrativo de GK Servicios (`INTEGRATIONS_ADMIN_TOKEN`).

---

## 🖥️ 4. Configuración Frontend (Vite + Vue 3)

### A. Configurar el alias en `vite.config.js`
En el archivo `vite.config.js` del proyecto principal, agrega el alias `@GoogleCalendar` dentro de `resolve.alias` (tanto en el bloque `build` como en el `else` de desarrollo):

```javascript
resolve: {
  alias: {
    "@Public": path.resolve(__dirname, "./public"),
    "@Components": path.resolve(__dirname, "./resources/js/components"),
    "@GKModule": path.resolve(__dirname, "./resources/js/gk-module"),

    // Paquetes externos de GK-Code
    "@GoogleCalendar": path.resolve(
      __dirname,
      "./vendor/gkcode-ok/gk-google-calendar/resources/js/components"
    ),
  },
},
```

### B. Registrar los componentes en `resources/js/components/imports.js`
El componente **`AgendaGoogle`** ya integra internamente a **`GoogleCalendarAdmin`** (`Conexion/index.vue`), unificando en una sola pantalla tanto la vinculación OAuth 2.0 y selección del calendario principal como la agenda interactiva (vistas Mes, Semana, Día y Lista). De esta forma **no es necesario crear otro ítem de menú** para conectar la cuenta.

En el archivo `imports.js` del proyecto principal, importa los componentes desde el alias `@GoogleCalendar`:

```javascript
/* Google Calendar */
import AgendaGoogle from "@GoogleCalendar/GoogleCalendar/Agenda/index.vue";
import GoogleCalendarTenant from "@GoogleCalendar/GoogleCalendar/Admin/index.vue";

export default {
  // ... otros componentes
  AgendaGoogle,
  GoogleCalendarTenant,
};
```

#### Detalle de cada componente:
| Nombre en `imports.js` | Ruta del componente | Función en la aplicación |
| :--- | :--- | :--- |
| **`AgendaGoogle`** | `@GoogleCalendar/GoogleCalendar/Agenda/index.vue` | **Agenda + Conexión Google Calendar (Unificado):** si el usuario aún no vinculó su cuenta, muestra el asistente de conexión OAuth 2.0 estilo Google Workspace; una vez conectado, muestra su calendario actual (vistas Mes, Semana, Día y Agenda/Lista, ABM de eventos simples/recurrentes y recordatorios) junto con el panel desplegable para cambiar de calendario o desvincular la cuenta. |
| **`GoogleCalendarTenant`** | `@GoogleCalendar/GoogleCalendar/Admin/index.vue` | **Administración ROOT de empresas (Tenants):** permite generar el token de registro con GK Servicios y habilitar/deshabilitar empresas para usar Google Calendar. |

*(Opcionalmente, si algún proyecto requiere montar el panel de conexión por separado, `@GoogleCalendar/GoogleCalendar/Conexion/index.vue` sigue funcionando de forma independiente, al igual que los calendarios base `@GoogleCalendar/Calendar/GoogleStyleCalendar.vue` y `@GoogleCalendar/Calendar/MonthCalendar.vue`).*

---

## 🧹 5. Limpieza si migras desde una versión integrada en el proyecto

Si el proyecto donde lo estás instalando ya tenía una versión local anterior del módulo de Google Calendar:

1. **Rutas**: Elimina `require __DIR__ . '/GoogleCalendar/web.php';` en el `routes/web.php` del proyecto principal y borra `routes/GoogleCalendar/web.php` (el paquete ya registra automáticamente todas las rutas bajo `/home/GoogleCalendar` y `/home/GoogleCalendarTenant`).
2. **Actualizar referencias de Namespaces en el proyecto anfitrión**:
   - Si en `App\Models\System\Company` tenías definida la relación `gkServiciosTenant()`, actualízala al modelo del paquete (o elimínala, ya que `GoogleCalendarServiceProvider` la registra dinámicamente):
     ```php
     public function gkServiciosTenant()
     {
         return $this->hasOne(\GKCode\GoogleCalendar\Models\GkServiciosTenant::class, 'company_id');
     }
     ```
   - Si en `App\Http\Controllers\System\AgendaController` inyectas el servicio de sincronización, actualiza el `use`:
     ```php
     use GKCode\GoogleCalendar\Services\GoogleCalendarSyncService;
     ```
3. **Controladores, Servicios, Modelos, Enums y Componentes locales antiguos**: Elimina los archivos locales duplicados:
   - `app/Http/Controllers/GoogleCalendar/`
   - `app/Models/Generic/GoogleCalendar/`
   - `app/Services/GoogleCalendar/`
   - `app/Enums/GoogleCalendarConnectionStatus.php` y `app/Enums/GoogleSyncStatus.php`
   - `resources/js/components/Otros/GoogleCalendar/` y `resources/js/components/Otros/Calendar/`
4. **Limpiar cachés de Laravel**:
   ```bash
   php artisan optimize:clear
   ```
5. **Recompilar assets**:
   ```bash
   npm run build
   # o en desarrollo:
   npm run dev
   ```

---

## 🛣️ Endpoints incluidos automáticamente

### 1. Conexión del usuario y Calendario actual (`/home/GoogleCalendar`)
Registradas con los middlewares `['web', 'auth', 'verified', 'estadoverificar', 'blockip', 'isnotclient']`:

| Método | Ruta | Nombre de ruta | Descripción |
| :--- | :--- | :--- | :--- |
| `GET` | `/home/GoogleCalendar/estado` | `GoogleCalendar.estado` | Estado actual de la conexión y calendario seleccionado del usuario |
| `POST` | `/home/GoogleCalendar/conectar` | `GoogleCalendar.conectar` | Registra la integración en GK Servicios e inicia el flujo OAuth con Google |
| `GET` | `/home/GoogleCalendar/oauth-status` | `GoogleCalendar.oauthStatus` | Verifica el estado de autorización OAuth en GK Servicios |
| `GET` | `/home/GoogleCalendar/calendarios` | `GoogleCalendar.calendarios` | Lista los calendarios disponibles en la cuenta de Google del usuario |
| `POST` | `/home/GoogleCalendar/calendario-principal` | `GoogleCalendar.calendarioPrincipal` | Selecciona y guarda el calendario principal a utilizar en la aplicación |
| `POST` | `/home/GoogleCalendar/eventos/{id}/sincronizar` | `GoogleCalendar.sincronizarEvento` | Sincroniza manualmente un evento de `agenda_events` hacia Google Calendar |
| `PUT` | `/home/GoogleCalendar/desconectar` | `GoogleCalendar.desconectar` | Desconecta la integración de Google Calendar del usuario actual |
| `GET` | `/home/GoogleCalendar/agenda` | `GoogleCalendar.agenda.index` | Lista los eventos del calendario de Google del usuario (por mes o rango `from`/`to`) |
| `GET` | `/home/GoogleCalendar/agenda/{eventId}` | `GoogleCalendar.agenda.show` | Obtiene la regla de recurrencia (`RRULE`) de un evento recurrente |
| `POST` | `/home/GoogleCalendar/agenda` | `GoogleCalendar.agenda.store` | Crea un evento (simple o recurrente, con recordatorios) en Google Calendar |
| `PUT` | `/home/GoogleCalendar/agenda/{eventId}` | `GoogleCalendar.agenda.update` | Actualiza un evento o serie recurrente (`instance`, `following`, `all`) |
| `DELETE` | `/home/GoogleCalendar/agenda/{eventId}` | `GoogleCalendar.agenda.destroy` | Elimina un evento o serie recurrente (`instance`, `following`, `all`) |

### 2. Administración de empresas / Tenants (`/home/GoogleCalendarTenant`)
Registradas con los middlewares `['web', 'auth', 'verified', 'estadoverificar', 'blockip', 'isnotclient', 'ensure.root']` (exclusivo ROOT):

| Método | Ruta | Nombre de ruta | Descripción |
| :--- | :--- | :--- | :--- |
| `GET` | `/home/GoogleCalendarTenant/data` | `GoogleCalendarTenant.index` | Listado paginado de empresas y su estado de habilitación en GK Servicios |
| `POST` | `/home/GoogleCalendarTenant/generar-token` | `GoogleCalendarTenant.generarToken` | Genera el token de registro y descubre los IDs de catálogo usando el token admin |
| `POST` | `/home/GoogleCalendarTenant/catalogo` | `GoogleCalendarTenant.catalogo` | Guarda manualmente `provider_id` y `service_id` del catálogo |
| `POST` | `/home/GoogleCalendarTenant/token` | `GoogleCalendarTenant.token` | Guarda manualmente el `registration_token` de GK Servicios |
| `POST` | `/home/GoogleCalendarTenant/{companyId}/habilitar` | `GoogleCalendarTenant.habilitar` | Registra y habilita una empresa (tenant) en GK Servicios |
| `PUT` | `/home/GoogleCalendarTenant/{companyId}/deshabilitar` | `GoogleCalendarTenant.deshabilitar` | Deshabilita el uso de Google Calendar para una empresa |
