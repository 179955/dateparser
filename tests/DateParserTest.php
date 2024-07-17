<?php

namespace OneSeven9955\Tests;

use OneSeven9955\DateParser\DateParser;
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

    public function testParsesUnknownDateFormat(): void
    {
        $dateStrings = [
            // mm/dd/yy
            "3/31/2014" => '2014-03-31 00:00:00',
            "03/31/2014" => '2014-03-31 00:00:00',
            "08/21/71" => '1971-08-21 00:00:00',
            "8/1/71" => '1971-08-01 00:00:00',
            // yyyy/mm/dd
            "2014/3/31" => '2014-03-31 00:00:00',
            "2014/03/31" => '2014-03-31 00:00:00',
            "2014-04-26" => '2014-04-26 00:00:00',
            // mm.dd.yy
            "3.31.2014" => '2014-03-31 00:00:00',
            "03.31.2014" => '2014-03-31 00:00:00',
            "08.21.71" => '1971-08-21 00:00:00',
            "2014.03" => '2014-03-01 00:00:00',
            "2014.03.30" => '2014-03-30 00:00:00',

            "oct 7, 1970" => '1970-10-07 00:00:00',
            "oct 7, '70" => '1970-10-07 00:00:00',
            "oct. 7, 1970" => '1970-10-07 00:00:00',
            "oct. 7, 70" => '1970-10-07 00:00:00',
            "October 7, 1970" => '1970-10-07 00:00:00',
            "October 7th, 1970" => '1970-10-07 00:00:00',
            "7 oct 70" => '1970-10-07 00:00:00',
            "7 oct 1970" => '1970-10-07 00:00:00',
            "03 February 2013" => '2013-02-03 00:00:00',
            "1 July 2013" => '2013-07-01 00:00:00',
            "2013-Feb-03" => '2013-02-03 00:00:00',
            "04/30/2025" => '2025-04-30 00:00:00',
        ];

        foreach ($dateStrings as $datetime => $expectedDatetime) {
            $p = DateParser::from($datetime);
            $r = $p->parse();

            $actualDatetime = $r->format('Y-m-d H:i:s');

            $this->assertEquals($actualDatetime, $expectedDatetime, $datetime);
        }
    }
}
