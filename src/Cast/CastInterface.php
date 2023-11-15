<?php

namespace Dvsa\LaminasConfigCloudParameters\Cast;

interface CastInterface
{
    public function __invoke(string $value);
}
