<?php

namespace OneSeven9955\DateParser;

enum StateEnum
{
    case StartOver;
    case Start;
    case Alpha;
    case Digit;
    case YearDash;
    case DigitDash;
    case DigitSlash;
    case DigitColon;
    case DigitDot;
    case DigitWs;
    case YearDashDash;
    case DigitDashAlpha;
    case YearDashAlphaDash;
    case DigitDashAlphaDash;
    case YearDashDashWs;
    case DigitDotDot;
    case AlphaWs;
    case AlphaWsMonth;
    case AlphaWsMore;
    case WeekdayAbbrComma;
    case WeekdayComma;
    case AlphaPeriodWsDigit;
    case AlphaWsAlpha;
    case AlphaWsDigit;
    case AlphaWsDigitMore;
    case AlphaWsDigitYearPossible;
    case AlphaWsMonthSuffix;
    case AlphaWsDigitMoreWs;
    case AlphaWsDigitMoreWsYear;
    case AlphaWsMonthMore;
    case DigitWsMonthLong;
    case DigitWsMonthYear;
}
