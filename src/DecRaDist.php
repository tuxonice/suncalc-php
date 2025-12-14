<?php

declare(strict_types=1);

namespace Tlab\SunCalc;

class DecRaDist extends DecRa
{
    public float $dist;

    public function __construct(float $d, float $r, float $dist)
    {
        parent::__construct($d, $r);
        $this->dist = $dist;
    }
}
