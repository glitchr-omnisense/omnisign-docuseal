<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Model\SignerStatus;
use Omnisign\Request\Remind;
use Omnisign\Request\Request;

/** PUT /submitters/{id}, send_email: true - for each signer who has not signed. */
final class RemindAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Remind;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Remind);
        foreach ($request->envelope->states as $key => $state) {
            if ((null === $request->signer || $request->signer === $key) && null !== $state->reference && !\in_array($state->status, [SignerStatus::SIGNED, SignerStatus::DECLINED], true)) {
                $this->api->json('PUT', '/submitters/'.$state->reference, ['send_email' => true]);
            }
        }
        $request->setResult($request->envelope);
    }
}
