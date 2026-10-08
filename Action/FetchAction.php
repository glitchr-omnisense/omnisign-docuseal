<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Request\Fetch;
use Omnisign\Request\Request;

/** GET /submissions/{id}: its status and its submitters'. */
final class FetchAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Fetch;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Fetch);
        $request->setResult($this->read($request->envelope, $this->api->json('GET', '/submissions/'.$request->envelope->reference())));
    }
}
