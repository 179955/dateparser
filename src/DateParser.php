<?php

declare(strict_types=1);

namespace OneSeven9955\DateParser;

use DateTimeInterface;
use OneSeven9955\DateParser\StateEnum;
use OneSeven9955\DateParser\Week;
use OneSeven9955\DateParser\Month;
use OneSeven9955\DateParser\ParseException;

final class DateParser
{
    protected string $dateStr;
    private array $dateChars;
    private StateEnum $dateState = StateEnum::Start;

    private int $yearPos = 0;
    private int $yearLen = 0;

    private int $monthPos = 0;
    private int $monthLen = 0;

    private int $dayPos = 0;
    private int $dayLen = 0;

    private int $firstPartLen = 0;

    private string $fullMonth = "";

    private int $skipPos = 0;

    private function __construct(string $dateStr)
    {
        $this->dateStr = trim($dateStr);
        $this->dateChars = mb_str_split($this->dateStr) ?? [];
    }

    public static function from(string $dateStr): self
    {
        return new static($dateStr);
    }

    /**
     * Parse an unknown date string format.
     * Raises an exception when an unexpected position found.
     *
     * @throws ParseException
     */
    public function parse(): \DateTimeInterface
    {
        $this->performParse();

        if ($this->yearLen <= 0) {
            throw ParseException::missingUnit('year', $this->dateStr);
        }

        if ($this->monthLen <= 0) {
            throw ParseException::missingUnit('month', $this->dateStr);
        }

        $dayStr = implode('', array_slice($this->dateChars, $this->dayPos, $this->dayLen));
        $dayInt = -1;
        if (ctype_digit($dayStr)) {
            $dayInt = (int) $dayStr;
        } else {
            $dayInt = 1;
        }

        $monthStr = implode('', array_slice($this->dateChars, $this->monthPos, $this->monthLen));
        $monthInt = -1;
        if (ctype_digit($monthStr)) {
            $monthInt = (int) $monthStr;

            if (1 > $monthInt) {
                throw ParseException::unitUnderflow('month', 1, $monthInt);
            }
            if (12 < $monthInt) {
                throw ParseException::unitOverflow('month', 12, $monthInt);
            }
        } else {
            $monthInt = Month::number($monthStr) ?? throw ParseException::invalidTextualUnit('month', $monthStr);
        }

        $yearStr = implode('', array_slice($this->dateChars, $this->yearPos, $this->yearLen));
        $yearInt = -1;
        if (ctype_digit($yearStr)) {
            $yearInt = (int) $yearStr;
            if ($this->yearLen == 2) {
                $yearInt += 1900;
            }
        } else {
            throw ParseException::invalidTextualUnit('year', $yearStr);
        }

        $dt = new \DateTime();
        $dt->setTime(hour: 0, minute: 0, second: 0);
        $dt->setDate(year: $yearInt, month: $monthInt, day: $dayInt);

        return $dt;
    }

    /**
     * Parse an unknown date string format.
     * Suppresses parse exceptions.
     */
    public function parseSilent(): DateTimeInterface|ParseException
    {
        try {
            return $this->parse();
        } catch (ParseException) {
            return null;
        }
    }

    /**
     * @throws ParseException
     */
    protected function performParse(): void
    {
        $this->dateState = StateEnum::Start;
        $this->yearPos = $this->yearLen = $this->monthPos = $this->monthLen = $this->dayLen = $this->dayPos = 0;
        $charsCount = count($this->dateChars);

        $i = 0;

        for (; $i < $charsCount; $i++) {
            $this->processDateState($i, $this->dateChars[$i]);

            if ($this->dateState === StateEnum::StartOver) {
                $this->performParse();
                return;
            }
        }

        $this->coalesceDate($i);

        if (!empty($this->fullMonth)) {
            $this->setFullMonthLen();
        }

        switch ($this->dateState) {
            case StateEnum::YearDashAlphaDash:
                // 2013-Feb-03
                // 2013-Feb-3
                $this->dayLen = $i - $this->dayPos;
                $this->assertDay();
                break;

            case StateEnum::DigitWsMonthLong:
                // 18 January 2018
                // 8 January 2018
                $this->monthPos = $this->dayLen + 1;
                $this->monthLen = $charsCount - strlen('  2018') - $this->dayLen;
                break;
            default:
                break;
        }
    }

