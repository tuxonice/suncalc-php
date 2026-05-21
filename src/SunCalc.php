<?php

declare(strict_types=1);

namespace Tlab\SunCalc;

/*
 SunCalc is a PHP library for calculating sun/moon position and light phases.
 https://github.com/gregseth/suncalc-php

 Based on Vladimir Agafonkin's JavaScript library.
 https://github.com/mourner/suncalc

 Sun calculations are based on http://aa.quae.nl/en/reken/zonpositie.html
 formulas.

 Moon calculations are based on http://aa.quae.nl/en/reken/hemelpositie.html
 formulas.

 Calculations for illumination parameters of the moon are based on
 http://idlastro.gsfc.nasa.gov/ftp/pro/astro/mphase.pro formulas and Chapter 48
 of "Astronomical Algorithms" 2nd edition by Jean Meeus (Willmann-Bell,
 Richmond) 1998.

 Calculations for moon rise/set times are based on
 http://www.stargazing.net/kepler/moonrise.html article.
*/


// shortcuts for easier to read formulas
use DateTime;

define('RAD', M_PI / 180);

// general calculations for position
define('E', RAD * 23.4397); // obliquity of the Earth
define('J0', 0.0009);


class SunCalc
{
    public DateTime $date;
    public float $lat;
    public float $lng;

    // sun times configuration (angle, morning name, evening name)
    private array $times = [
        [-0.833, 'sunrise', 'sunset'],
        [-0.3, 'sunriseEnd', 'sunsetStart'],
        [-6, 'dawn', 'dusk'],
        [-12, 'nauticalDawn', 'nauticalDusk'],
        [-18, 'nightEnd', 'night'],
        [6, 'goldenHourEnd', 'goldenHour']
    ];

    public function __construct(DateTime $date, float $lat, float $lng)
    {
        $this->date = $date;
        $this->lat = $lat;
        $this->lng = $lng;
    }

    // calculates sun position for a given date and latitude/longitude
    public function getSunPosition(): AzAlt
    {

        $lw = RAD * -$this->lng;
        $phi = RAD * $this->lat;
        $d = Utils::toDays($this->date);

        $c = Utils::sunCoords($d);
        $H = Utils::siderealTime($d, $lw) - $c->ra;

        return new AzAlt(
            Utils::azimuth($H, $phi, $c->dec),
            Utils::altitude($H, $phi, $c->dec)
        );
    }

    // calculates sun times for a given date and latitude/longitude
    public function getSunTimes(): array
    {

        $lw = RAD * -$this->lng;
        $phi = RAD * $this->lat;

        $d = Utils::toDays($this->date);
        $n = Utils::julianCycle($d, $lw);
        $ds = Utils::approxTransit(0, $lw, $n);

        $M = Utils::solarMeanAnomaly($ds);
        $L = Utils::eclipticLongitude($M);
        $dec = Utils::declination($L, 0);

        $Jnoon = Utils::solarTransitJ($ds, $M, $L);

        $result = [
            'solarNoon' => Utils::fromJulian($Jnoon, $this->date),
            'nadir' => Utils::fromJulian($Jnoon - 0.5, $this->date)
        ];

        for ($i = 0, $len = count($this->times); $i < $len; $i += 1) {
            $time = $this->times[$i];

            $Jset = Utils::getSetJ($time[0] * RAD, $lw, $phi, $dec, $n, $M, $L);
            $Jrise = $Jnoon - ($Jset - $Jnoon);

            $result[$time[1]] = Utils::fromJulian($Jrise, $this->date);
            $result[$time[2]] = Utils::fromJulian($Jset, $this->date);
        }

        return $result;
    }


    public function getMoonPosition(DateTime $date): AzAltDist
    {
        $lw = RAD * -$this->lng;
        $phi = RAD * $this->lat;
        $d = Utils::toDays($date);

        $c = Utils::moonCoords($d);
        $H = Utils::siderealTime($d, $lw) - $c->ra;
        $h = Utils::altitude($H, $phi, $c->dec);

        // altitude correction for refraction
        $h = $h + RAD * 0.017 / tan($h + RAD * 10.26 / ($h + RAD * 5.10));

        return new AzAltDist(
            Utils::azimuth($H, $phi, $c->dec),
            $h,
            $c->dist
        );
    }


    public function getMoonIllumination(): array
    {

        $d = Utils::toDays($this->date);
        $s = Utils::sunCoords($d);
        $m = Utils::moonCoords($d);

        $sdist = 149598000; // distance from Earth to Sun in km

        $phi = acos(sin($s->dec) * sin($m->dec) + cos($s->dec) * cos($m->dec) * cos($s->ra - $m->ra));
        $inc = atan2($sdist * sin($phi), $m->dist - $sdist * cos($phi));
        $angle = atan2(cos($s->dec) * sin($s->ra - $m->ra), sin($s->dec) * cos($m->dec) - cos($s->dec) * sin($m->dec) * cos($s->ra - $m->ra));

        return [
            'fraction' => (1 + cos($inc)) / 2,
            'phase' => 0.5 + 0.5 * $inc * ($angle < 0 ? -1 : 1) / M_PI,
            'angle' => $angle
        ];
    }

