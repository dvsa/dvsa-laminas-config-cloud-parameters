<?php

namespace DvsaTest\LaminasConfigCloudParameters\Unit\ParameterProvider\Aws;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterProviderException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\SecretsManager;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-api
 */
class SecretsManagerTest extends TestCase
{
    public function testThrowsLibraryException(): void
    {
        $this->expectException(ParameterProviderException::class);

        $secretsManagerClient = $this->createMock(SecretsManagerClient::class);
        $secretsManagerClient->method('__call')->with('getSecretValue')->willThrowException(new AwsException('AWS_EXCEPTION', new Command('GetSecretValue')));

        $secretsManager = new SecretsManager($secretsManagerClient);
        $secretsManager('ID');
    }

    public function testStringSecretReturned(): void
    {
        $secretsManagerClient = $this->createMock(SecretsManagerClient::class);
        $secretsManagerClient->method('__call')->with('getSecretValue')->willReturn([
            'SecretString' => '{"foo":"bar"}',
        ]);

        $secretsManager = new SecretsManager($secretsManagerClient);
        $this->assertEquals(['foo' => 'bar'], $secretsManager('ID'));
    }

    public function testBinaryStringSecretReturned(): void
    {
        $secretsManagerClient = $this->createMock(SecretsManagerClient::class);
        $secretsManagerClient->method('__call')->with('getSecretValue')->willReturn([
            'SecretBinary' => base64_encode('{"foo":"bar"}'),
        ]);

        $secretsManager = new SecretsManager($secretsManagerClient);
        $this->assertEquals(['foo' => 'bar'], $secretsManager('ID'));
    }
}
