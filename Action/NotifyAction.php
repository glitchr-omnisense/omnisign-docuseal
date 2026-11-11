<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Docuseal\Data;
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
        $read = Data::of($payload);
        $type = $read->text('event_type');
        $data = Data::of((array) ($payload['data'] ?? []));
        $form = str_starts_with($type, 'form.');
        $request->setResult(new Notification(
            self::EVENTS[$type] ?? Event::OTHER,
            $type,
            $form ? ($data->textOrNull('submission_id') ?? $data->data('submission')->textOrNull('id')) : $data->textOrNull('id'),
            $form ? ($data->textOrNull('external_id') ?? $data->textOrNull('id')) : null,
            $read->moment('timestamp'),
            $type.'@'.$data->text('id').'@'.$read->text('timestamp'),
            $payload,
        ));
    }
}
