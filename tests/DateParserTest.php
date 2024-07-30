<?php

namespace OneSeven9955\Tests;

use Generator;
use OneSeven9955\DateParser\DateParser;
use OneSeven9955\DateParser\ParseException;
use PHPUnit\Framework\TestCase;

final class DateParserTest extends TestCase
{
    public function testConstructs(): void
    {
        $p = DateParser::from('2021-01-01');

        $this->assertInstanceOf(DateParser::class, $p);

        $p = DateParser::from('2021-01-01');

        $this->assertInstanceOf(DateParser::class, $p);
    }

    /**
     * @dataProvider validDates
     */
    public function testParsesValidDates(string $datetime, string $expectedDatetime): void
    {
        $p = DateParser::from($datetime);
        $r = $p->parse();

        $actualDatetime = $r->format('Y-m-d H:i:s');

        $this->assertEquals($actualDatetime, $expectedDatetime, $datetime);
    }

    /**
     * @return Generator<array{string,string}>
     */
    public function validDates(): Generator
    {
        // mm/dd/yy
        yield ["3/31/2014",			"2014-03-31 00:00:00"];
        yield ["03/31/2014",		"2014-03-31 00:00:00"];
        yield ["08/21/71",			"1971-08-21 00:00:00"];
        yield ["8/1/71",			"1971-08-01 00:00:00"];
        // yyyy/mm/dd
        yield ["2014/3/31",			"2014-03-31 00:00:00"];
        yield ["2014/03/31",		"2014-03-31 00:00:00"];
        yield ["2014-04-26",		"2014-04-26 00:00:00"];
        // mm.dd.yy
        yield ["3.31.2014",			"2014-03-31 00:00:00"];
        yield ["03.31.2014",		"2014-03-31 00:00:00"];
        yield ["08.21.71",			"1971-08-21 00:00:00"];
        yield ["2014.03",			"2014-03-01 00:00:00"];
        yield ["2014.03.30",		"2014-03-30 00:00:00"];

        yield ["oct 7, 1970",		"1970-10-07 00:00:00"];
        yield ["oct 7, '70",		"1970-10-07 00:00:00"];
        yield ["oct. 7, 1970",		"1970-10-07 00:00:00"];
        yield ["oct. 7, 70",		"1970-10-07 00:00:00"];
        yield ["October 7, 1970",	"1970-10-07 00:00:00"];
        yield ["October 7th, 1970",	"1970-10-07 00:00:00"];
        yield ["7 oct 70",			"1970-10-07 00:00:00"];
        yield ["7 oct 1970",		"1970-10-07 00:00:00"];
        yield ["03 February 2013",	"2013-02-03 00:00:00"];
        yield ["1 July 2013",		"2013-07-01 00:00:00"];
        yield ["2013-Feb-03",		"2013-02-03 00:00:00"];
        yield ["04/30/2025",		"2025-04-30 00:00:00"];
        yield ["April 8th, 2009",	"2009-04-08 00:00:00"];
    }

    /**
     * @dataProvider invalidDates
     */
    public function testFailsOnInvalidFormat(string $datetime, string $exceptionMessage): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $p = DateParser::from($datetime);
        $r = $p->parse();
    }

    /**
     * @return Generator<array{string,string}>
     */
    public function invalidDates(): Generator
    {
        // mm/dd/yy
        yield ["31/12/2014", "Overflow of the month: max 12, got 31"];
        yield ["13/01/2014", "Overflow of the month: max 12, got 13"];
        yield ["Frostbloom 8th, 2009", "Could not parse the date"];
    }
}
