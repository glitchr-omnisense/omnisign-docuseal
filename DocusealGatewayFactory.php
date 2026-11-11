<?php

namespace Omnisign\Docuseal;

use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisign\Config;
use Omnisign\Docuseal\Action\CancelAction;
use Omnisign\Docuseal\Action\CreateAction;
use Omnisign\Docuseal\Action\DownloadAction;
use Omnisign\Docuseal\Action\FetchAction;
use Omnisign\Docuseal\Action\NotifyAction;
use Omnisign\Docuseal\Action\RemindAction;
use Omnisign\Docuseal\Action\SendAction;
use Omnisign\Docuseal\Action\SigningUrlAction;
use Omnisign\GatewayFactory;
use Omnisign\Model\Capabilities;
use Omnisign\Model\Level;
use Omnisign\Model\StatementLimit;
use Omnisign\Model\StatementMode;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * DocuSeal: an instance of one's own - open source, nothing leaves for a
 * third party - or DocuSeal's cloud.
 *
 *   options:
 *     url: https://sign.example.org/api            # required: the API's root (cloud: https://api.docuseal.com, https://api.docuseal.eu)
 *     api_key: '%env(DOCUSEAL_API_KEY)%'           # required: the instance's API key (Settings > API)
 *     app_url: ~                                   # where signers' forms open (<app_url>/s/<slug>); the url without /api by default
 *     webhook_secret: '%env(default::DOCUSEAL_WEBHOOK_SECRET)%'   # whsec_...: notify() checks X-Docuseal-Signature with it
 *     webhook_tolerance: 300
 *     third_party: ~                               # whether the instance is someone else's; told from the url when left empty (the cloud: yes)
 */
final class DocusealGatewayFactory extends GatewayFactory
{
    private static ?UsagePolicy $policy = null;

    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    /** Its usage policy, as policy.json declares it (read once). */
    public static function usagePolicy(): UsagePolicy
    {
        return self::$policy ??= UsagePolicy::fromFile(__DIR__.'/policy.json');
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnisign.factory_name' => 'docuseal',
            'omnisign.factory_title' => 'DocuSeal',
            'omnisign.required_options' => ['url', 'api_key'],
            'app_url' => null,
            'webhook_secret' => null,
            'webhook_tolerance' => 300,
            'third_party' => null,
            'omnisign.capabilities' => static fn (Config $c) => new Capabilities(
                [Level::SIMPLE],
                embedded: true,
                ordered: true,
                identityCheck: false,
                thirdParty: null !== $c->get('third_party') && '' !== $c->get('third_party') ? $c->bool('third_party') : (bool) preg_match('~(^|\.)docuseal\.(com|eu|co)$~', (string) parse_url(Data::cast($c['url']), \PHP_URL_HOST)),
                documents: true,
                templates: true,
                policy: self::usagePolicy(),
                statements: self::statements(),
            ),
            'omnisign.api' => function (Config $c): Api {
                $url = rtrim(Data::cast($c['url']), '/');
                $app = $c->get('app_url') ?: (preg_match('~^https://api\.(docuseal\.(?:com|eu))$~', $url, $m) ? 'https://'.$m[1] : preg_replace('~/api$~', '', $url));

                return new Api($this->http ?? HttpClient::create(), Data::cast($c['api_key']), $url, rtrim(Data::cast($app), '/'), Data::cast($c->get('webhook_secret')) ?: null, Data::integer($c['webhook_tolerance']), backoff: $c->backoff());
            },
            'omnisign.action.create' => new CreateAction(),
            'omnisign.action.send' => new SendAction(),
            'omnisign.action.fetch' => new FetchAction(),
            'omnisign.action.remind' => new RemindAction(),
            'omnisign.action.cancel' => new CancelAction(),
            'omnisign.action.download' => new DownloadAction(),
            'omnisign.action.signing_url' => new SigningUrlAction(),
            'omnisign.action.notify' => static fn (Config $c) => $c->get('webhook_secret') ? new NotifyAction() : null,
        ]);
    }

    /**
     * The statements DocuSeal carries, as its OpenAPI says
     * (console.docuseal.com/openapi.yml, read 2026-11-11): typed in a text
     * field, or acknowledged by ticking a required checkbox, the statement
     * as the field's description (Markdown, shown on the signing form). Its
     * OpenAPI states no length for a field's value or description: no limit
     * declared. Fields placed by coordinates need DocuSeal Pro.
     *
     * @return list<StatementLimit>
     */
    public static function statements(): array
    {
        $source = 'DocuSeal OpenAPI, console.docuseal.com/openapi.yml, read 2026-11-11: documents[].fields description, no length stated';

        return [new StatementLimit(StatementMode::TYPED, null, StatementLimit::CHARACTERS, $source), new StatementLimit(StatementMode::ACKNOWLEDGED, null, StatementLimit::CHARACTERS, $source)];
    }
}
