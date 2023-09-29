<?php

namespace DvsaTest\LaminasConfigCloudParameters\Unit\ParameterProvider\Aws;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Ssm\SsmClient;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterProviderException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\ParameterStore;
use PHPUnit\Framework\TestCase;

class ParameterStoreTest extends TestCase
{
    public function testThrowsLibraryException(): void
    {
        $this->expectException(ParameterProviderException::class);

        $ssmClient = $this->createMock(SsmClient::class);
        $ssmClient->method('getPaginator')->willThrowException(new AwsException('AWS_EXCEPTION', new Command('GetParametersByPath')));

        $parameterStore = new ParameterStore($ssmClient);
        $parameterStore('ID');
    }

    public function testReturnsParameter(): void
    {
        $parameterStoreClient = $this->createMock(SsmClient::class);
        $parameterStoreClient->method('getPaginator')->willReturn([[
        'Parameters' => [
        [
          'Name' => '/EXAMPLE/PARAMETER/ID',
          'Value' => 'EXAMPLE_VALUE',
        ],
        ],
        ]]);

        $parameterStore = new ParameterStore($parameterStoreClient);
        $this->assertEquals(['ID' => 'EXAMPLE_VALUE'], $parameterStore('/EXAMPLE/PARAMETER'));
    }
}
