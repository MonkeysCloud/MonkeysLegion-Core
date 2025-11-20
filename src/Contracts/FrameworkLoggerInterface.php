<?php

namespace MonkeysLegion\Core\Contracts;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Interface FrameworkLoggerInterface
 *
 * @deprecated Use Psr\Log\LoggerInterface Or MonkeysLegion\Log\Contract\MonkeysLoggerInterface instead.
 */
interface FrameworkLoggerInterface extends LoggerInterface
{
    /**
     * Log a message at the appropriate level based on the content of the message.
     *
     * @param string $message The log message.
     * @param array<mixed> $context The log context.
     */
    public function smartLog(string|Stringable $message, array $context = []): void;

    /**
     * Set the underlying logger instance.
     *
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self;

    /**
     * Get the underlying logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface;

    /**
     * Set the current environment.
     *
     * @param string $env
     * @return $this
     */
    public function setEnvironment(string $env): self;

    /**
     * Get the current environment.
     *
     * @return string
     */
    public function getEnvironment(): string;
}
