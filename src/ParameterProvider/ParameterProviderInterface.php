<?php

declare(strict_types=1);

namespace Dvsa\LaminasConfigCloudParameters\ParameterProvider;

interface ParameterProviderInterface
{
  /**
   * @return array<string, string>
   */
    public function __invoke(string $id): array;

  /**
   * @param array<string, mixed> $config
   */
    public static function create(array $config): self;
}
