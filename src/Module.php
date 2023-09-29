<?php

declare(strict_types=1);

namespace Dvsa\LaminasConfigCloudParameters;

use Dvsa\LaminasConfigCloudParameters\ParameterProvider\ParameterProviderInterface;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ModuleManager\Listener\ConfigListener;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException as SymfonyParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @psalm-api
 */
class Module
{
    public function init(ModuleManager $moduleManager): void
    {
        $events = $moduleManager->getEventManager();

        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, [$this, 'onMergeConfig']);
    }

    public function onMergeConfig(ModuleEvent $e): void
    {
        $configListener = $e->getConfigListener();

        if (!$configListener instanceof ConfigListener) {
            return;
        }

        $config = $configListener->getMergedConfig(false);

        $parameters = [];

        foreach ($config['config_parameters']['providers'] as $fqcn => $ids) {
            assert(is_a($fqcn, ParameterProviderInterface::class, true));

            $provider = $fqcn::create($config);

            $parameters = array_merge($parameters, ...array_map(fn($id) => $provider($id), $ids));
        }

        $bag = new ParameterBag($parameters);

        $postProcessor = function (array $config) use ($bag) {
            try {
                $bag->resolve();

                $resolved = $bag->resolveValue($config);

                return $bag->unescapeValue($resolved);
            } catch (SymfonyParameterNotFoundException $e) {
                throw new Exception\ParameterNotFoundException($e->getMessage(), $e->getCode(), $e);
            }
        };

        $processedConfig = new ConfigAggregator([new ArrayProvider($config)], null, [$postProcessor]);

        $configListener->setMergedConfig($processedConfig->getMergedConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'config_parameters' => [
                'providers' => [],
            ]
        ];
    }
}
