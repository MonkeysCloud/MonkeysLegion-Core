<?php

namespace MonkeysLegion\Core\Logger;

use MonkeysLegion\Core\Contracts\FrameworkLoggerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Stringable;

/**
 * A logger that adapts its logging behavior based on the application's environment.
 *
 * @deprecated use MonkeysLegion\Logger package instead.
 */
class MonkeyLogger implements FrameworkLoggerInterface
{
    private LoggerInterface $logger;
    private string $env;

    /** @var array<string> */
    private array $validLevels = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG
    ];

    public function __construct(?LoggerInterface $logger = null, ?string $env = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->env = strtolower($env ?? 'dev');
    }

    /**
     * Set the underlying logger instance.
     *
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Set the current environment.
     *
     * @param string $env
     * @return $this
     */
    public function setEnvironment(string $env): self
    {
        $this->env = strtolower($env);
        return $this;
    }

    /**
     * Get the underlying logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get the current environment.
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->env;
    }

    /**
     * Log a message at the appropriate level based on the environment.
     *
     * @param string $message The log message.
     * @param array<mixed> $context The log context.
     */
    public function smartLog(string|Stringable $message, array $context = []): void
    {
        $context = $this->enrichContext($context);

        switch ($this->env) {
            case 'production':
            case 'prod':
                $this->logger->info($message, $context); // stable flow logs
                break;

            case 'staging':
            case 'preprod':
                $this->logger->notice($message, $context); // mild visibility
                break;

            case 'testing':
            case 'test':
                $this->logger->warning($message, $context); // catches more attention in CI
                break;

            case 'development':
            case 'dev':
            default:
                $this->logger->debug($message, $context); // full verbosity
                break;
        }
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logger->emergency($message, $this->enrichContext($context));
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logger->alert($message, $this->enrichContext($context));
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logger->critical($message, $this->enrichContext($context));
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $this->enrichContext($context));
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $this->enrichContext($context));
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logger->notice($message, $this->enrichContext($context));
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logger->info($message, $this->enrichContext($context));
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $this->enrichContext($context));
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!in_array($level, $this->validLevels, true)) {
            throw new \InvalidArgumentException("Invalid log level: " . var_export($level, true));
        }
        $this->logger->log($level, $message, $this->enrichContext($context));
    }

    /**
     * @param array<mixed> $context
     * @return array<mixed>
     */
    private function enrichContext(array $context = []): array
    {
        // TODO: Add more useful info like request ID, App name/version, etc.
        return array_merge(['env' => $this->env], $context);
    }
}
