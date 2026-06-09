<?php

declare(strict_types=1);

namespace DvsaTest\LaminasConfigCloudParameters\Functional;

use Aws\MockHandler;
use Aws\Result;
use Dvsa\LaminasConfigCloudParameters\Cast\Boolean;
use Dvsa\LaminasConfigCloudParameters\Cast\Integer;
use Dvsa\LaminasConfigCloudParameters\Exception\ParameterNotFoundException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\ParameterStore;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\Aws\SecretsManager;
use Laminas\ModuleManager\Listener\ConfigListener;
use Laminas\ModuleManager\Listener\InitTrigger;
use Laminas\ModuleManager\Listener\ModuleResolverListener;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
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

        $config = $this->loadMergedConfig($config);

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

        $this->loadMergedConfig($config);
    }

    /**
     * Exercises the module's config-merge hook by driving the ModuleManager and
     * ConfigListener directly, without booting a full MVC Application. Returns
     * the fully merged and processed config — equivalent to what an MVC
     * application would expose via Application::getConfig().
     *
     * @param array<string, mixed> $moduleConfig
     *
     * @return array<string, mixed>
     */
    protected function loadMergedConfig(array $moduleConfig): array
    {
        $moduleManager = new ModuleManager([
            'Dvsa\LaminasConfigCloudParameters',
        ]);

        $events = $moduleManager->getEventManager();

        // Minimal listener set: resolve the module name to its Module class,
        // call Module::init() (which registers Module::onMergeConfig), and
        // collect/merge module config.
        $events->attach(ModuleEvent::EVENT_LOAD_MODULE_RESOLVE, new ModuleResolverListener());
        $events->attach(ModuleEvent::EVENT_LOAD_MODULE, new InitTrigger());

        $configListener = new ConfigListener();
        $configListener->attach($events);

        // Inject the test config before the module processes it. Attaching here,
        // ahead of loadModules(), keeps this listener before Module::onMergeConfig,
        // which is registered later during the module's init().
        $events->attach(
            ModuleEvent::EVENT_MERGE_CONFIG,
            function (ModuleEvent $e) use ($moduleConfig): void {
                $configListener = $e->getConfigListener();
                $config = $configListener->getMergedConfig(false);

                $config = array_merge_recursive($config, $moduleConfig);

                $configListener->setMergedConfig($config);
            }
        );

        $moduleManager->loadModules();

        /**
         * @var array<string, mixed> $config
         */
        $config = $configListener->getMergedConfig(false);

        return $config;
    }
}
