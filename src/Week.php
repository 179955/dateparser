<?php

declare(strict_types=1);

namespace OneSeven9955\DateParser;

final class Week
{
    private const FULL = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    private const SHORT = [
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
        'sun' => 7,
    ];

    public static function isTextualWeekDay(string $value): bool
    {
        $value = mb_strtolower($value);

        return isset(Week::FULL[$value]) || isset(Week::SHORT[$value]);
    }
}