    public function getMoonTimes(bool $inUTC = false): array
    {
        $t = clone $this->date;
        if ($inUTC) {
            $t->setTimezone(new \DateTimeZone('UTC'));
        }

        $t->setTime(0, 0, 0);

        $hc = 0.133 * RAD;
        $h0 = $this->getMoonPosition($t)->altitude - $hc;
        $rise = 0;
        $set = 0;
        $x1 = 0;
        $x2 = 0;

        // go in 2-hour chunks, each time seeing if a 3-point quadratic curve crosses zero (which means rise or set)
        for ($i = 1; $i <= 24; $i += 2) {
            $h1 = $this->getMoonPosition(Utils::hoursLater($t, $i))->altitude - $hc;
            $h2 = $this->getMoonPosition(Utils::hoursLater($t, $i + 1))->altitude - $hc;

            $a = ($h0 + $h2) / 2 - $h1;
            $b = ($h2 - $h0) / 2;
            $xe = -$b / (2 * $a);
            $ye = ($a * $xe + $b) * $xe + $h1;
            $d = $b * $b - 4 * $a * $h1;
            $roots = 0;

            if ($d >= 0) {
                $dx = sqrt($d) / (abs($a) * 2);
                $x1 = $xe - $dx;
                $x2 = $xe + $dx;
                if (abs($x1) <= 1) {
                    $roots++;
                }
                if (abs($x2) <= 1) {
                    $roots++;
                }
                if ($x1 < -1) {
                    $x1 = $x2;
                }
            }

            if ($roots === 1) {
                if ($h0 < 0) {
                    $rise = $i + $x1;
                } else {
                    $set = $i + $x1;
                }
            } else if ($roots === 2) {
                $rise = $i + ($ye < 0 ? $x2 : $x1);
                $set = $i + ($ye < 0 ? $x1 : $x2);
            }

            if ($rise != 0 && $set != 0) {
                break;
            }

            $h0 = $h2;
        }

        $result = [];

        if ($rise != 0) {
            $result['moonrise'] = Utils::hoursLater($t, $rise);
        }
        if ($set != 0) {
            $result['moonset'] = Utils::hoursLater($t, $set);
        }

        if ($rise == 0 && $set == 0) {
            $result[$ye > 0 ? 'alwaysUp' : 'alwaysDown'] = true;
        }

        return $result;
    }

    /**
     * Get all major moon phases occurring within a date period.
     *
     * @return array<int, array{phase: string, datetime: DateTime, fraction: float, phaseValue: float}>
     */
    public function getMoonPhasesForPeriod(DateTime $startDate, DateTime $endDate): array
    {
        $phases = [];
        $targetPhases = [0.0, 0.25, 0.5, 0.75];
        $phaseNames = ['New Moon', 'First Quarter', 'Full Moon', 'Last Quarter'];

        $current = clone $startDate;
        $interval = new \DateInterval('P1D');

        $prevPhase = $this->getMoonIlluminationForDate($current)['phase'];

        while ($current <= $endDate) {
            $current->add($interval);
            $currIllumination = $this->getMoonIlluminationForDate($current);
            $currPhase = $currIllumination['phase'];

            foreach ($targetPhases as $index => $targetPhase) {
                if ($this->hasPhaseCrossing($prevPhase, $currPhase, $targetPhase)) {
                    $exactDate = $this->refinePhaseDate(clone $current, $targetPhase);
                    if ($exactDate >= $startDate && $exactDate <= $endDate) {
                        $illumination = $this->getMoonIlluminationForDate($exactDate);
                        $phases[] = [
                            'phase' => $phaseNames[$index],
                            'datetime' => $exactDate,
                            'fraction' => $illumination['fraction'],
                            'phaseValue' => $targetPhase,
                        ];
                    }
                }
            }

            $prevPhase = $currPhase;
        }

        usort($phases, fn ($a, $b) => $a['datetime'] <=> $b['datetime']);

        return $phases;
    }

    /**
     * Get daily moon phase data for a number of days starting from a date.
     *
     * @return array<int, array{date: DateTime, phase: float, phaseName: string, fraction: float}>
     */
    public function getDailyMoonPhases(DateTime $startDate, int $days): array
    {
        $result = [];
        $current = clone $startDate;
        $interval = new \DateInterval('P1D');

        for ($i = 0; $i < $days; $i++) {
            $illumination = $this->getMoonIlluminationForDate($current);
            $result[] = [
                'date' => clone $current,
                'phase' => $illumination['phase'],
                'phaseName' => $this->getPhaseName($illumination['phase']),
                'fraction' => $illumination['fraction'],
            ];
            $current->add($interval);
        }

        return $result;
    }

