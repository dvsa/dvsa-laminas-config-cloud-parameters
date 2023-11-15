<?php 

namespace Dvsa\LaminasConfigCloudParameters\Cast;

class Boolean implements CastInterface
{
    public function __invoke(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
