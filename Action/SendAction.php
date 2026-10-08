<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Request\Request;
use Omnisign\Request\Send;

/** DocuSeal sends at creation: Send reads the submission back. */
final class SendAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Send;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Send);
        $request->setResult($this->read($request->envelope, $this->api->json('GET', '/submissions/'.$request->envelope->reference())));
    }
}