    /**
     * Find the next occurrence of a specific moon phase.
     */
    public function findNextMoonPhase(string $phaseName, DateTime $startDate): ?DateTime
    {
        $targetPhase = $this->getPhaseValueFromName($phaseName);
        if ($targetPhase === null) {
            return null;
        }

        $current = clone $startDate;
        $interval = new \DateInterval('P1D');

        $prevPhase = $this->getMoonIlluminationForDate($current)['phase'];

        for ($i = 0; $i < 32; $i++) {
            $current->add($interval);
            $currPhase = $this->getMoonIlluminationForDate($current)['phase'];

            if ($this->hasPhaseCrossing($prevPhase, $currPhase, $targetPhase)) {
                return $this->refinePhaseDate(clone $current, $targetPhase);
            }

            $prevPhase = $currPhase;
        }

        return null;
    }

    /**
     * Get moon illumination for a specific date.
     */
    private function getMoonIlluminationForDate(DateTime $date): array
    {
        $d = Utils::toDays($date);
        $s = Utils::sunCoords($d);
        $m = Utils::moonCoords($d);

        $sdist = 149598000;

        $phi = acos(sin($s->dec) * sin($m->dec) + cos($s->dec) * cos($m->dec) * cos($s->ra - $m->ra));
        $inc = atan2($sdist * sin($phi), $m->dist - $sdist * cos($phi));
        $angle = atan2(
            cos($s->dec) * sin($s->ra - $m->ra),
            sin($s->dec) * cos($m->dec) - cos($s->dec) * sin($m->dec) * cos($s->ra - $m->ra)
        );

        return [
            'fraction' => (1 + cos($inc)) / 2,
            'phase' => 0.5 + 0.5 * $inc * ($angle < 0 ? -1 : 1) / M_PI,
        ];
    }

    /**
     * Check if a phase value was crossed between two phase values.
     */
    private function hasPhaseCrossing(float $prevPhase, float $currPhase, float $targetPhase): bool
    {
        $epsilon = 0.02;

        if (abs($currPhase - $targetPhase) < $epsilon) {
            return true;
        }

        if ($prevPhase > $currPhase) {
            $currPhase += 1.0;
        }

        $targetPhaseNext = $targetPhase + 1.0;

        return ($prevPhase <= $targetPhase && $currPhase >= $targetPhase)
            || ($prevPhase <= $targetPhaseNext && $currPhase >= $targetPhaseNext);
    }

    /**
     * Refine the exact date of a moon phase using binary search.
     */
    private function refinePhaseDate(DateTime $approxDate, float $targetPhase): DateTime
    {
        $low = clone $approxDate;
        $low->modify('-2 days');
        $high = clone $approxDate;
        $high->modify('+2 days');

        for ($i = 0; $i < 20; $i++) {
            $mid = new DateTime('@' . (($low->getTimestamp() + $high->getTimestamp()) / 2));
            $midPhase = $this->getMoonIlluminationForDate($mid)['phase'];

            $normalizedTarget = $targetPhase;
            if ($targetPhase < 0.5 && $midPhase > 0.75) {
                $normalizedTarget = $targetPhase + 1.0;
            }

            if ($midPhase < $normalizedTarget) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return new DateTime('@' . (($low->getTimestamp() + $high->getTimestamp()) / 2));
    }

    /**
     * Get phase name from phase value.
     */
    private function getPhaseName(float $phase): string
    {
        if ($phase < 0.02 || $phase > 0.98) {
            return 'New Moon';
        }
        if ($phase < 0.23) {
            return 'Waxing Crescent';
        }
        if ($phase < 0.27) {
            return 'First Quarter';
        }
        if ($phase < 0.48) {
            return 'Waxing Gibbous';
        }
        if ($phase < 0.52) {
            return 'Full Moon';
        }
        if ($phase < 0.73) {
            return 'Waning Gibbous';
        }
        if ($phase < 0.77) {
            return 'Last Quarter';
        }

        return 'Waning Crescent';
    }

    /**
     * Get phase value from phase name.
     */
    private function getPhaseValueFromName(string $phaseName): ?float
    {
        $normalized = strtolower(str_replace([' ', '-', '_'], '', $phaseName));

        return match ($normalized) {
            'new', 'newmoon' => 0.0,
            'firstquarter', 'first', 'quarter1' => 0.25,
            'full', 'fullmoon' => 0.5,
            'lastquarter', 'last', 'thirdquarter', 'quarter3' => 0.75,
            default => null,
        };
    }
}
