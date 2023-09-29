<?php

namespace DvsaTest\LaminasConfigCloudParameters\Unit\ParameterProvider\Aws;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterProviderException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\SecretsManager;
use PHPUnit\Framework\TestCase;

class SecretsManagerTest extends TestCase
{
    public function testThrowsLibraryException(): void
    {
        $this->expectException(ParameterProviderException::class);

        $secretsManagerClient = $this->getMockBuilder(SecretsManagerClient::class)->disableOriginalConstructor()->addMethods(['getSecretValue'])->getMock();
        $secretsManagerClient->method('getSecretValue')->willThrowException(new AwsException('AWS_EXCEPTION', new Command('GetSecretValue')));

        $secretsManager = new SecretsManager($secretsManagerClient);
        $secretsManager('ID');
    }

    public function testStringSecretReturned(): void
    {
        $secretsManagerClient = $this->getMockBuilder(SecretsManagerClient::class)->disableOriginalConstructor()->addMethods(['getSecretValue'])->getMock();
        $secretsManagerClient->method('getSecretValue')->willReturn([
            'SecretString' => '{"foo":"bar"}',
        ]);

        $secretsManager = new SecretsManager($secretsManagerClient);
        $this->assertEquals(['foo' => 'bar'], $secretsManager('ID'));
    }

    public function testBinaryStringSecretReturned(): void
    {
        $secretsManagerClient = $this->getMockBuilder(SecretsManagerClient::class)->disableOriginalConstructor()->addMethods(['getSecretValue'])->getMock();
        $secretsManagerClient->method('getSecretValue')->willReturn([
            'SecretBinary' => base64_encode('{"foo":"bar"}'),
        ]);

        $secretsManager = new SecretsManager($secretsManagerClient);
        $this->assertEquals(['foo' => 'bar'], $secretsManager('ID'));
    }
}
