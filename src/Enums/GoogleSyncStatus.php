<?php

namespace GKCode\GoogleCalendar\Enums;

enum GoogleSyncStatus: string
{
    case NOT_SYNCED = 'not_synced';
    case PENDING = 'pending';
    case SYNCED = 'synced';
    case ERROR = 'error';
    case DELETED_REMOTE = 'deleted_remote';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SYNCED => 'Sin sincronizar',
            self::PENDING => 'Pendiente',
            self::SYNCED => 'Sincronizado',
            self::ERROR => 'Error',
            self::DELETED_REMOTE => 'Eliminado en Google',
        };
    }
}
