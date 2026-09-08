<?php

declare(strict_types=1);

namespace Dvsa\LaminasConfigCloudParameters\Exception;

use InvalidArgumentException;
use Throwable;

class ParameterNotFoundException extends InvalidArgumentException
{
    /** @var array<string, list<string>> */
    private array $unresolvedParameters = [];

    /**
     * The message carries the same detail in prose, because that is what every consumer logs.
     * These fields are for the ones that can do more with it than print it.
     *
     * @param array<string, list<string>> $unresolvedParameters parameter name => config keys
     *                                                          referencing it
     */
    public static function forUnresolvedParameters(
        string $message,
        array $unresolvedParameters,
        int $code = 0,
        ?Throwable $previous = null
    ): self {
        $exception = new self($message, $code, $previous);
        $exception->unresolvedParameters = $unresolvedParameters;

        return $exception;
    }

    /**
     * Every parameter that could not be resolved, mapped to every config key referencing it.
     * Complete - unlike the message, which caps the keys it lists per parameter.
     *
     * Empty when the failure could not be attributed to a config key, in which case the
     * message still carries whatever the underlying resolver reported.
     *
     * @return array<string, list<string>>
     */
    public function getUnresolvedParameters(): array
    {
        return $this->unresolvedParameters;
    }

    /**
     * The names alone, for callers that only want to know what was missing.
     *
     * @return list<string>
     */
    public function getUnresolvedParameterNames(): array
    {
        return array_keys($this->unresolvedParameters);
    }
}
