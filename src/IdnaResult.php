<?php

declare(strict_types=1);

namespace Rowbot\Idna;

class IdnaResult
{
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var int
     */
    protected $errors;

    /**
     * @var bool
     */
    protected $transitionalDifferent;

    public function __construct(Domain $domain)
    {
        $this->domain = $domain->toString();
        $this->errors = $domain->getErrors();
        $this->transitionalDifferent = $domain->isTransitionalDifferent();
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getErrors(): int
    {
        return $this->errors;
    }

    public function hasError(int $error): bool
    {
        return ($this->errors & $error) !== 0;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== 0;
    }

    public function isTransitionalDifferent(): bool
    {
        return $this->transitionalDifferent;
    }
}
