<?php

declare(strict_types=1);

namespace Dvsa\LaminasConfigCloudParameters;

use Dvsa\LaminasConfigCloudParameters\Exception\InvalidCastException;
use Dvsa\LaminasConfigCloudParameters\ParameterProvider\ParameterProviderInterface;
use Laminas\ModuleManager\Listener\ConfigListener;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException as SymfonyParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @psalm-api
 */
class Module
{
    /** Mirrors Symfony's own placeholder syntax: %name%, with %% an escaped percent sign. */
    private const PARAMETER_PATTERN = '/%%|%([^%\s]++)%/';

    private const MAX_REPORTED_PATHS = 10;

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

            $parameters = array_merge($parameters, ...array_map(fn(string $id) => $provider($id), $ids));
        }

        $bag = new ParameterBag($parameters);

        try {
            $bag->resolve();
            /** @var array<string, mixed> $resolved */
            $resolved = $bag->resolveValue($config);

            if (!empty($config['config_parameters']['casts'])) {
                $this->applyCasts($resolved, $config['config_parameters']['casts']);
            }

            $processedConfig = $bag->unescapeValue($resolved);
        } catch (SymfonyParameterNotFoundException $e) {
            throw new Exception\ParameterNotFoundException(
                $this->describeUnresolvedParameters($config, $bag) ?? $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $configListener->setMergedConfig($processedConfig);
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return array{config_parameters: array{providers: array<string, string[]>, casts: array<string, class-string<Cast\CastInterface>>}}
     */
    public function getConfig(): array
    {
        return [
            'config_parameters' => [
                'providers' => [],
                'casts' => [],
            ],
        ];
    }


    /**
     * Symfony reports the name of the first parameter it could not resolve and nothing else,
     * which leaves whoever hit it searching a merged config of thousands of keys to find out
     * where the placeholder lives and therefore what to set. This walks the config to say
     * where - every unresolved parameter and every key referencing it, not just the first.
     *
     * Only reached on the way to a fatal, so the cost is irrelevant, and it returns null if it
     * finds nothing so the original message is never replaced with something less useful.
     *
     * @psalm-param array<string, mixed> $config
     */
    private function describeUnresolvedParameters(array $config, ParameterBag $bag): ?string
    {
        /** @var array<string, list<string>> $unresolved */
        $unresolved = [];

        $walk = function (array $node, string $path) use (&$walk, &$unresolved, $bag): void {
            foreach ($node as $key => $value) {
                $keyPath = $path === '' ? (string) $key : $path . '.' . $key;

                if (is_array($value)) {
                    $walk($value, $keyPath);
                    continue;
                }

                if (!is_string($value) || !preg_match_all(self::PARAMETER_PATTERN, $value, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $name) {
                    // An escaped %% produces an empty capture and references nothing.
                    if ($name !== '' && !$bag->has($name) && !in_array($keyPath, $unresolved[$name] ?? [], true)) {
                        $unresolved[$name][] = $keyPath;
                    }
                }
            }
        };

        $walk($config, '');

        if ($unresolved === []) {
            return null;
        }

        ksort($unresolved);

        $lines = [];
        foreach ($unresolved as $name => $paths) {
            $shown = array_slice($paths, 0, self::MAX_REPORTED_PATHS);
            $suffix = count($paths) > self::MAX_REPORTED_PATHS
                ? sprintf(' (and %d more)', count($paths) - self::MAX_REPORTED_PATHS)
                : '';

            $lines[] = sprintf('  "%s" referenced by %s%s', $name, implode(', ', $shown), $suffix);
        }

        $summary = count($unresolved) === 1
            ? 'No provider supplied 1 config parameter:'
            : sprintf('No provider supplied %d config parameters:', count($unresolved));

        if ($config['config_parameters']['providers'] === []) {
            $lines[] = '';
            $lines[] = 'No parameter providers are configured, so no placeholder can resolve. Either'
                . ' configure config_parameters.providers, or set these keys in your local config -'
                . ' Laminas\\Stdlib\\ArrayUtils\\MergeRemoveKey removes one entirely.';
        }

        return $summary . "\n" . implode("\n", $lines);
    }

    /**
     * @psalm-param array<string, mixed> $config
     * @psalm-param array<string, class-string<Cast\CastInterface>> $casts
     */
    private function applyCasts(array &$config, array $casts): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($casts as $key => $type) {
            /** @phpstan-ignore-next-line function.alreadyNarrowedType */
            if (!is_a($type, Cast\CastInterface::class, allow_string: true)) {
                throw new InvalidCastException("Class {$type} must implement " . Cast\CastInterface::class . " interface.");
            }

            $property = $key;

            $exists = $propertyAccessor->isReadable($config, $property);

            if (!$exists) {
                continue;
            }

            $value = $propertyAccessor->getValue($config, $property);

            if (is_string($value)) {
                // @phpstan-ignore-next-line parameterByRef.type
                $propertyAccessor->setValue($config, $property, (new $type())($value));
            }
        }
    }
}