    /**
     * @param int $i
     * @param string $char
     * @throws ParseException
     */
    private function processDateState(int &$i, string $char): void
    {
        match ($this->dateState) {
            StateEnum::Start => (function () use (&$i, $char): void {
                if (ctype_digit($char)) {
                    $this->dateState = StateEnum::Digit;
                } elseif (ctype_alpha($char)) {
                    $this->dateState = StateEnum::Alpha;
                } else {
                    throw ParseException::unexpectedDateStartChar();
                }
            })(),
            StateEnum::Digit => (function () use (&$i, $char): void {
                switch ($char) {
                    case '-':
                    case "\u{2212}":
                        // 2006-01-02
                        // 2013-Feb-03
                        // 13-Feb-03
                        // 29-Jun-2016
                        if ($i === 4) {
                            $this->dateState = StateEnum::YearDash;
                            $this->yearPos = 0;
                            $this->yearLen = $i;
                            $this->monthPos = $i + 1;
                            $this->assertYear();
                        } else {
                            $this->dateState = StateEnum::DigitDash;
                        }
                        break;
                    case '/':
                        // 03/31/2005
                        // 2014/02/24
                        $this->dateState = StateEnum::DigitSlash;

                        if ($i === 4) {
                            $this->yearPos = 0;
                            $this->yearLen = $i;
                            $this->monthPos = $i + 1;
                            $this->assertYear();
                        } else {
                            if ($this->monthLen === 0) {
                                $this->monthLen = $i;
                                $this->dayPos = $i + 1;
                                $this->assertMonth();
                            } elseif ($this->dayLen === 0) {
                                $this->dayLen = $i;
                                $this->monthPos = $i + 1;
                                $this->assertDay();
                            }
                        }
                        break;
                    case ':':
                        // 03/31/2005
                        // 2014/02/24
                        $this->dateState = StateEnum::DigitColon;

                        if ($i === 4) {
                            $this->yearLen = $i;
                            $this->monthPos = $i + 1;
                            $this->assertYear();
                        } else {
                            if ($this->monthLen === 0) {
                                $this->monthLen = $i;
                                $this->dayPos = $i + 1;
                                $this->assertMonth();
                            }
                        }
                        break;
                    case '.':
                        // 3.31.2014
                        // 08.21.71
                        // 2014.05
                        $this->dateState = StateEnum::DigitDot;

                        if ($i === 4) {
                            $this->yearLen = $i;
                            $this->monthPos = $i + 1;
                            $this->assertYear();
                        } else {
                            $this->monthPos = 0;
                            $this->monthLen = $i;
                            $this->dayPos = $i + 1;
                            $this->assertMonth();
                        }
                        break;
                    case ' ':
                        // 18 January 2018
                        // 8 January 2018
                        // 8 jan 2018
                        // 02 Jan 2018 23:59
                        // 02 Jan 2018 23:59:34
                        // 12 Feb 2006, 19:17
                        // 12 Feb 2006, 19:17:22
                        $this->dateState = StateEnum::DigitWs;
                        $this->dayPos = 0;
                        $this->dayLen = $i;
                        break;
                }
                $this->firstPartLen = $i;
            })(),
            StateEnum::DigitWs => (function () use (&$i, $char): void {
                // 18 January 2018
                // 8 January 2018
                // 8 jan 2018
                // 1 jan 18
                // 02 Jan 2018 23:59
                // 02 Jan 2018 23:59:34
                // 12 Feb 2006, 19:17
                // 12 Feb 2006, 19:17:22
                switch ($char) {
                    case ' ':
                        $this->yearPos = $i + 1;
                        $this->dayPos = 0;
                        $this->dayLen = $this->firstPartLen;
                        $this->assertDay();

                        if ($i > $this->dayLen + mb_strlen(' Sep')) {
                            // If len greater than space + 3 it must be full month
                            $this->dateState = StateEnum::DigitWsMonthLong;
                        } else {
                            // If len=3, the might be Feb or May?  Ie ambiguous abbreviated but
                            // we can parse may with either.  BUT, that means the
                            // format may not be correct?
                            $this->monthPos = $this->dayLen + 1;
                            $this->monthLen = $i - $this->monthPos;
                            $this->assertMonth();
                            $this->dateState = StateEnum::DigitWsMonthYear;
                        }
                        break;
                }
            })(),
            StateEnum::DigitWsMonthYear => (function () use (&$i, $char): void {
                // 8 jan 2018
                // 02 Jan 2018 23:59
                // 02 Jan 2018 23:59:34
                // 12 Feb 2006, 19:17
                // 12 Feb 2006, 19:17:2
                switch ($char) {
                    case ',':
                        $this->yearLen = $i - $this->yearPos;
                        $this->assertYear();
                        $i++;
                        break;
                    case ' ':
                        $this->yearLen = $i - $this->yearPos;
                        $this->assertYear();
                        break;
                }
            })(),
            StateEnum::YearDash => (function () use (&$i, $char): void {
                // dateYearDashDashT
                //  2006-01-02T15:04:05Z07:00
                // dateYearDashDashWs
                //  2013-04-01 22:43:22
                // dateYearDashAlphaDash
                //   2013-Feb-03
                switch ($char) {
                    case '-':
                        $this->monthLen = $i - $this->monthPos;
                        $this->dayPos = $i + 1;
                        $this->dateState = StateEnum::YearDashDash;
                        $this->assertMonth();
                        break;
                    default:
                        if (ctype_alpha($char)) {
                            $this->dateState = StateEnum::YearDashAlphaDash;
                        }
                }
            })(),
            StateEnum::YearDashAlphaDash => (function () use (&$i, $char): void {
                // 2013-Feb-03
                switch ($char) {
                    case '-':
                        $this->monthLen = $i - $this->monthPos;
                        $this->dayPos = $i + 1;
                        $this->assertMonth();
                }
            })(),
            StateEnum::DigitSlash => (function () use (&$i, $char): void {
                // 2014/07/10 06:55:38.156283
                // 03/19/2012 10:11:59
                // 04/2/2014 03:00:37
                // 3/1/2012 10:11:59
                // 4/8/2014 22:05
                // 3/1/2014
                // 10/13/2014
                // 01/02/2006
                // 1/2/06
                switch ($char) {
                    case '/':
                        if ($this->yearLen > 0) {
                            // 2014/07/10 06:55:38.156283
                            if ($this->monthLen === 0) {
                                $this->monthLen = $i - $this->monthPos;
                                $this->dayPos = $i + 1;
                                $this->assertMonth();
                            }
                        } elseif (true) {
                            if ($this->dayLen === 0) {
                                $this->dayLen = $i - $this->dayPos;
                                $this->yearPos = $i + 1;
                                $this->assertDay();
                            }
                        } else {
                            if ($this->monthLen === 0) {
                                $this->monthLen = $i - $this->monthPos;
                                $this->yearPos = $i + 1;
                                $this->assertMonth();
                            }
                        }
                        break;
                }
            })(),
            StateEnum::DigitDash => (function () use (&$i, $char): void {
                // 13-Feb-03
                // 29-Jun-2016
                if (ctype_alpha($char)) {
                    $this->dateState = StateEnum::DigitDashAlpha;
                    $this->monthPos = $i;
                } else {
                    throw ParseException::unexpectedCharPosition($i);
                }
            })(),
            StateEnum::DigitDashAlphaDash => (function () use (&$i, $char): void {
                // 13-Feb-03
                // 28-Feb-03
                // 29-Jun-2016
                switch ($char) {
                    case '-':
                        $this->monthLen = $i - $this->monthPos;
                        $this->yearPos = $i + 1;
                }
            })(),
            StateEnum::DigitWsMonthLong => (function () use (&$i, $char): void {
                // 18 January 2018
                // 8 January 2018
            })(),
            StateEnum::DigitDot => (function () use (&$i, $char): void {
                // This is the 2nd period
                // 3.31.2014
                // 08.21.71
                // 2014.05
                // 2018.09.30
                switch ($char) {
                    case '.':
                        if ($this->monthPos === 0) {
                            // 3.31.2014
                            $this->dayLen = $i - $this->dayPos;
                            $this->yearPos = $i + 1;
                            $this->assertDay();
                            $this->dateState = StateEnum::DigitDotDot;
                        } else {
                            $this->monthLen = $i - $this->monthPos;
                            $this->dayPos = $i + 1;
                            $this->assertMonth();
                            $this->dateState = StateEnum::DigitDotDot;
                        }
                        break;
                }
            })(),
            StateEnum::DigitDotDot => (function () use (&$i, $char): void {
            })(),
            StateEnum::Alpha => (function () use (&$i, $char): void {
                // dateAlphaWS
                //  Mon Jan _2 15:04:05 2006
                //  Mon Jan _2 15:04:05 MST 2006
                //  Mon Jan 02 15:04:05 -0700 2006
                //  Mon Aug 10 15:44:11 UTC+0100 2015
                //  Fri Jul 03 2015 18:04:07 GMT+0100 (GMT Daylight Time)
                //  dateAlphaWSDigit
                //    May 8, 2009 5:57:51 PM
                //    oct 1, 1970
                //  dateAlphaWsMonth
                //    April 8, 2009
                //  dateAlphaWsMore
                //    dateAlphaWsAtTime
                //      January 02, 2006 at 3:04pm MST-07
                //
                //  dateAlphaPeriodWsDigit
                //    oct. 1, 1970
                // dateWeekdayComma
                //   Monday, 02 Jan 2006 15:04:05 MST
                //   Monday, 02-Jan-06 15:04:05 MST
                //   Monday, 02 Jan 2006 15:04:05 -0700
                //   Monday, 02 Jan 2006 15:04:05 +0100
                // dateWeekdayAbbrevComma
                //   Mon, 02 Jan 2006 15:04:05 MST
                //   Mon, 02 Jan 2006 15:04:05 -0700
                //   Thu, 13 Jul 2017 08:58:40 +0100
                //   Tue, 11 Jul 2017 16:28:13 +0200 (CEST)
                //   Mon, 02-Jan-06 15:04:05 MST
                switch ($char) {
                    case ' ':
                        //      X
                        // April 8, 2009
                        if ($i > 3) {
                            // Determine whether it's the alpha name of month or day.
                            $possibleMonthUnit = mb_strtolower(mb_substr($this->getDateStr(), 0, $i));

                            if (Month::isFullTextualMonth($possibleMonthUnit)) {
                                $this->fullMonth = $possibleMonthUnit;

                                // mb_strlen(" 31, 2018")   = 9
                                if (mb_strlen(mb_substr($this->getDateStr(), $i)) < 10) {
                                    $this->dateState = StateEnum::AlphaWsMonth;
                                } else {
                                    $this->dateState = StateEnum::AlphaWsMore;
                                }

                                $this->dayPos = $i + 1;
                                break;
                            }
                        } else {
                            // dateAlphaWs
                            //   May 05, 2005, 05:05:05
                            //   May 05 2005, 05:05:05
                            //   Jul 05, 2005, 05:05:05
                            //   May 8 17:57:51 2009
                            //   May  8 17:57:51 2009
                            // skip & return to dateStart
                            //   Tue 05 May 2020, 05:05:05
                            //   Mon Jan  2 15:04:05 2006
                            $possibleDayUnit = mb_strtolower(mb_substr($this->getDateStr(), 0, $i));

                            if (Week::isTextualWeekDay($possibleDayUnit)) {
                                $this->dateStr = mb_substr($this->dateStr, $i + 1);
                                $this->dateState = StateEnum::StartOver;
                                break;
                            }
                            $this->dateState = StateEnum::AlphaWs;
                        }
                        break;
                    case ',':
                        // Mon, 02 Jan 2006
                        // $this->monthPos = 0
                        // $this->monthLen = i
                        if ($i === 3) {
                            $this->dateState = StateEnum::WeekdayAbbrComma;
                            $this->monthPos = 0;
                            $this->assertMonth();
                        } else {
                            $this->dateState = StateEnum::WeekdayComma;
                            $this->skipPos = $i + 2;
                            $i++;
                            // TODO: implement skip pos
                        }
                        break;
                    case '.':
                        // sept. 28, 2017
                        // jan. 28, 2017
                        $this->dateState = StateEnum::AlphaPeriodWsDigit;
                        if ($i === 3) {
                            $this->monthLen = $i;
                            $this->monthPos = 0;
                            $this->assertMonth();
                        } elseif ($i === 4) {
                            array_splice($this->dateChars, $i);
                            $this->dateState = StateEnum::StartOver;
                        } else {
                            throw ParseException::unexpectedCharPosition($i);
                        }
                        break;
                }
            })(),
            StateEnum::AlphaWs => (function () use (&$i, $char): void {
                // dateAlphaWsAlpha
                //   Mon Jan _2 15:04:05 2006
                //   Mon Jan _2 15:04:05 MST 2006
                //   Mon Jan 02 15:04:05 -0700 2006
                //   Fri Jul 03 2015 18:04:07 GMT+0100 (GMT Daylight Time)
                //   Mon Aug 10 15:44:11 UTC+0100 2015
                // dateAlphaWsDigit
                //   May 8, 2009 5:57:51 PM
                //   May 8 2009 5:57:51 PM
                //   May 8 17:57:51 2009
                //   May  8 17:57:51 2009
                //   May 08 17:57:51 2009
                //   oct 1, 1970
                //   oct 7, '70
                if (ctype_alpha($char)) {
                    $this->monthPos = $i;
                    $this->monthLen = 3;
                    $this->dayPos = 0;
                    $this->dayLen = 3;
                    $this->assertMonth();
                    $this->assertDay();
                    $this->dateState = StateEnum::AlphaWsAlpha;
                } elseif (ctype_digit($char)) {
                    $this->monthPos = 0;
                    $this->monthLen = 3;
                    $this->dayPos = $i;
                    $this->assertMonth();
                    $this->dateState = StateEnum::AlphaWsDigit;
                }
            })(),
            StateEnum::AlphaWsMore => (function () use (&$i, $char): void {
                // January 02, 2006, 15:04:05
                // January 02 2006, 15:04:05
                // January 2nd, 2006, 15:04:05
                // January 2nd 2006, 15:04:05
                // September 17, 2012 at 5:00pm UTC-05
                switch (true) {
                    case $char === ',':
                        //           x
                        // January 02, 2006, 15:04:05
                        if ($this->nextCharIs($i, ' ')) {
                            $this->dayLen = $i - $this->dayPos;
                            $this->yearPos = $i + 2;
                            $this->dateState = StateEnum::AlphaWsMonthMore;
                            $this->assertDay();
                            $i++;
                        }
                        break;
                    case $char === ' ':
                        //           x
                        // January 02 2006, 15:04:05
                        $this->dayLen = $i - $this->dayPos;
                        $this->yearPos = $i + 1;
                        $this->assertDay();
                        $this->dateState = StateEnum::AlphaWsMonthMore;
                        break;
                    case ctype_digit($char):
                        //         XX
                        // January 02, 2006, 15:04:05
                        break;
                    case ctype_alpha($char):
                        $this->dayLen = $i - $this->dayPos;
                        $this->dateState = StateEnum::AlphaWsMonthSuffix;
                        $i--;
                        break;
                }
            })(),
            StateEnum::AlphaWsDigit => (function () use (&$i, $char): void {
                // May 8, 2009 5:57:51 PM
                // May 8 2009 5:57:51 PM
                // oct 1, 1970
                // oct 7, '70
                // oct. 7, 1970
                // May 8 17:57:51 2009
                // May  8 17:57:51 2009
                // May 08 17:57:51 2009
                if ($char === ',') {
                    $this->dayLen = $i - $this->dayPos;
                    $this->dateState = StateEnum::AlphaWsDigitMore;
                    $this->assertDay();
                } elseif ($char === ' ') {
                    $this->dayLen = $i - $this->dayPos;
                    $this->yearPos = $i + 1;
                    $this->dateState = StateEnum::AlphaWsDigitYearPossible;

                    $this->assertDay();
                } elseif (ctype_alpha($char)) {
                    $this->dateState = StateEnum::AlphaWsMonthSuffix;
                    $i--;
                }
            })(),
            StateEnum::AlphaWsDigitMore => (function () use (&$i, $char): void {
                //       x
                // May 8, 2009 5:57:51 PM
                // May 05, 2005, 05:05:05
                // May 05 2005, 05:05:05
                // oct 1, 1970
                // oct 7, '70
                if ($char === ' ') {
                    $this->yearPos = $i + 1;
                    $this->dateState = StateEnum::AlphaWsDigitMoreWs;
                }
            })(),
            StateEnum::AlphaWsDigitMoreWs => (function () use (&$i, $char): void {
                //            x
                // May 8, 2009 5:57:51 PM
                // May 05, 2005, 05:05:05
                // oct 1, 1970
                // oct 7, '70
                switch ($char) {
                    case '\'':
                        $this->yearPos = $i + 1;
                        break;
                    case ' ':
                    case ',':
                        //            x
                        // May 8, 2009 5:57:51 PM
                        //            x
                        // May 8, 2009, 5:57:51 PM
                        $this->yearLen = $i - $this->yearPos;
                        $this->assertYear();
                        $this->dateState = StateEnum::AlphaWsDigitMoreWsYear;
                        break;
                }
            })(),
            StateEnum::AlphaWsMonth => (function () use (&$i, $char): void {
                // April 8, 2009
                // April 8 2009
                switch ($char) {
                    case ' ':
                    case ',':
                        //       x
                        // June 8, 2009
                        //       x
                        // June 8 2009
                        if ($this->dayLen === 0) {
                            $this->dayLen = $i - $this->dayPos;
                            $this->assertDay();
                        }
                        break;
                    case 's':
                    case 'S':
                    case 'r':
                    case 'R':
                    case 't':
                    case 'T':
                    case 'n':
                    case 'N':
                        // st, rd, nd, st
                        $this->dateState = StateEnum::AlphaWsMonthSuffix;
                        $i--;
                        break;
                    default:
                        if ($this->dayLen > 0 && $this->yearPos === 0) {
                            $this->yearPos = $i;
                        }
                }
            })(),
            StateEnum::AlphaPeriodWsDigit => (function () use (&$i, $char): void {
                //    oct. 7, '70
                switch (true) {
                    case $char === ' ':
                        break;
                    case ctype_digit($char):
                        $this->dateState = StateEnum::AlphaWsDigit;
                        $this->dayPos = $i;
                        break;
                    default:
                        throw ParseException::unexpectedCharPosition($i);
                }
            })(),
            StateEnum::AlphaWsMonthSuffix => (function () use (&$i, $char): void {
                //        x
                // April 8th, 2009
                // April 8th 2009
                switch ($char) {
                    case 't':
                    case 'T':
                        if ($this->nextCharIs($i, 'h') || $this->nextCharIs($i, 'H')) {
                            if (count($this->dateChars) > $i + 2) {
                                array_splice($this->dateChars, $i, 2);

                                $this->dateState = StateEnum::StartOver;
                            }
                        }
                        break;
                    case 'r':
                    case 'R':
                    case 'n':
                    case 'N':
                        if ($this->nextCharIs($i, 'd') || $this->nextCharIs($i, 'D')) {
                            if (count($this->dateChars) > $i + 2) {
                                array_splice($this->dateChars, $i, 2);

                                $this->dateState = StateEnum::StartOver;
                            }
                        }
                        break;
                    case 's':
                    case 'S':
                        if ($this->nextCharIs($i, 't') || $this->nextCharIs($i, 'T')) {
                            if (count($this->dateChars) > $i + 2) {
                                array_splice($this->dateChars, $i, 2);

                                $this->dateState = StateEnum::StartOver;
                            }
                        }
                        break;
                }
            })(),
            default => null
        };
    }

