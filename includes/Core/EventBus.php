<?php
/**
 * Interner Event-Dispatcher & optionale HMAC-Webhook-Brücke (z. B. für den Universal Business Hub).
 *
 * @package SocialMediaHub\Core
 */

declare(strict_types=1);

namespace SocialMediaHub\Core;

class EventBus
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    /** @var list<array{event: string, payload: array<string, mixed>, occurred_at: string}> */
    private array $dispatchedLog = [];

    public function subscribe(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Löst ein Domänen-Event aus, ruft interne Listener, WordPress-Hooks (`smh/{event}`)
     * und optional konfigurierte, HMAC-signierte Webhooks (z. B. zum Business Hub) auf.
     *
     * @param string               $event   Name des Ereignisses, z. B. `post_group.approved` oder `job.failed`.
     * @param array<string, mixed> $payload Strukturierte Ereignisdaten (niemals geheime Tokens).
     */
    public function dispatch(string $event, array $payload = []): void
    {
        $occurredAt = gmdate('c');
        $record = [
            'event'       => $event,
            'payload'     => $payload,
            'occurred_at' => $occurredAt,
        ];
        $this->dispatchedLog[] = $record;

        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload, $occurredAt);
        }

        if (function_exists('do_action')) {
            do_action('smh/' . str_replace('.', '/', $event), $payload, $occurredAt);
        }
    }

    /**
     * Erzeugt eine HMAC-SHA256-Signatur für ausgehende oder eingehende Business-Hub-Webhooks.
     *
     * @param array<string, mixed> $body
     */
    public function signWebhookPayload(array $body, string $secret, int $timestamp): string
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $json, $secret);
    }

    /**
     * Prüft die Signatur eines eingehenden Webhooks zeitkonstant (`hash_equals`).
     *
     * @param array<string, mixed> $body
     */
    public function verifyWebhookSignature(
        array $body,
        string $secret,
        int $timestamp,
        string $providedSignature,
        int $toleranceSeconds = 300
    ): bool {
        if ($secret === '' || abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }
        $expected = $this->signWebhookPayload($body, $secret, $timestamp);
        return hash_equals($expected, $providedSignature);
    }

    /**
     * @return list<array{event: string, payload: array<string, mixed>, occurred_at: string}>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedLog;
    }
}
