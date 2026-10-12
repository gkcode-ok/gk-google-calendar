<?php

use GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar\AgendaGoogleController;
use GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar\GoogleCalendarConnectionController;
use GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar\GkServiciosTenantController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => '/GoogleCalendarTenant', 'middleware' => ['ensure.root','web']], function () {
    Route::get('/data', [GkServiciosTenantController::class, 'index'])->name('GoogleCalendarTenant.index');
    Route::post('/generar-token', [GkServiciosTenantController::class, 'generarToken'])->name('GoogleCalendarTenant.generarToken');
    Route::post('/catalogo', [GkServiciosTenantController::class, 'guardarCatalogo'])->name('GoogleCalendarTenant.catalogo');
    Route::post('/token', [GkServiciosTenantController::class, 'guardarToken'])->name('GoogleCalendarTenant.token');
    Route::post('/{companyId}/habilitar', [GkServiciosTenantController::class, 'habilitar'])->name('GoogleCalendarTenant.habilitar');
    Route::put('/{companyId}/deshabilitar', [GkServiciosTenantController::class, 'deshabilitar'])->name('GoogleCalendarTenant.deshabilitar');
});

Route::group(['prefix' => '/GoogleCalendar', 'middleware' => ['ensure.root']], function () {
    Route::get('/estado', [GoogleCalendarConnectionController::class, 'estado'])->name('GoogleCalendar.estado');
    Route::post('/conectar', [GoogleCalendarConnectionController::class, 'conectar'])->name('GoogleCalendar.conectar');
    Route::get('/oauth-status', [GoogleCalendarConnectionController::class, 'oauthStatus'])->name('GoogleCalendar.oauthStatus');
    Route::get('/calendarios', [GoogleCalendarConnectionController::class, 'calendarios'])->name('GoogleCalendar.calendarios');
    Route::post('/calendario-principal', [GoogleCalendarConnectionController::class, 'calendarioPrincipal'])->name('GoogleCalendar.calendarioPrincipal');
    Route::post('/eventos/{id}/sincronizar', [GoogleCalendarConnectionController::class, 'sincronizarEvento'])->name('GoogleCalendar.sincronizarEvento');
    Route::put('/desconectar', [GoogleCalendarConnectionController::class, 'desconectar'])->name('GoogleCalendar.desconectar');

    Route::get('/agenda', [AgendaGoogleController::class, 'index'])->name('GoogleCalendar.agenda.index');
    Route::get('/agenda/{eventId}', [AgendaGoogleController::class, 'show'])->name('GoogleCalendar.agenda.show');
    Route::post('/agenda', [AgendaGoogleController::class, 'store'])->name('GoogleCalendar.agenda.store');
    Route::put('/agenda/{eventId}', [AgendaGoogleController::class, 'update'])->name('GoogleCalendar.agenda.update');
    Route::delete('/agenda/{eventId}', [AgendaGoogleController::class, 'destroy'])->name('GoogleCalendar.agenda.destroy');
});