    private function nextCharIs(int $pos, string $char): bool
    {
        return isset($this->dateChars[$pos + 1]) && ($this->dateChars[$pos + 1] === $char);
    }

    /**
     * @throws ParseException
     */
    private function assertDay(): void
    {
        if (!in_array($this->dayLen, [1, 2], true)) {
            throw ParseException::unexpectedDateUnitLength('day', $this->dayLen);
        }
    }

    /**
     * @throws ParseException
     */
    private function assertMonth(): void
    {
        if ($this->monthLen < 1 || $this->monthLen > 4) {
            throw ParseException::unexpectedDateUnitLength('month', $this->monthLen);
        }
    }

    private function setFullMonthLen(): void
    {
        if ($this->monthPos !== 0) {
            return;
        }

        $this->monthLen = mb_strlen($this->fullMonth);
    }

    /**
     * @throws ParseException
     */
    private function assertYear(): void
    {
        if (!in_array($this->yearLen, [2, 4], true)) {
            throw ParseException::unexpectedDateUnitLength('year', $this->yearLen);
        }
    }

    /**
     * @param int $end
     * @throws ParseException
     */
    private function coalesceDate(int $end): void
    {
        if ($this->yearPos > 0) {
            if ($this->yearLen === 0) {
                $this->yearLen = $end - $this->yearPos;
            }

            $this->assertYear();
        }

        if ($this->monthPos > 0 && $this->monthLen === 0) {
            $this->monthLen = $end - $this->monthPos;

            $this->assertMonth();
        }

        if ($this->dayPos > 0 && $this->dayLen === 0) {
            $this->dayLen = $end - $this->dayPos;

            $this->assertDay();
        }
    }

    private function getDayStr(): string
    {
        return mb_substr($this->dateStr, $this->dayPos, $this->dayLen);
    }

    private function getMonthStr(): string
    {
        return mb_substr($this->dateStr, $this->monthPos, $this->monthLen);
    }

    private function getYearStr(): string
    {
        return mb_substr($this->dateStr, $this->yearPos, $this->yearLen);
    }

    public function getDateStr(): string
    {
        return implode('', $this->dateChars);
    }
}
