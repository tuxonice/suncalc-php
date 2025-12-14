<?php

declare(strict_types=1);

namespace Tlab\SunCalc;

class AzAlt
{
    public float $azimuth;
    public float $altitude;

    public function __construct(float $az, float $alt)
    {
        $this->azimuth = $az;
        $this->altitude = $alt;
    }

    public function getAzimuth(): float
    {
        return $this->azimuth;
    }

    public function getAltitude(): float
    {
        return $this->altitude;
    }

    public function toArray(): array
    {
        return [
            'azimuth' => $this->azimuth,
            'altitude' => $this->altitude
        ];
    }
}
