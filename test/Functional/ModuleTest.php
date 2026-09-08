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
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException as SymfonyParameterNotFoundException;
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
     * Symfony names the first parameter it cannot resolve and stops there. What a developer
     * needs is where the placeholder is, so they know which key to set or override.
     */
    public function testMissingParameterMessageNamesEveryConfigKeyReferencingIt(): void
    {
        $config = [
            'config_parameters' => ['providers' => [], 'casts' => []],
            'awsOptions' => ['region' => 'eu-west-1', 'proxy' => 'http://%shd_proxy%'],
            'companies_house_connection' => ['proxy' => '%shd_proxy%'],
        ];

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertStringContainsString(
                '"shd_proxy" referenced by awsOptions.proxy, companies_house_connection.proxy',
                $e->getMessage()
            );
        }
    }

    /** All of them, not just the one Symfony happened to reach first. */
    public function testMissingParameterMessageReportsEveryUnresolvedParameter(): void
    {
        $config = [
            'config_parameters' => ['providers' => [], 'casts' => []],
            'mail' => ['dsn' => '%olcs_notify_dsn%'],
            'awsOptions' => ['proxy' => 'http://%shd_proxy%'],
        ];

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertStringContainsString('No provider supplied 2 config parameters:', $e->getMessage());
            $this->assertStringContainsString('"olcs_notify_dsn" referenced by mail.dsn', $e->getMessage());
            $this->assertStringContainsString('"shd_proxy" referenced by awsOptions.proxy', $e->getMessage());
        }
    }

    /**
     * The common local-development case: no providers, so nothing can resolve and the fix is
     * to supply or remove the key rather than to hunt for a provider that is misbehaving.
     */
    public function testMissingParameterMessageExplainsWhenNoProvidersAreConfigured(): void
    {
        $config = [
            'config_parameters' => ['providers' => [], 'casts' => []],
            'awsOptions' => ['proxy' => 'http://%shd_proxy%'],
        ];

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertStringContainsString('No parameter providers are configured', $e->getMessage());
            $this->assertStringContainsString('MergeRemoveKey', $e->getMessage());
        }
    }

    /** %% is an escaped percent sign, not a reference, and must not be reported as missing. */
    public function testEscapedPercentIsNotReportedAsAMissingParameter(): void
    {
        $config = [
            'config_parameters' => ['providers' => [], 'casts' => []],
            'literal' => 'a 100%% certain literal',
            'awsOptions' => ['proxy' => 'http://%shd_proxy%'],
        ];

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertStringContainsString('No provider supplied 1 config parameter:', $e->getMessage());
            $this->assertStringNotContainsString('literal', $e->getMessage());
        }
    }

    /**
     * The message is what every consumer logs, so it carries the full detail in prose. These
     * fields are for consumers that can do more than print it - structured log fields, a
     * health check, a setup script that offers to write the missing keys.
     */
    public function testExceptionCarriesTheUnresolvedParametersAsStructuredData(): void
    {
        $config = [
            'config_parameters' => ['providers' => [], 'casts' => []],
            'mail' => ['dsn' => '%olcs_notify_dsn%'],
            'awsOptions' => ['proxy' => 'http://%shd_proxy%'],
            'companies_house_connection' => ['proxy' => '%shd_proxy%'],
        ];

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertSame(
                [
                    'olcs_notify_dsn' => ['mail.dsn'],
                    'shd_proxy' => ['awsOptions.proxy', 'companies_house_connection.proxy'],
                ],
                $e->getUnresolvedParameters()
            );
            $this->assertSame(['olcs_notify_dsn', 'shd_proxy'], $e->getUnresolvedParameterNames());
        }
    }

    /**
     * The message caps the keys it lists so it stays readable; the structured data must not,
     * or a consumer reading it would silently act on a partial list.
     */
    public function testStructuredDataIsCompleteEvenWhenTheMessageIsTruncated(): void
    {
        $referencingKeys = [];
        for ($i = 1; $i <= 12; $i++) {
            $referencingKeys['service_' . $i] = ['endpoint' => 'https://%shd_proxy%/v1'];
        }

        $config = ['config_parameters' => ['providers' => [], 'casts' => []]] + $referencingKeys;

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertStringContainsString('(and 2 more)', $e->getMessage());
            $this->assertCount(12, $e->getUnresolvedParameters()['shd_proxy']);
        }
    }

    /** The underlying resolver failure stays reachable for anything that wants the original. */
    public function testOriginalResolverExceptionIsPreserved(): void
    {
        $config = [
            'config_parameters' => ['providers' => [], 'casts' => []],
            'awsOptions' => ['proxy' => 'http://%shd_proxy%'],
        ];

        try {
            $this->loadMergedConfig($config);
            $this->fail('Expected ' . ParameterNotFoundException::class);
        } catch (ParameterNotFoundException $e) {
            $this->assertInstanceOf(SymfonyParameterNotFoundException::class, $e->getPrevious());
        }
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
