<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Model\Status;
use Omnisign\Request\Cancel;
use Omnisign\Request\Request;

/** DELETE /submissions/{id}: archived - nobody may sign it any more. */
final class CancelAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $this->api->json('DELETE', '/submissions/'.$request->envelope->reference());
        $request->setResult($request->envelope->with(status: Status::CANCELED));
    }
}
