<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Action\ActionInterface;
use Omnisign\Action\ApiAwareInterface;
use Omnisign\Action\ApiAwareTrait;
use Omnisign\Docuseal\Api;
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
            $key = $submitter['external_id'] ?? null;
            $key = null !== $key && \in_array($key, array_map(static fn ($s) => $s->key, $envelope->signers), true) ? $key : ($byId[(string) ($submitter['id'] ?? '')] ?? null);
            if (null === $key) {
                continue;
            }
            $old = $states[$key] ?? new SignerState($key);
            $link = $submitter['embed_src'] ?? (isset($submitter['slug']) ? $this->api->appUrl.'/s/'.$submitter['slug'] : $old->link);
            $states[$key] = new SignerState($key, (string) ($submitter['id'] ?? $old->reference), Api::SIGNER_STATUSES[$submitter['status'] ?? ''] ?? $old->status, isset($submitter['completed_at']) ? new \DateTimeImmutable($submitter['completed_at']) : $old->signedAt, $link, null, $old->reason);
        }
        $first = $submitters[0] ?? [];
        $reference = array_is_list($answer) ? (string) ($first['submission_id'] ?? $envelope->reference) : (string) ($answer['id'] ?? $envelope->reference);
        $status = array_is_list($answer)
            ? Status::SENT
            : (isset($answer['archived_at']) ? Status::CANCELED : (Api::STATUSES[$answer['status'] ?? ''] ?? Status::UNKNOWN));

        return $envelope->with(
            reference: $reference,
            status: $status,
            states: $states,
            completedAt: !array_is_list($answer) && isset($answer['completed_at']) ? new \DateTimeImmutable($answer['completed_at']) : null,
            data: array_is_list($answer) ? ['submitters' => $answer] : $answer,
        );
    }
}
