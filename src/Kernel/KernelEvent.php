<?php

declare(strict_types=1);

namespace MonkeysLegion\Core\Kernel;

/**
 * Lifecycle events for the application kernel.
 */
enum KernelEvent: string
{
    case Booting      = 'kernel.booting';
    case Booted       = 'kernel.booted';
    case Terminating  = 'kernel.terminating';
    case Terminated   = 'kernel.terminated';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Booting     => 'Booting',
            self::Booted      => 'Booted',
            self::Terminating => 'Terminating',
            self::Terminated  => 'Terminated',
        };
    }
}
