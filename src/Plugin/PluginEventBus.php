<?php
declare(strict_types=1);

namespace ProjectO\Plugin;

use Throwable;

/**
 * Minimal in-process publish/subscribe bus for cross-plugin notifications.
 *
 * The core owns only the transport: plugins announce domain events by name and
 * other plugins subscribe through their manifest. No plugin references another
 * plugin's classes, so boundaries stay intact (e.g. project.foundation emits
 * "project.archived" and labor.crew reacts by withdrawing its static pages).
 *
 * Subscribers run after the emitting use case has committed. A subscriber that
 * throws is logged and skipped: a notification must never turn a successful
 * business operation into a failure, and one broken listener must not starve
 * the others.
 */
final class PluginEventBus
{
    /** @var array<string, array<int, callable>> */
    private array $listeners = [];

    public function subscribe(string $event, callable $listener): void
    {
        if ($event === '') {
            return;
        }
        $this->listeners[$event][] = $listener;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $emitted events already dispatched, guards re-entry
     */
    public function emit(string $event, array $payload = [], array &$emitted = []): void
    {
        if ($event === '' || isset($emitted[$event])) {
            return;
        }
        $emitted[$event] = true;
        foreach ($this->listeners[$event] ?? [] as $listener) {
            try {
                $listener($payload);
            } catch (Throwable $exception) {
                error_log('plugin event listener failed for ' . $event . ': ' . $exception->getMessage());
            }
        }
    }

    /** @return array<int, string> */
    public function events(): array
    {
        return array_keys($this->listeners);
    }
}
