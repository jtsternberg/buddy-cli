<?php

declare(strict_types=1);

namespace BuddyCli\Exceptions;

class MissingScopeException extends \RuntimeException
{
    public function __construct(string $message, private string $scope, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getScope(): string
    {
        return $this->scope;
    }
}
