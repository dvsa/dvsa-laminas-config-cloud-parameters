<?php

namespace DvsaTest\LaminasConfigCloudParameters\Functional;

use Aws\MockHandler;
use Aws\Result;
use Dvsa\LaminasConfigCloudParameters\Cast\Boolean;
use Dvsa\LaminasConfigCloudParameters\Cast\Integer;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterNotFoundException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\ParameterStore;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\SecretsManager;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Application;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-api
 */
class ModuleTest extends TestCase
{
    public function testProcessParameters(): void
    {
        $mock = new MockHandler();

        $mock->append(new Result([
            'SecretString' => json_encode(['SECRET_VALUE_1' => 'secret'])
        ]));

        $mock->append(
            new Result([
                'Parameters' => [
                    [
                        'Name' => '/EXAMPLE/PATH/PARAMETER_VALUE_1',
                        'Value' => 'parameter',
                    ],
                    [
                        'Name' => '/EXAMPLE/PATH/PARAMETER_VALUE_2',
                        'Value' => 'TRUE',
                    ],
                    [
                        'Name' => '/EXAMPLE/PATH/PARAMETER_VALUE_3',
                        'Value' => '42',
                    ]
                ]
            ])
        );

        $config = [
            'aws' => [
                'global' => [
                    'handler' => $mock,
                    'credentials' => false,
                ],
            ],
            'config_parameters' => [
                'providers' => [
                    SecretsManager::class => [
                        'SECRET_KEY_1',
                    ],
                    ParameterStore::class => [
                        '/EXAMPLE/PATH',
                    ],
                ],
                'casts' => [
                    '[parameter_2]' => Boolean::class,
                    '[parameter_3][nested][deep]' => new Integer(),
                ],
            ],
            'secret' => '%SECRET_VALUE_1%',
            'parameter' => '%PARAMETER_VALUE_1%',
            'parameter_2' => '%PARAMETER_VALUE_2%',
            'parameter_3' => [
                'nested' => [
                    'deep' => '%PARAMETER_VALUE_3%',
                ],
            ],
        ];

        $application = $this->createApplication($config);

        /**
         * @var array<string, mixed> $config
         */
        $config = $application->getConfig();

        $this->assertIsArray($config);

        $this->assertSame('secret', $config['secret'] ?? null);
        $this->assertSame('parameter', $config['parameter'] ?? null);
        $this->assertSame(true, $config['parameter_2'] ?? null);
        $this->assertSame(42, $config['parameter_3']['nested']['deep'] ?? null);
    }

    public function testMissingParametersThrowException(): void
    {
        $this->expectException(ParameterNotFoundException::class);

        $mock = new MockHandler();

        $mock->append(new Result([
            'SecretString' => json_encode(['SECRET_VALUE_1' => 'secret'])
        ]));

        $config = [
            'aws' => [
                'global' => [
                    'handler' => $mock,
                    'credentials' => false,
                ],
            ],
            'config_parameters' => [
                'providers' => [
                    SecretsManager::class => [
                        'SECRET_KEY_1',
                    ],
                ],
            ],
            'parameter' => '%PARAMETER_VALUE_1%',
        ];

        $application = $this->createApplication($config);

        $application->getConfig();
    }

    /**
     * @param array<string, mixed> $moduleConfig
     */
    protected function createApplication(array $moduleConfig): Application
    {
        $configuration = [
            'modules' => [
                'Dvsa\LaminasConfigCloudParameters',
            ],
            'module_listener_options' => [],
        ];

        $smConfig = new ServiceManagerConfig([]);
        $serviceManager = new ServiceManager();
        $smConfig->configureServiceManager($serviceManager);
        $serviceManager->setService('ApplicationConfig', $configuration);

        /**
         * @var ModuleManager $moduleManager
         */
        $moduleManager = $serviceManager->get('ModuleManager');

        $moduleManager->getEventManager()->attach(
            ModuleEvent::EVENT_MERGE_CONFIG,
            function (ModuleEvent $e) use ($moduleConfig) {
                $configListener = $e->getConfigListener();
                $config = $configListener->getMergedConfig(false);

                $config = array_merge_recursive($config, $moduleConfig);

                $configListener->setMergedConfig($config);
            }
        );

        $moduleManager->loadModules();

        return $serviceManager->get('Application');
    }
}
