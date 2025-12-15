SunCalc PHP
===========

[![Tests](https://github.com/tuxonice/suncalc-php/actions/workflows/tests.yml/badge.svg)](https://github.com/tuxonice/suncalc-php/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/tuxonice/suncalc-php.svg)](https://packagist.org/packages/tuxonice/suncalc-php)
[![PHP Version](https://img.shields.io/packagist/php-v/tuxonice/suncalc-php.svg)](https://packagist.org/packages/tuxonice/suncalc-php)
[![Total Downloads](https://img.shields.io/packagist/dt/tuxonice/suncalc-php.svg)](https://packagist.org/packages/tuxonice/suncalc-php)
[![License](https://img.shields.io/packagist/l/tuxonice/suncalc-php.svg)](LICENSE)

SunCalc PHP is a tiny PHP library for calculating sun position, sunlight phases (times for sunrise, sunset, dusk, etc.), 
moon position, and lunar phase for a given location and time. This fork brings the original [gregseth/suncalc-php](https://github.com/gregseth/suncalc-php) 
library up to PHP 8, publishes it as a modern Composer package, and keeps full API compatibility with the original 
JavaScript library created by [Vladimir Agafonkin](http://agafonkin.com/en) ([@mourner](https://github.com/mourner)).

Most calculations are based on the formulas given in the excellent Astronomy Answers articles about 
the [position of the sun](http://aa.quae.nl/en/reken/zonpositie.html) and [the planets](http://aa.quae.nl/en/reken/hemelpositie.html). You can read about the different twilight phases calculated by 
SunCalc in the [Twilight article on Wikipedia](http://en.wikipedia.org/wiki/Twilight).

## Requirements

- PHP ^8.0
- ext-date (enabled by default on most PHP installations)

## Installation

Install the package via Composer:

```bash
composer require tuxonice/suncalc-php
```

Once installed, the library is available under the `Tlab\\SunCalc` namespace via PSR-4 autoloading.

## Usage example

```php
<?php

use Tlab\SunCalc\SunCalc;

$sunCalc = new SunCalc(new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')), 48.85, 2.35);

// Format sunrise time from the DateTime object
$sunTimes = $sunCalc->getSunTimes();
$sunriseStr = $sunTimes['sunrise']->format('H:i');

// Get position of the sun (azimuth and altitude) at today's sunrise
$sunrisePos = $sunCalc->getPosition($sunTimes['sunrise']);

// Get sunrise azimuth in degrees
$sunriseAzimuth = $sunrisePos->azimuth * 180 / M_PI;
```

## Reference

### Sunlight times

```php
SunCalc::getSunTimes()
```

Returns an array with the following indexes (each is a `DateTime` object):

| Property        | Description                                                              |
| --------------- | ------------------------------------------------------------------------ |
| `sunrise`       | sunrise (top edge of the sun appears on the horizon)                     |
| `sunriseEnd`    | sunrise ends (bottom edge of the sun touches the horizon)                |
| `goldenHourEnd` | morning golden hour (soft light, best time for photography) ends         |
| `solarNoon`     | solar noon (sun is in the highest position)                              |
| `goldenHour`    | evening golden hour starts                                               |
| `sunsetStart`   | sunset starts (bottom edge of the sun touches the horizon)               |
| `sunset`        | sunset (sun disappears below the horizon, evening civil twilight starts) |
| `dusk`          | dusk (evening nautical twilight starts)                                  |
| `nauticalDusk`  | nautical dusk (evening astronomical twilight starts)                     |
| `night`         | night starts (dark enough for astronomical observations)                 |
| `nadir`         | nadir (darkest moment of the night, sun is in the lowest position)       |
| `nightEnd`      | night ends (morning astronomical twilight starts)                        |
| `nauticalDawn`  | nautical dawn (morning nautical twilight starts)                         |
| `dawn`          | dawn (morning nautical twilight ends, morning civil twilight starts)     |

`SunCalc::times` property contains all currently defined times.


### Sun position

```php
SunCalc::getSunPosition(/*DateTime*/ $timeAndDate)
```

Returns an object with the following properties:

 * `altitude`: sun altitude above the horizon in radians,
 e.g. `0` at the horizon and `PI/2` at the zenith (straight over your head)
 * `azimuth`: sun azimuth in radians (direction along the horizon, measured from south to west),
 e.g. `0` is south and `M_PI * 3/4` is northwest


### Moon position

```php
SunCalc::getMoonPosition(/*DateTime*/ $timeAndDate)
```

Returns an object with the following properties:

 * `altitude`: moon altitude above the horizon in radians
 * `azimuth`: moon azimuth in radians
 * `distance`: distance to moon in kilometers


### Moon illumination

```php
SunCalc::getMoonIllumination()
```

Returns an array with the following properties:

 * `fraction`: illuminated fraction of the moon; varies from `0.0` (new moon) to `1.0` (full moon)
 * `phase`: moon phase; varies from `0.0` to `1.0`, described below
 * `angle`: midpoint angle in radians of the illuminated limb of the moon reckoned eastward from the north point of the disk;
 the moon is waxing if the angle is negative, and waning if positive

Moon phase value should be interpreted like this:

| Phase | Name            |
| -----:| --------------- |
| 0     | New Moon        |
|       | Waxing Crescent |
| 0.25  | First Quarter   |
|       | Waxing Gibbous  |
| 0.5   | Full Moon       |
|       | Waning Gibbous  |
| 0.75  | Last Quarter    |
|       | Waning Crescent |

### Moon rise and set times

```php
SunCalc::getMoonTimes($inUTC)
```

Returns an object with the following indexes:

 * `rise`: moonrise time as `DateTime`
 * `set`: moonset time as `DateTime`
 * `alwaysUp`: `true` if the moon never rises/sets and is always _above_ the horizon during the day
 * `alwaysDown`: `true` if the moon is always _below_ the horizon

By default, it will search for moon rise and set during local user's day (from 0 to 24 hours).
If `$inUTC` is set to true, it will instead search the specified date from 0 to 24 UTC hours.

## What changed in this fork

- Upgraded the codebase to require PHP 8.0 or newer and leverage modern language features such as typed properties and strict typing.
- Published under the package name `tuxonice/suncalc-php` with PSR-4 autoloading for the `Tlab\\SunCalc\\` namespace.
- Added tooling for development (PHPUnit, PHP_CodeSniffer, PHPStan) to keep the implementation consistent and reliable.

## Development

1. Install dependencies:
   ```bash
   composer install
   ```
2. Run the test suite:
   ```bash
   vendor/bin/phpunit
   ```
3. (Optional) Run static analysis and coding standards:
   ```bash
   vendor/bin/phpstan analyse
   vendor/bin/phpcs
   ```

## Credits

- Original JavaScript algorithm by [Vladimir Agafonkin](https://github.com/mourner)
- Original PHP port by [Greg Seth](https://github.com/gregseth)
- PHP 8 refactor and Composer package by [Helder Correia](https://github.com/tuxonice)

## License

Released under the GPLv2 license. See the [LICENSE](LICENSE) file for details.
