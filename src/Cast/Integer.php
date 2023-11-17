<?php

namespace Dvsa\LaminasConfigCloudParameters\Cast;

class Integer implements CastInterface
{
    public function __invoke(string $value): int
    {
        return intval($value);
    }
}
