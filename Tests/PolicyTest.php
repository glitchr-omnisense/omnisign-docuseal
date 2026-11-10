<?php

namespace Omnisign\Docuseal\Tests;

use Omnisign\Exception\UsageLimitReachedException;
use Omnisign\Model\Envelope;
use Omnisign\Registry;
use Omnisign\Docuseal\DocusealGatewayFactory;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** DocuSeal's usage policy: declared in policy.json, read by the gateway, enforced through the registry. */
final class PolicyTest extends TestCase
{
    public function testThePolicyIsDeclaredAndRead(): void
    {
        $declared = UsagePolicy::fromFile(__DIR__.'/../policy.json');
        $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);

        self::assertIsArray($composer);
        $extra = $composer['extra'] ?? null;
        self::assertIsArray($extra);
        self::assertSame(['policy' => 'policy.json'], $extra['omnisense'] ?? null);
        self::assertSame('docuseal', $declared->provider);
        self::assertSame('2026-11-10', $declared->provenance->verifiedAt);
        self::assertSame($declared->toArray(), DocusealGatewayFactory::usagePolicy()->toArray());
        self::assertNull($declared->rateLimits, 'no limit published');

        $registry = new Registry([new DocusealGatewayFactory(new MockHttpClient())], ['docuseal' => ['factory' => 'docuseal', 'options' => self::options()]]);
        self::assertSame($declared->toArray(), $registry->policy('docuseal')->toArray());
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('{"error":"too many requests"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '30', 'content-type' => 'application/json']]);
        });
        $registry = new Registry([new DocusealGatewayFactory($http)], ['docuseal' => ['factory' => 'docuseal', 'options' => self::options()]]);

        try {
            $registry->get('docuseal')->fetch(new Envelope('Bail', reference: '42'));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        try {
            $registry->get('docuseal')->fetch(new Envelope('Bail', reference: '42'));
            self::fail('A call went out while DocuSeal asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(29, $e->retryAfter);
        }
        self::assertSame(1, $requests, 'the wait held the next request back');
    }

    /** @return array<string, mixed> */
    private static function options(): array
    {
        return ['url' => 'https://api.docuseal.eu', 'api_key' => 'ds-api-key'];
    }
}
