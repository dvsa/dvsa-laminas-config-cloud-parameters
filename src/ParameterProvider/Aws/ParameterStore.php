<?php

declare(strict_types=1);

namespace Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws;

use Aws\Exception\AwsException;
use Aws\Ssm\SsmClient;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterProviderException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\ParameterProviderInterface;

class ParameterStore implements ParameterProviderInterface
{
    protected SsmClient $ssmClient;

    public function __construct(
        SsmClient $ssmClient
    ) {
        $this->ssmClient = $ssmClient;
    }

    public function __invoke(string $id): array
    {
        try {
            /**
             * @throws AwsException
             */
            $results = $this->ssmClient->getPaginator('GetParametersByPath', [
                'Path' => $id,
                'Recursive' => true,
                'WithDecryption' => true,
            ]);
        } catch (AwsException $e) {
            throw new ParameterProviderException($e->getMessage(), $e->getCode(), $e);
        }

        $parameters = [];

        foreach ($results as $result) {
            foreach ($result['Parameters'] as $parameter) {
                $parameters[$this->normaliseName($id, $parameter['Name'])] = (string)$parameter['Value'];
            }
        }

        return $parameters;
    }

    protected function normaliseName(string $path, string $name): string
    {
        return ltrim(substr($name, strlen($path)), '/');
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config): self
    {
        $clientConfig = ($config['aws']['ssm_client'] ?? []) + ($config['aws']['global'] ?? []) + [
                'version' => 'latest',
                'region' => 'eu-west-1',
            ];

        $ssmClient = new SsmClient($clientConfig);

        return new self($ssmClient);
    }
}
