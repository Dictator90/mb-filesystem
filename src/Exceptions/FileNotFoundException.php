<?php

namespace MB\Filesystem\Exceptions;

class FileNotFoundException extends \RuntimeException
{
    public function __construct(string $path, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("File not found: {$path}", $code, $previous);
    }
}