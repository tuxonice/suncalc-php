<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tlab\SunCalc\SunCalc;

final class GoldenMasterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/fixtures/golden-master.json';

    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        $json = file_get_contents(self::FIXTURE);
        $this->fixture = json_decode($json ?: 'null', true, flags: JSON_THROW_ON_ERROR);
    }

    public function testGoldenMaster(): void
    {
        foreach ($this->fixture['cases'] as $i => $case) {
            $in = $case['input'];
            $expected = $case['output'];

            $dateUtc = new DateTimeImmutable($in['date']); // already Z
            $lat = (float)$in['lat'];
            $lng = (float)$in['lng'];

            $actual = $this->callRefactored($dateUtc, $lat, $lng);
            $actualNorm = $this->normalize($actual);

            $this->assertClose($expected, $actualNorm, "Case #$i ({$in['name']} {$in['date']})");
        }
    }

    /** @return array<string, mixed> */
    private function callRefactored(DateTimeImmutable $dateUtc, float $lat, float $lng): array
    {
        $date = \DateTime::createFromInterface($dateUtc);

        $sunCalc = new SunCalc($date, $lat, $lng);

        return [
            'sunTimes' => $sunCalc->getSunTimes(),
            'sunPosition' => $sunCalc->getSunPosition()->toArray(),
            'moonPosition' => $sunCalc->getMoonPosition($date)->toArray(),
            'moonIllumination' => $sunCalc->getMoonIllumination(),
        ];
    }

    /** @param mixed $v @return mixed */
    private function normalize($v)
    {
        if ($v instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($v)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $vv) $out[$k] = $this->normalize($vv);
            return $out;
        }
        return $v;
    }

    /**
     * Deep compare with tolerances:
     * - timestamps: <= 1s
     * - floats: delta 1e-10
     *
     * @param mixed $expected
     * @param mixed $actual
     */
    private function assertClose($expected, $actual, string $ctx): void
    {
        if (is_string($expected) && $this->isUtcIso($expected) && is_string($actual) && $this->isUtcIso($actual)) {
            $e = new DateTimeImmutable($expected);
            $a = new DateTimeImmutable($actual);
            $diff = abs($e->getTimestamp() - $a->getTimestamp());
            $this->assertLessThanOrEqual(1, $diff, "$ctx (timestamp diff {$diff}s)");
            return;
        }

        if (is_float($expected) && is_float($actual)) {
            $this->assertEqualsWithDelta($expected, $actual, 1e-10, $ctx);
            return;
        }

        if (is_int($expected) && is_int($actual)) {
            $this->assertSame($expected, $actual, $ctx);
            return;
        }

        if (is_array($expected) && is_array($actual)) {
            $this->assertSame(array_keys($expected), array_keys($actual), "$ctx (keys)");
            foreach ($expected as $k => $v) {
                $this->assertClose($v, $actual[$k], "$ctx (.$k)");
            }
            return;
        }

        $this->assertSame($expected, $actual, $ctx);
    }

    private function isUtcIso(string $s): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $s);
    }
}
