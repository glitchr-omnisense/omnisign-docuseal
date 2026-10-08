<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Exception\InvalidConfigException;
use Omnisign\Model\SigningUrl;
use Omnisign\Request\Request;
use Omnisign\Request\SigningUrl as SigningUrlRequest;

/**
 * The signer's form: <app>/s/<slug> (embed_src) - a page of its own, or
 * the docuseal-form web component's src.
 */
final class SigningUrlAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof SigningUrlRequest;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof SigningUrlRequest);
        $state = $request->envelope->state($request->signer) ?? throw new InvalidConfigException(\sprintf('The signer "%s" was not created on DocuSeal with this envelope.', $request->signer));
        if (null === $state->link) {
            $submitter = $this->api->json('GET', '/submitters/'.$state->reference);
            $state = $this->read($request->envelope, [$submitter + ['external_id' => $request->signer]])->state($request->signer);
        }
        $request->setResult(new SigningUrl((string) $state?->link));
    }
}
