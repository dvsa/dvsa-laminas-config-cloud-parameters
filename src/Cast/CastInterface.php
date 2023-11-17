<?php

namespace Dvsa\LaminasConfigCloudParameters\Cast;

interface CastInterface
{
    /**
     * @return mixed
     */
    public function __invoke(string $value);
}
