<?php

declare(strict_types=1);

namespace OneSeven9955\DateParser;

final class Month
{
    private const FULL = [
        'january' => 1,
        'february' => 2,
        'march' => 3,
        'april' => 4,
        'may' => 5,
        'june' => 6,
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
    ];

    private const SHORT = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12,
    ];

    public static function isFullTextualMonth(string $value): bool
    {
        $value = mb_strtolower($value);

        return isset(Month::FULL[$value]);
    }

    public static function isShortTextualMonth(string $value): bool
    {
        $value = mb_strtolower($value);

        return isset(Month::SHORT[$value]);
    }

    public static function isTextualMonth(string $value): bool
    {
        $value = mb_strtolower($value);

        return isset(Month::FULL[$value]) || isset(Month::SHORT[$value]);
    }

    public static function number(string $value): ?int
    {
        $value = mb_strtolower($value);

        return Month::FULL[$value] ?? Month::SHORT[$value] ?? null;
    }
}
