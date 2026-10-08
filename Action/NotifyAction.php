<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Model\Event;
use Omnisign\Model\Notification;
use Omnisign\Request\Notify;
use Omnisign\Request\Request;

/** A webhook's call: X-Docuseal-Signature checked, event_type read. */
final class NotifyAction extends AbstractAction
{
    private const EVENTS = [
        'submission.created' => Event::SENT, 'form.viewed' => Event::OPENED, 'form.started' => Event::OPENED,
        'form.completed' => Event::SIGNED, 'form.declined' => Event::DECLINED, 'submission.completed' => Event::COMPLETED,
        'submission.expired' => Event::EXPIRED, 'submission.archived' => Event::CANCELED,
    ];

    public function supports(Request $request): bool
    {
        return $request instanceof Notify;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Notify);
        $payload = $this->api->verify($request->body, $request->header('X-Docuseal-Signature'));
        $type = (string) ($payload['event_type'] ?? '');
        $data = (array) ($payload['data'] ?? []);
        $form = str_starts_with($type, 'form.');
        $request->setResult(new Notification(
            self::EVENTS[$type] ?? Event::OTHER,
            $type,
            null !== ($reference = $form ? ($data['submission_id'] ?? $data['submission']['id'] ?? null) : ($data['id'] ?? null)) ? (string) $reference : null,
            $form ? ($data['external_id'] ?? (isset($data['id']) ? (string) $data['id'] : null)) : null,
            isset($payload['timestamp']) ? new \DateTimeImmutable((string) $payload['timestamp']) : null,
            $type.'@'.($data['id'] ?? '').'@'.($payload['timestamp'] ?? ''),
            $payload,
        ));
    }
}
