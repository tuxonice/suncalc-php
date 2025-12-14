<?php

declare(strict_types=1);

namespace Tlab\SunCalc;

class AzAltDist extends AzAlt
{
    public float $dist;

    public function __construct(float $az, float $alt, float $dist)
    {
        parent::__construct($az, $alt);
        $this->dist = $dist;
    }

    public function getDist(): float
    {
        return $this->dist;
    }

    public function toArray(): array
    {
        return [
            'azimuth' => $this->azimuth,
            'altitude' => $this->altitude,
            'dist' => $this->dist,
        ];
    }
}
