<?php

namespace Omnisign\Docuseal;

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
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
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
                thirdParty: null !== $c->get('third_party') && '' !== $c->get('third_party') ? $c->bool('third_party') : (bool) preg_match('~(^|\.)docuseal\.(com|eu|co)$~', (string) parse_url((string) $c['url'], \PHP_URL_HOST)),
                documents: true,
                templates: true,
            ),
            'omnisign.api' => function (Config $c): Api {
                $url = rtrim((string) $c['url'], '/');
                $app = $c->get('app_url') ?: (preg_match('~^https://api\.(docuseal\.(?:com|eu))$~', $url, $m) ? 'https://'.$m[1] : preg_replace('~/api$~', '', $url));

                return new Api($this->http ?? HttpClient::create(), (string) $c['api_key'], $url, rtrim((string) $app, '/'), $c->get('webhook_secret') ?: null, (int) $c['webhook_tolerance']);
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
}
