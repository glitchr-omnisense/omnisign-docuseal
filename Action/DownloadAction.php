<?php

namespace Omnisign\Docuseal\Action;

use Omnisign\Exception\ProviderException;
use Omnisign\Model\Download;
use Omnisign\Model\File;
use Omnisign\Request\Download as DownloadRequest;
use Omnisign\Request\Request;

/**
 * GET /submissions/{id}: once completed, its documents' URLs and the audit
 * log's - each fetched.
 */
final class DownloadAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof DownloadRequest;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof DownloadRequest);
        $submission = $this->api->json('GET', '/submissions/'.$request->envelope->reference());
        if ('completed' !== ($submission['status'] ?? null)) {
            throw new ProviderException('docuseal', \sprintf('The submission is %s: its signed documents come once it is completed.', $submission['status'] ?? 'not readable'));
        }
        $documents = [];
        foreach ((array) ($submission['documents'] ?? []) as $document) {
            $name = (string) ($document['name'] ?? 'signed');
            $documents[] = new File($this->api->fetchFile((string) $document['url']), str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf');
        }
        $request->setResult(new Download($documents, isset($submission['audit_log_url']) ? new File($this->api->fetchFile((string) $submission['audit_log_url']), 'audit-log.pdf') : null));
    }
}
