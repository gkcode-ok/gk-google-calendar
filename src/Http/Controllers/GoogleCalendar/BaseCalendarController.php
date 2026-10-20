<?php

namespace GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar;

use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Controlador base autonomo del paquete.
 * Funciona en proyectos con o sin App\Http\Controllers\BaseController.
 */
abstract class BaseCalendarController extends Controller
{
    protected $modelo = 'Google Calendar';
    protected $mainModel = null;

    public function callAction($method, $parameters)
    {
        try {
            $response = parent::callAction($method, $parameters);

            if (is_array($response) && request()->expectsJson()) {
                return response()->json($response);
            }

            return $response;
        } catch (ValidationException $ve) {
            return response()->json([
                'status' => -1,
                'descripcion' => 'Error de validación de datos.',
                'errors' => $ve->errors(),
                'message' => $ve->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => -1,
                'descripcion' => $e->getMessage() ?: 'Error en la acción del controlador.',
            ], 500);
        }
    }
}
