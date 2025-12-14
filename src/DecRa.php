<?php

declare(strict_types=1);

namespace Tlab\SunCalc;

class DecRa
{
    public float $dec;
    public float $ra;

    public function __construct(float $d, float $r)
    {
        $this->dec = $d;
        $this->ra = $r;
    }
}
