<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Action\ActionInterface;
use Omnisign\Action\ApiAwareInterface;
use Omnisign\Action\ApiAwareTrait;
use Omnisign\Docuseal\Api;
use Omnisign\Docuseal\Data;
use Omnisign\Model\Envelope;
use Omnisign\Model\SignerState;
use Omnisign\Model\Status;

/** An action on DocuSeal's API. */
abstract class AbstractAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * The envelope as DocuSeal answered: a submission, or the list of
     * submitters a creation gives - each signer found back by external_id
     * (their key), or by id.
     *
     * @param array<mixed> $answer
     */
    protected function read(Envelope $envelope, array $answer): Envelope
    {
        $submitters = array_is_list($answer) ? $answer : (array) ($answer['submitters'] ?? []);
        $states = $envelope->states;
        $byId = [];
        foreach ($states as $key => $state) {
            $byId[(string) $state->reference] = $key;
        }
        foreach ($submitters as $submitter) {
            $submitter = Data::of($submitter);
            $key = $submitter->get('external_id');
            $key = null !== $key && \in_array($key, array_map(static fn ($s) => $s->key, $envelope->signers), true) ? $key : ($byId[$submitter->text('id')] ?? null);
            if (null === $key) {
                continue;
            }
            $old = $states[$key] ?? new SignerState($key);
            $slug = $submitter->textOrNull('slug');
            $link = $submitter->textOrNull('embed_src') ?? (null !== $slug ? $this->api->appUrl.'/s/'.$slug : $old->link);
            $states[$key] = new SignerState($key, $submitter->textOrNull('id') ?? (string) $old->reference, Api::SIGNER_STATUSES[$submitter->text('status')] ?? $old->status, $submitter->moment('completed_at') ?? $old->signedAt, $link, null, $old->reason);
        }
        $read = Data::of($answer);
        $reference = (array_is_list($answer) ? Data::of($submitters[0] ?? [])->textOrNull('submission_id') : $read->textOrNull('id')) ?? (string) $envelope->reference;
        $status = array_is_list($answer)
            ? Status::SENT
            : (isset($answer['archived_at']) ? Status::CANCELED : (Api::STATUSES[$read->text('status')] ?? Status::UNKNOWN));

        return $envelope->with(
            reference: $reference,
            status: $status,
            states: $states,
            completedAt: array_is_list($answer) ? null : $read->moment('completed_at'),
            data: array_is_list($answer) ? ['submitters' => $answer] : Data::map($answer),
        );
    }
}
