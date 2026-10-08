<?php

namespace App\Services\GoogleCalendar;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Traduce entre la estructura de recurrencia de Nexora y las reglas RRULE de Google Calendar.
 */
class RecurrenceRuleService
{
    public const FREQUENCIES = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
    public const DAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    private const SUPPORTED_KEYS = ['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'COUNT', 'UNTIL'];

    /**
     * @param array{freq:string,interval?:int,by_day?:array,end_type?:string,until?:string,count?:int} $rule
     */
    public function build(array $rule, CarbonInterface $start, string $tz): string
    {
        $freq = strtoupper((string) $rule['freq']);
        $parts = ['FREQ=' . $freq];

        $interval = max(1, (int) ($rule['interval'] ?? 1));
        if ($interval > 1) {
            $parts[] = 'INTERVAL=' . $interval;
        }

        if ($freq === 'WEEKLY') {
            $days = array_values(array_intersect(self::DAYS, array_map('strtoupper', (array) ($rule['by_day'] ?? []))));
            $parts[] = 'BYDAY=' . implode(',', $days ?: [self::DAYS[$start->dayOfWeekIso - 1]]);
        }

        if ($freq === 'MONTHLY') {
            $parts[] = 'BYMONTHDAY=' . $start->day;
        }

        $endType = $rule['end_type'] ?? 'never';
        if ($endType === 'until' && !empty($rule['until'])) {
            $parts[] = 'UNTIL=' . $this->untilValue(Carbon::parse($rule['until'], $tz)->endOfDay());
        } elseif ($endType === 'count' && !empty($rule['count'])) {
            $parts[] = 'COUNT=' . (int) $rule['count'];
        }

        return 'RRULE:' . implode(';', $parts);
    }

    /**
     * Devuelve la estructura de Nexora o null si no hay regla RRULE.
     * "readonly" indica que la regla usa elementos que el formulario no puede editar.
     */
    public function parse(array $recurrence, string $tz): ?array
    {
        $line = $this->rruleLine($recurrence);
        if ($line === null) {
            return null;
        }

        $map = $this->toMap($line);
        $readonly = count(array_diff(array_keys($map), self::SUPPORTED_KEYS)) > 0;

        $byDay = [];
        foreach (array_filter(explode(',', $map['BYDAY'] ?? '')) as $day) {
            if (!in_array($day, self::DAYS, true)) {
                $readonly = true;
                continue;
            }
            $byDay[] = $day;
        }

        $freq = $map['FREQ'] ?? '';
        if (!in_array($freq, self::FREQUENCIES, true)) {
            $readonly = true;
        }

        $endType = 'never';
        $until = null;
        $count = null;
        if (isset($map['COUNT'])) {
            $endType = 'count';
            $count = (int) $map['COUNT'];
        } elseif (isset($map['UNTIL'])) {
            $endType = 'until';
            $until = Carbon::parse($map['UNTIL'], 'UTC')->setTimezone($tz)->format('Y-m-d');
        }

        return [
            'freq' => $freq,
            'interval' => max(1, (int) ($map['INTERVAL'] ?? 1)),
            'by_day' => $byDay,
            'end_type' => $endType,
            'until' => $until,
            'count' => $count,
            'readonly' => $readonly,
        ];
    }

    /**
     * Reemplaza la regla RRULE conservando EXDATE/RDATE y demas lineas existentes.
     */
    public function replaceRule(array $recurrence, ?string $newRule): array
    {
        $others = array_values(array_filter($recurrence, fn ($line) => !$this->isRrule($line)));

        return $newRule ? array_merge([$newRule], $others) : $others;
    }

    /**
     * Corta la serie para que la ultima ocurrencia sea anterior a $before.
     */
    public function truncate(array $recurrence, CarbonInterface $before): array
    {
        $until = $this->untilValue($before->copy()->subSecond());

        return array_map(function ($line) use ($until) {
            if (!$this->isRrule($line)) {
                return $line;
            }

            $map = $this->toMap($line);
            unset($map['COUNT'], $map['UNTIL']);
            $map['UNTIL'] = $until;

            return 'RRULE:' . collect($map)->map(fn ($v, $k) => $k . '=' . $v)->implode(';');
        }, $recurrence);
    }

    public function withoutEnd(array $recurrence): array
    {
        return array_map(function ($line) {
            if (!$this->isRrule($line)) {
                return $line;
            }

            $map = $this->toMap($line);
            unset($map['COUNT'], $map['UNTIL']);

            return 'RRULE:' . collect($map)->map(fn ($v, $k) => $k . '=' . $v)->implode(';');
        }, $recurrence);
    }

    private function untilValue(CarbonInterface $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private function isRrule(string $line): bool
    {
        return str_starts_with(strtoupper($line), 'RRULE:');
    }

    private function rruleLine(array $recurrence): ?string
    {
        foreach ($recurrence as $line) {
            if ($this->isRrule((string) $line)) {
                return (string) $line;
            }
        }

        return null;
    }

    private function toMap(string $line): array
    {
        $map = [];
        foreach (explode(';', substr($line, 6)) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, '');
            if ($key !== '') {
                $map[strtoupper($key)] = $value;
            }
        }

        return $map;
    }
}
