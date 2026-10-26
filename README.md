# omnisign/docuseal

**DocuSeal** for [omnisense/omnisign](https://github.com/glitchr-omnisense/omnisign): documents signed on
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

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by DocuSeal. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence covers
this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with DocuSeal's terms
([docuseal.com/terms](https://www.docuseal.com/terms)), for obtaining whatever they require (an
account, a key, a licence or subscription, an attribution or a citation, a rate limit to respect),
and for any use made of what it returns. The authors publish it free of charge, make no commercial
use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
