<?php

namespace OneSeven9955\DateParser;

use Exception;

final class ParseException extends Exception
{
    public static function unexpectedCharPosition(int $pos): ParseException
    {
        return new ParseException("Unexpected char at $pos position.");
    }

    public static function unexpectedDateStartChar(): ParseException
    {
        return new ParseException("Unexpected date start char.");
    }

    public static function notImplementedCase(): ParseException
    {
        return new ParseException("Not implemented case.");
    }

    public static function unexpectedDateUnitLength(string $unit, int $length): ParseException
    {
        return new ParseException("Unexpected the $unit unit length. Got $length.");
    }

    public static function missingUnit(string $unit, string $dateStr): ParseException
    {
        return new ParseException("Missing unit $unit in '$dateStr'.");
    }

    public static function unitOverflow(string $unit, int $max, int $value): ParseException
    {
        return new ParseException("Overflow of the $unit: max $max, got $value");
    }

    public static function unitUnderflow(string $unit, int $min, int $value): ParseException
    {
        return new ParseException("Underflow of the $unit: min $min, got $value");
    }

    public static function invalidTextualUnit(string $unit, string $value): ParseException
    {
        return new ParseException("Got invalid textual value of $unit: '$value'.");
    }

    public static function couldNotParse(string $value): ParseException
    {
        return new ParseException("Could not parse the date: '$value'.");
    }
}
