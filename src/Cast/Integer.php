<?php

namespace Dvsa\LaminasConfigCloudParameters\Cast;

class Integer implements CastInterface
{
    #[\Override]
    public function __invoke(string $value): int
    {
        return intval($value);
    }
}
