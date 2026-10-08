# omnisign/docuseal

**DocuSeal** for [glitchr/omnisign](https://github.com/glitchr-studio/omnisign): documents signed on
an instance of one's own - [open source](https://github.com/docusealco/docuseal) (AGPL-3.0), run in
a container: nothing leaves for a third party - or on DocuSeal's cloud.

```php
use Omnisign\Docuseal\DocusealGatewayFactory;

$gateway = (new DocusealGatewayFactory($httpClient))->create(['url' => 'https://sign.example.org/api', 'api_key' => getenv('DOCUSEAL_API_KEY')]);

$envelope = $gateway->create(new Envelope('Bail', signers: [$tenant], embedded: true, template: '12'));
$gateway->signingUrl($envelope, 'tenant')->url;           // https://sign.example.org/s/<slug>
$gateway->download($gateway->fetch($envelope));           // the signed PDF and the audit log, once completed
```

The open-source edition makes a submission from a **template** of the instance's; from the
application's own PDFs (`POST /submissions/pdf`) takes DocuSeal Pro or the cloud.

**Verified in real on 2026-10-08** against an instance of the open-source edition run in Docker:
created, signing link, completed, downloaded (signed PDF and audit log), archived.

[Documentation](docs/index.md): the options, templates and PDFs, the webhooks, what was verified.

License: LGPL-3.0-or-later.
