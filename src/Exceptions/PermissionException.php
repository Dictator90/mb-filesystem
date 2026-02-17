<?php

namespace MB\Filesystem\Exceptions;

class PermissionException extends \RuntimeException
{
    public function __construct(string $path, string $operation, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Permission denied: cannot {$operation} '{$path}'", $code, $previous);
    }
}