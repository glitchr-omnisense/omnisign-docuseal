<?php

namespace Omnisign\Docuseal\Tests;

use Omnisign\Docuseal\DocusealGatewayFactory;
use Omnisign\Exception\InvalidConfigException;
use Omnisign\Exception\InvalidKeyException;
use Omnisign\Exception\InvalidNotificationException;
use Omnisign\Exception\ProviderException;
use Omnisign\GatewayInterface;
use Omnisign\Model\Document;
use Omnisign\Model\Envelope;
use Omnisign\Model\Event;
use Omnisign\Model\Field;
use Omnisign\Model\File;
use Omnisign\Model\Signer;
use Omnisign\Model\SignerStatus;
use Omnisign\Model\Status;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers recorded on 2026-10-08 from a DocuSeal instance of the open-source
 * edition (docuseal/docuseal, run locally at localhost:13300): a submission
 * created from a template, pending, completed, archived; the Pro edition's
 * /submissions/pdf refused; a wrong key.
 */
final class DocusealGatewayTest extends TestCase
{
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $calls = [];

    /** @param list<string|MockResponse> $answers */
    private function gateway(array $answers, array $options = []): GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->calls[] = [$method, $url, $options];
            if (str_contains($url, '/file/')) {
                return new MockResponse(str_contains($url, 'Audit%20Log') ? '%PDF-1.6 audit' : '%PDF-1.4 signed');
            }
            $answer = array_shift($answers) ?? throw new \LogicException('No answer left for '.$method.' '.$url);
            if ($answer instanceof MockResponse) {
                return $answer;
            }
            [$file, $status] = explode(':', $answer.':200');

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$file.'.json'), ['http_code' => (int) $status]);
        });

        return (new DocusealGatewayFactory($http))->create($options + ['url' => 'http://localhost:13300/api', 'api_key' => 'ds-api-key']);
    }

    private static function lease(): Envelope
    {
        return new Envelope('Bail - harness', signers: [new Signer('tenant', 'Camille Érable', 'camille@erable.example')], embedded: true, template: '1', key: 'lease-42');
    }

    public function testASubmissionFromATemplateIsSentAtOnce(): void
    {
        $created = $this->gateway(['submissions-create'])->create(self::lease());

        self::assertSame(['POST', 'http://localhost:13300/api/submissions'], [$this->calls[0][0], $this->calls[0][1]]);
        self::assertStringContainsString('X-Auth-Token: ds-api-key', implode("\n", $this->calls[0][2]['headers']));
        self::assertSame(['template_id' => 1, 'submitters' => [['role' => 'First Party', 'email' => 'camille@erable.example', 'name' => 'Camille Érable', 'external_id' => 'tenant', 'send_email' => false]], 'send_email' => false, 'order' => 'random'], json_decode((string) $this->calls[0][2]['body'], true));
        $tenant = $created->state('tenant');
        self::assertSame([Status::SENT, '4', SignerStatus::WAITING], [$created->status, $created->reference, $tenant->status]);
        self::assertMatchesRegularExpression('~^http://localhost:13300/s/[A-Za-z0-9]+$~', (string) $tenant->link);
    }

    public function testItIsFollowedCompletedDownloadedOrArchived(): void
    {
        $gateway = $this->gateway(['submissions-create', 'submission-pending', 'submission-completed', 'submission-completed', 'submission-archived']);
        $created = $gateway->create(self::lease());

        $pending = $gateway->fetch($created);
        self::assertSame([Status::SENT, SignerStatus::WAITING], [$pending->status, $pending->state('tenant')->status]);
        $done = $gateway->fetch($created->with(reference: '2', states: ['tenant' => new \Omnisign\Model\SignerState('tenant', '2')]));
        self::assertSame([Status::COMPLETED, SignerStatus::SIGNED], [$done->status, $done->state('tenant')->status]);

        $download = $gateway->download($done);
        self::assertSame(['lease.pdf', '%PDF-1.4 signed'], [$download->documents[0]->filename, $download->documents[0]->content]);
        self::assertSame(['audit-log.pdf', '%PDF-1.6 audit'], [$download->evidence?->filename, $download->evidence?->content]);
        $files = array_values(array_filter($this->calls, static fn ($c) => str_contains($c[1], '/file/')));
        self::assertCount(2, $files);
        self::assertStringContainsString('X-Auth-Token', implode("\n", $files[0][2]['headers'] ?? []), 'the instance\'s own host: the key goes with it');

        self::assertSame(Status::CANCELED, $gateway->fetch($created->with(reference: '3'))->status, 'archived');
    }

    public function testCancelRemindAndTheSigningForm(): void
    {
        $gateway = $this->gateway(['submissions-create', new MockResponse('{"id":4,"archived_at":"2026-10-08T01:14:29.073Z"}'), new MockResponse('{"id":4,"status":"awaiting"}')]);
        $created = $gateway->create(self::lease());

        self::assertSame(Status::CANCELED, $gateway->cancel($created)->status);
        self::assertSame(['DELETE', 'http://localhost:13300/api/submissions/4'], [$this->calls[1][0], $this->calls[1][1]]);
        $gateway->remind($created);
        self::assertSame(['PUT', ['send_email' => true]], [$this->calls[2][0], json_decode((string) $this->calls[2][2]['body'], true)]);
        self::assertSame($created->state('tenant')->link, $gateway->signingUrl($created, 'tenant')->url);
    }

    public function testDocumentsTakeTheProEditionWhichAnOpenSourceInstanceRefuses(): void
    {
        $envelope = new Envelope('Bail', [new Document('lease', new File('%PDF', 'bail.pdf'))], [new Signer('tenant', 'Camille Érable', 'camille@erable.example')], [new Field('tenant', 'lease', x: 100, y: 600)]);
        try {
            $this->gateway(['submission-pdf-404:404'])->create($envelope);
            self::fail();
        } catch (ProviderException $e) {
            self::assertStringContainsString('This feature is available in Pro Edition', $e->getMessage());
        }
        $body = json_decode((string) $this->calls[0][2]['body'], true);
        self::assertSame('http://localhost:13300/api/submissions/pdf', $this->calls[0][1]);
        self::assertSame(['name' => 'bail', 'file' => base64_encode('%PDF'), 'fields' => [['name' => 'signature-tenant-1', 'type' => 'signature', 'role' => 'tenant', 'required' => true, 'areas' => [['x' => 100, 'y' => 600, 'w' => 200, 'h' => 60, 'page' => 1]]]]], $body['documents'][0]);

        $this->expectException(InvalidConfigException::class);
        $this->gateway([])->create(new Envelope('Nothing', signers: [new Signer('s', 'S', 's@example.org')]));
    }

    public function testAWebhooksSignatureIsCheckedAsDocusealSignsIt(): void
    {
        $body = '{"event_type":"form.completed","timestamp":"2026-10-08T01:14:25Z","data":{"id":4,"submission_id":4,"external_id":"tenant","status":"completed"}}';
        $time = (string) time();
        $gateway = $this->gateway([], ['webhook_secret' => 'whsec_c2VjcmV0LW9mLXRoZS13ZWJob29r']);

        $notification = $gateway->notify($body, ['X-Docuseal-Signature' => $time.'.'.hash_hmac('sha256', $time.'.'.$body, 'whsec_c2VjcmV0LW9mLXRoZS13ZWJob29r')]);
        self::assertSame([Event::SIGNED, '4', 'tenant'], [$notification->event, $notification->reference, $notification->signer]);

        $this->expectException(InvalidNotificationException::class);
        $gateway->notify($body, ['X-Docuseal-Signature' => (time() - 3600).'.'.hash_hmac('sha256', (time() - 3600).'.'.$body, 'whsec_c2VjcmV0LW9mLXRoZS13ZWJob29r')]);
    }

    public function testAWrongKeyAndWhoIsAThirdParty(): void
    {
        self::assertFalse($this->gateway([])->capabilities()->thirdParty, 'an instance of one\'s own');
        self::assertTrue($this->gateway([], ['url' => 'https://api.docuseal.eu'])->capabilities()->thirdParty, 'DocuSeal\'s cloud');
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage('Not authenticated');
        $this->gateway(['unauthorized:401'])->fetch(self::lease()->with(reference: '2'));
    }
}
