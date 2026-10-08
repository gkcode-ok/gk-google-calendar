<?php

namespace App\Http\Controllers\GoogleCalendar;

use App\Http\Controllers\BaseController;
use App\Models\Generic\GoogleCalendar\GoogleCalendarConnection;
use App\Services\GoogleCalendar\GoogleCalendarEventService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AgendaGoogleController extends BaseController
{
    protected $modelo = 'Agenda Google';
    protected $mainModel = GoogleCalendarConnection::class;

    private const NOT_CONNECTED = 'Google Calendar no esta conectado. Configuralo desde Configuracion > Google Calendar.';

    public function __construct(private readonly GoogleCalendarEventService $events)
    {
    }

    public function index(Request $request): array
    {
        $connection = $this->connection();
        if (!$connection) {
            return ['status' => -1, 'descripcion' => self::NOT_CONNECTED];
        }

        $campos = Validator::make($request->all(), [
            'filter_month' => ['nullable', 'integer', 'between:1,12'],
            'filter_year' => ['nullable', 'integer', 'between:2000,2100'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'title' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if (!empty($campos['from'])) {
            $from = $campos['from'];
            $to = $campos['to'];
        } else {
            $first = Carbon::create((int) ($campos['filter_year'] ?? now()->year), (int) ($campos['filter_month'] ?? now()->month), 1);
            $from = $first->toDateString();
            $to = $first->copy()->endOfMonth()->toDateString();
        }

        $result = $this->events->list($connection, $from, $to, $campos['title'] ?? null);

        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => 'No fue posible obtener los eventos: ' . $result['error']];
        }

        return [
            'status' => 0,
            'descripcion' => 'Listado de Agenda Google',
            'data' => ['data' => $result['events'], 'calendar' => $connection->calendar_summary],
        ];
    }

    public function show(Request $request, string $eventId): array
    {
        $connection = $this->connection();
        if (!$connection) {
            return ['status' => -1, 'descripcion' => self::NOT_CONNECTED];
        }

        $result = $this->events->recurrence($connection, $request->query('recurring_event_id') ?: $eventId);
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => 'No se pudo obtener la recurrencia del evento: ' . $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Recurrencia del evento', 'data' => $result['payload']];
    }

    public function store(Request $request): array
    {
        $connection = $this->connection();
        if (!$connection) {
            return ['status' => -1, 'descripcion' => self::NOT_CONNECTED];
        }

        $result = $this->events->create($connection, $this->validated($request));
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => 'No se pudo crear el evento: ' . $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Evento Creado Correctamente', 'data' => $result['payload'], 'channel' => 'gkcode'];
    }

    public function update(Request $request, string $eventId): array
    {
        $connection = $this->connection();
        if (!$connection) {
            return ['status' => -1, 'descripcion' => self::NOT_CONNECTED];
        }

        $result = $this->events->update($connection, $eventId, $this->validated($request));
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => 'No se pudo modificar el evento: ' . $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Evento Modificado Correctamente', 'data' => $result['payload'], 'channel' => 'gkcode'];
    }

    public function destroy(Request $request, string $eventId): array
    {
        $connection = $this->connection();
        if (!$connection) {
            return ['status' => -1, 'descripcion' => self::NOT_CONNECTED];
        }

        $campos = Validator::make($request->all(), [
            'scope' => ['nullable', 'in:instance,following,all'],
            'recurring_event_id' => ['nullable', 'string', 'max:255'],
            'original_starts_at' => ['nullable', 'date'],
        ])->validate();

        $result = $this->events->delete(
            $connection,
            $eventId,
            $campos['scope'] ?? null,
            $campos['recurring_event_id'] ?? null,
            $campos['original_starts_at'] ?? null
        );
        // 404: el evento ya no existe en Google, el objetivo se cumplio.
        if (!$result['ok'] && ($result['status'] ?? null) !== 404) {
            return ['status' => -1, 'descripcion' => 'No se pudo eliminar el evento: ' . $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Evento Eliminado Correctamente', 'data' => '', 'channel' => 'gkcode'];
    }

    private function validated(Request $request): array
    {
        return Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'scope' => ['nullable', 'in:instance,following,all'],
            'recurring_event_id' => ['nullable', 'string', 'max:255'],
            'original_starts_at' => ['nullable', 'date'],
            'previous_starts_at' => ['nullable', 'date'],
            'reminders' => ['nullable', 'array', 'max:5'],
            'reminders.*.method' => ['required', 'in:popup,email'],
            'reminders.*.minutes' => ['required', 'integer', 'between:0,40320'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.freq' => ['nullable', 'in:DAILY,WEEKLY,MONTHLY,YEARLY'],
            'recurrence.interval' => ['nullable', 'integer', 'between:1,999'],
            'recurrence.by_day' => ['nullable', 'array'],
            'recurrence.by_day.*' => ['in:MO,TU,WE,TH,FR,SA,SU'],
            'recurrence.end_type' => ['nullable', 'in:never,until,count'],
            'recurrence.until' => ['nullable', 'required_if:recurrence.end_type,until', 'date_format:Y-m-d'],
            'recurrence.count' => ['nullable', 'required_if:recurrence.end_type,count', 'integer', 'between:1,999'],
            'recurrence.readonly' => ['nullable', 'boolean'],
        ])->validate();
    }

    private function connection(): ?GoogleCalendarConnection
    {
        $user = Auth::user();

        return $this->events->connectionFor($user->company_id, $user->id);
    }
}
