This PHP package is a date string parser that can interpret date strings in various formats. It is implemented using a finite state machine. With this package, developers can easily parse date strings without relying on external libraries or tools.

## Getting Started
```php
use OneSeven9955\DateParser\DateParser;
use OneSeven9955\DateParser\ParseException;

// Strict parse: an invalid date string will cause ParseException
try {
    $datetime = DateParser::from('30/04/2025')->parse(); // DateTime { date: 2015-04-30 00:00:00.0 UTC (+00:00) }
} catch (ParseException $ex) {
    printf("Unable to parse the date: %s\n", $ex->getMessage());
}

// Silent parse: an invalid date string will cause null as return value
$datetimeOrNull = DateParser::from('30#04#2025')->parseSilent(); // null
```

## Supported Date Formats
The time part is being omitted.

**YYYY-MM-DD**
- 2014-04-26

**MM/DD/YY**
- 3/31/2014
- 03/31/2014
- 08/21/71
- 8/1/71

**YYYY/MM/DD**
- 2014/3/31
- 2014/03/31

**MM.DD.YY**
- 3.31.2014
- 03.31.2014
- 08.21.71
- 2014.03
- 2014.03.30

**Textual Month**
- oct 7, 1970
- oct 7, '70
- oct. 7, 1970
- oct. 7, 70
- October 7, 1970
- October 7th, 1970
- 7 oct 70
- 7 oct 1970
- 03 February 2013
- 1 July 2013
- 2013-Feb-03
