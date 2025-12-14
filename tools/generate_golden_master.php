#!/usr/bin/env php
<?php
declare(strict_types=1);


require('suncalc.php');

use AurorasLive\SunCalc;


final class GoldenMasterGenerator
{
    private DateTimeZone $utc;

    public function __construct()
    {
        $this->utc = new DateTimeZone('UTC');
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function generate(): array
    {
        $cases = [];
        foreach ($this->locations() as $loc) {
            foreach ($this->dates() as $dateUtc) {
                $input = [
                    'name' => $loc['name'],
                    'date' => $dateUtc->format('Y-m-d\TH:i:s\Z'),
                    'lat'  => $loc['lat'],
                    'lng'  => $loc['lng'],
                ];

                $output = $this->callOriginal(
                    $dateUtc,
                    (float)$loc['lat'],
                    (float)$loc['lng']
                );

                $cases[] = [
                    'input'  => $input,
                    'output' => $this->normalize($output),
                ];
            }
        }

        return [
            'meta' => [
                'generated_at' => (new DateTimeImmutable('now', $this->utc))->format('c'),
                'php' => PHP_VERSION,
            ],
            'cases' => $cases,
        ];
    }

    /**
     * @return array<int, array{name:string,lat:float,lng:float}>
     */
    private function locations(): array
    {
        return [
            ['name' => 'Paris',        'lat' => 48.85,   'lng' => 2.35],
            ['name' => 'Lisbon',       'lat' => 38.7223, 'lng' => -9.1393],
            ['name' => 'NewYork',      'lat' => 40.7128, 'lng' => -74.0060],
            ['name' => 'Quito',        'lat' => -0.1807, 'lng' => -78.4678],
            ['name' => 'Sydney',       'lat' => -33.8688,'lng' => 151.2093],
            ['name' => 'Tromso',       'lat' => 69.6492, 'lng' => 18.9553], // hard cases
            ['name' => 'NearDateline', 'lat' => 0.0,     'lng' => 179.9],
            ['name' => 'NearPole',     'lat' => 89.9,    'lng' => 0.0],
        ];
    }

    /**
     * @return DateTimeImmutable[]
     * @throws Exception
     */
    private function dates(): array
    {
        // A mix of “interesting” days + monthly samples.
        $days = [
            '2024-02-29', // leap day
            '2025-03-30', // DST-ish in many regions (still useful in UTC)
            '2025-06-21', // solstice
            '2025-09-22', // equinox-ish
            '2025-12-21', // solstice
        ];
        for ($m = 1; $m <= 12; $m++) {
            $days[] = sprintf('2025-%02d-01', $m);
            $days[] = sprintf('2025-%02d-10', $m);
            $days[] = sprintf('2025-%02d-15', $m);
            $days[] = sprintf('2025-%02d-20', $m);
        }
        $days = array_values(array_unique($days));

        $out = [];
        foreach ($days as $d) {
            $out[] = new DateTimeImmutable($d . 'T00:00:00Z', $this->utc);
        }
        return $out;
    }

    /**
     * Calls the *original* implementation.
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    private function callOriginal(DateTimeImmutable $dateUtc, float $lat, float $lng): array
    {
        // IMPORTANT: SunCalc uses DateTime; we pass an immutable converted to DateTime.
        $date = new DateTimeImmutable($dateUtc->format('c'), new DateTimeZone('UTC'));
        $dateMutable = \DateTime::createFromInterface($date);

        // If your class is namespaced later, update this.
        $sunCalc = new SunCalc($dateMutable, $lat, $lng);

        // Based on your snippet:
        $sunTimes = $sunCalc->getSunTimes();
        $sunPositions = $sunCalc->getSunPosition();
        $moonPositions = $sunCalc->getMoonPosition($dateMutable);
        $moonIllumination = $sunCalc->getMoonIllumination();

        return [
            'sunTimes' => $sunTimes,
            'sunPosition' => $sunPositions,
            'moonPosition' => $moonPositions,
            'moonIllumination' => $moonIllumination,
        ];
    }

    /**
     * Normalize to stable JSON-friendly values:
     * - DateTimeInterface => ISO8601 Z
     * - arrays recursively
     *
     * @param mixed $v
     * @return mixed
     */
    private function normalize($v)
    {
        if ($v instanceof DateTimeInterface) {
            // Force UTC + stable format
            $dt = DateTimeImmutable::createFromInterface($v)->setTimezone($this->utc);
            return $dt->format('Y-m-d\TH:i:s\Z');
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $vv) {
                $out[$k] = $this->normalize($vv);
            }
            return $out;
        }
        return $v;
    }
}

$outFile = __DIR__.'/../tests/fixtures/golden-master.json';

$gen = new GoldenMasterGenerator();
$data = $gen->generate();
file_put_contents($outFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
