<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (mis. presensi di luar radius, transisi status tidak sah).
 * Pesannya aman ditampilkan langsung ke pengguna.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, protected array $context = [], int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function context(): array
    {
        return $this->context;
    }
}
