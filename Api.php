<?php

namespace Omnisign\Docuseal;

use Omnisign\Exception\InvalidKeyException;
use Omnisign\Exception\InvalidNotificationException;
use Omnisign\Exception\ProviderException;
use Omnisign\Http\Answer;
use Omnisign\Model\SignerStatus;
use Omnisign\Model\Status;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * DocuSeal's API - an instance of one's own (open source: https://<host>/api)
 * or DocuSeal's cloud (https://api.docuseal.com, api.docuseal.eu): the API
 * key in X-Auth-Token, JSON both ways.
 */
final class Api
{
    public const PROVIDER = 'docuseal';

    public const STATUSES = ['pending' => Status::SENT, 'completed' => Status::COMPLETED, 'declined' => Status::DECLINED, 'expired' => Status::EXPIRED];
    public const SIGNER_STATUSES = ['awaiting' => SignerStatus::WAITING, 'sent' => SignerStatus::NOTIFIED, 'opened' => SignerStatus::OPENED, 'completed' => SignerStatus::SIGNED, 'declined' => SignerStatus::DECLINED];

    public function __construct(
        private readonly HttpClientInterface $http,
        #[\SensitiveParameter] private readonly string $apiKey,
        public readonly string $url,
        /** where a signer's form opens: <app_url>/s/<slug> */
        public readonly string $appUrl,
        #[\SensitiveParameter] private readonly ?string $webhookSecret = null,
        public readonly int $webhookTolerance = 300,
        private readonly ?\Closure $clock = null,
    ) {
    }

    /**
     * @param array<string, mixed> $json
     *
     * @return array<mixed>
     */
    public function json(string $method, string $path, array $json = []): array
    {
        $answer = Answer::send($this->http, self::PROVIDER, $method, $this->url.$path, ['headers' => ['X-Auth-Token' => $this->apiKey]] + ($json ? ['json' => $json] : []));
        if (\in_array($answer->status, [401, 403], true)) {
            throw new InvalidKeyException(self::PROVIDER, \sprintf('The API key was refused (HTTP %d): %s', $answer->status, self::error($answer)), (string) $answer->status);
        }
        if ($answer->status >= 400) {
            throw new ProviderException(self::PROVIDER, \sprintf('%s %s: HTTP %d, %s', $method, $path, $answer->status, self::error($answer)), (string) $answer->status);
        }

        return '' === trim($answer->body) ? [] : $answer->json();
    }

    /** A file DocuSeal links to (a signed document, the audit log): its URLs are signed, the key goes only to the API's own host. */
    public function fetchFile(string $url): string
    {
        $same = parse_url($url, \PHP_URL_HOST) === parse_url($this->url, \PHP_URL_HOST);
        $answer = Answer::send($this->http, self::PROVIDER, 'GET', $url, $same ? ['headers' => ['X-Auth-Token' => $this->apiKey]] : []);
        if ($answer->status >= 400) {
            throw new ProviderException(self::PROVIDER, \sprintf('GET %s: HTTP %d', strtok($url, '?'), $answer->status), (string) $answer->status);
        }

        return $answer->body;
    }

    /**
     * A webhook's call, its X-Docuseal-Signature checked: "<timestamp>.<hex
     * HMAC-SHA-256 of '<timestamp>.<body>' under the webhook's secret>", as
     * DocuSeal's lib/webhook_urls/signatures.rb signs it.
     *
     * @return array<string, mixed>
     */
    public function verify(string $body, ?string $header): array
    {
        if (null === $this->webhookSecret) {
            throw new InvalidNotificationException(self::PROVIDER, 'No webhook secret is configured.');
        }
        [$timestamp, $signature] = array_pad(explode('.', (string) $header, 2), 2, '');
        if (!ctype_digit($timestamp) || '' === $signature) {
            throw new InvalidNotificationException(self::PROVIDER, 'The X-Docuseal-Signature header is missing or malformed.');
        }
        $now = $this->clock ? ($this->clock)() : time();
        if ($this->webhookTolerance > 0 && abs($now - (int) $timestamp) > $this->webhookTolerance) {
            throw new InvalidNotificationException(self::PROVIDER, 'The callback is too old.');
        }
        if (!hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $this->webhookSecret), $signature)) {
            throw new InvalidNotificationException(self::PROVIDER, 'The signature does not hold.');
        }
        $data = json_decode($body, true);

        return \is_array($data) ? $data : throw new InvalidNotificationException(self::PROVIDER, 'The callback is not JSON.');
    }

    private static function error(Answer $answer): string
    {
        $error = json_decode($answer->body, true);

        if (\is_array($error) && (isset($error['error']) || isset($error['message']))) {
            return (string) ($error['error'] ?? $error['message']);
        }

        return mb_substr(trim($answer->body), 0, 200) ?: 'no detail';
    }
}
