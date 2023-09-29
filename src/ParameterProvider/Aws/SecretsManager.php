<?php

declare(strict_types=1);

namespace Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws;

use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterProviderException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\ParameterProviderInterface;

class SecretsManager implements ParameterProviderInterface
{
    protected SecretsManagerClient $secretsManagerClient;

    public function __construct(
        SecretsManagerClient $secretsManagerClient
    ) {
        $this->secretsManagerClient = $secretsManagerClient;
    }

    public function __invoke(string $id): array
    {
        try {
            $result = $this->secretsManagerClient->getSecretValue([
            'SecretId' => $id,
            ]);
        } catch (AwsException $e) {
            throw new ParameterProviderException($e->getMessage(), $e->getCode(), $e);
        }

        if (isset($result['SecretString'])) {
            $secret = $result['SecretString'];
        } else {
            $secret = base64_decode($result['SecretBinary']);
        }

        return json_decode($secret, true);
    }

    public static function create(array $config): self
    {
        $clientConfig = ($config['aws']['secrets_manager'] ?? []) + ($config['aws']['global'] ?? []) + [
        'version' => 'latest',
        'region' => 'eu-west-1',
        ];

        $secretsManagerClient = new SecretsManagerClient($clientConfig);

        return new self($secretsManagerClient);
    }
}
