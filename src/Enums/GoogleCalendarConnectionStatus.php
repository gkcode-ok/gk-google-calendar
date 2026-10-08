<?php

namespace GKCode\GoogleCalendar\Enums;

enum GoogleCalendarConnectionStatus: string
{
    case NOT_CONFIGURED = 'not_configured';
    case PENDING = 'pending';
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NOT_CONFIGURED => 'No configurado',
            self::PENDING => 'Pendiente de autorizacion',
            self::CONNECTED => 'Conectado',
            self::DISCONNECTED => 'Desconectado',
            self::ERROR => 'Con error',
        };
    }
}
