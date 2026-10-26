# omnisign/docuseal

DocuSeal for [omnisense/omnisign](https://github.com/glitchr-omnisense/omnisign): an instance of
one's own - [open source](https://github.com/docusealco/docuseal), nothing leaves for a third
party - or DocuSeal's cloud (`https://api.docuseal.com`, `https://api.docuseal.eu`). Simple
signatures, by e-mail or in the application's page, from a template of the instance's or from the
envelope's PDFs. Every call carries the API key as `X-Auth-Token`; JSON both ways.

## Installation

```sh
composer require omnisign/docuseal
```

PHP 8.2 or later, `omnisense/omnisign` and `symfony/http-client`. A DocuSeal instance and its API key
(Settings > API) - or DocuSeal's cloud.

```sh
docker run -d -p 3000:3000 -v docuseal:/data docuseal/docuseal       # an instance of one's own
```

## The sources

Read on 2026-10-08: DocuSeal's API reference and its OpenAPI (`console.docuseal.com/openapi.yml`),
and the open-source code itself ([docusealco/docuseal](https://github.com/docusealco/docuseal):
`config/routes.rb`, `app/controllers/api/*`, `lib/submissions/serialize_for_api.rb`,
`lib/webhook_urls/signatures.rb`) - which says what the open-source edition has: no
`/submissions/pdf`, a signature on webhooks.

## Credentials

- The instance's (or the cloud account's) **API key**: Settings, API.
- For webhooks, a webhook created in the instance's settings, and its **secret** (`whsec_...`).

## Options

| Option | Default | |
|---|---|---|
| `url` | required | the API's root: `https://<host>/api` for an instance, `https://api.docuseal.com` or `https://api.docuseal.eu` for the cloud |
| `api_key` | required | sent as `X-Auth-Token` |
| `app_url` | the `url` without `/api` (the cloud: `https://docuseal.com`) | where signers' forms open: `<app_url>/s/<slug>` |
| `webhook_secret`, `webhook_tolerance` | none, 300 | the webhook's secret (`whsec_...`); without it, `notify()` is not supported |
| `third_party` | told from the url | whether the instance is someone else's: the cloud is |

## Templates and PDFs

| | |
|---|---|
| `Envelope::$template` set | `POST /submissions`: `template_id`, the submitters by **role** (`Signer::$role`; the first may leave it, "First Party"), `external_id` their key - the open-source edition's way |
| documents and fields | `POST /submissions/pdf`: each document in base64, its fields (`name`, `type`, `role`: the signer's key, `areas`: x, y, w, h, page) - DocuSeal Pro and the cloud; an open-source instance answers 404, "This feature is available in Pro Edition" |

DocuSeal sends at creation (e-mails, or nothing when `embedded`): `create()` answers `SENT`, `send()`
reads the submission back.

| | |
|---|---|
| `fetch()` | `GET /submissions/{id}`: `pending` `SENT`, `completed` `COMPLETED`, `declined`, `expired`; archived: `CANCELED`. Submitters: `awaiting` `WAITING`, `sent` `NOTIFIED`, `opened` `OPENED`, `completed` `SIGNED`, `declined` `DECLINED` |
| `remind()` | `PUT /submitters/{id}`, `send_email: true`, for each who has not signed |
| `cancel()` | `DELETE /submissions/{id}`: archived |
| `download()` | the completed submission's documents and its `audit_log_url`, fetched - the key sent only to the instance's own host |
| `signingUrl()` | `embed_src`, or `<app_url>/s/<slug>`: a page of its own, or the `docuseal-form` web component's `src` |
| `notify()` | `X-Docuseal-Signature: <timestamp>.<hex HMAC-SHA-256 of "<timestamp>.<body>" under the secret>`, five minutes' tolerance - as `lib/webhook_urls/signatures.rb` signs it; `form.completed` `SIGNED`, `submission.completed` `COMPLETED`... |

Simple signatures only.

## Capabilities

| Request | Supported |
|---|---|
| `Create` | yes: from a template (`POST /submissions`), or from PDFs (`POST /submissions/pdf`: DocuSeal Pro and the cloud; an open-source instance refuses it) |
| `Send` | reads the submission back: DocuSeal sends at creation |
| `Fetch`, `Remind`, `Cancel`, `Download`, `SigningUrl` | yes |
| `Notify` | with `webhook_secret` |

`capabilities()`: simple; in the page; ordered; no identity check; third party only for the cloud
(or `third_party`); documents; templates. `Signer::$locale` is not sent; `SigningUrl`'s `returnUrl`
is not read (the envelope's `redirectUrl` is DocuSeal's `completed_redirect_url`); a field's
`anchor` is not read: fields are placed by page and position (`w` 200 and `h` 60 when not given).
The signed documents' URLs are fetched with the API key only when they are on the API's own host.

## Example

The [installation script of the manual](https://github.com/glitchr-omnisense/omnisign/blob/1.x/docs/installation.md#plain-php) is this gateway's, run on
2026-10-08 against an open-source instance in Docker. On the recorded answers, on 2026-10-26, with a
webhook signed as DocuSeal signs it:

```
docuseal
  > POST http://localhost:13300/api/submissions
  4 sent tenant=4 waiting http://localhost:13300/s/KKjaQ1uucspGL5
  notify: signed form.completed 4 tenant form.completed@7@2026-10-26T09:00:00Z
```

In Symfony:

```yaml
omnisign:
    gateways:
        contracts:
            factory: docuseal
            options:
                url: '%env(DOCUSEAL_URL)%'
                api_key: '%env(DOCUSEAL_API_KEY)%'
                webhook_secret: '%env(default::DOCUSEAL_WEBHOOK_SECRET)%'
```

## Errors

| Answer | Exception |
|---|---|
| 401, 403 | `InvalidKeyException` (`The API key was refused (HTTP 401): ...`) |
| other 4xx (an open-source instance's 404 on `/submissions/pdf`) | `ProviderException` (`<METHOD> <path>: HTTP <status>, <error>`) |
| 5xx, 429, no answer, a timeout (10 s) | `UnreachableException` |
| a download of a submission not completed | `ProviderException` |
| a level other than simple; no signer; neither template nor documents; a template signer without a role (but the first) | `InvalidConfigException`, before any call |
| a signing URL for a signer not created with the envelope | `InvalidConfigException` |
| a webhook that does not hold | `InvalidNotificationException` |

## Verified, and not

| | |
|---|---|
| The whole way on an open-source instance | **done on 2026-10-08**: `docuseal/docuseal` run in Docker, set up, a one-page PDF uploaded as a template and given a signature field through the API; then `create()` from the template (embedded), `signingUrl()` (`/s/<slug>`), `fetch()` (`SENT`, the tenant `WAITING`), the tenant completed through the API, `fetch()` (`COMPLETED`, signed at...), `download()` (the signed PDF, 17,829 bytes, and the audit log, 54,542 bytes), a second submission `cancel()`ed and read back `CANCELED`. `Tests/Fixtures` holds the answers recorded that day |
| `/submissions/pdf` | seen refused by the open-source edition (404, "available in Pro Edition"); its body follows DocuSeal's OpenAPI, **not verified** on Pro or the cloud |
| `remind()` | accepted by the instance (200); the e-mail not observed - no mail server configured |
| Webhooks | the signature by the tests, as DocuSeal's code computes it; no callback received from the instance |
| A signer signing in a browser | not done: completed through the API |

The package's tests (`Tests/DocusealGatewayTest.php`, 6 tests) pass on 2026-10-26 (PHP 8.4).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by DocuSeal. Whoever runs it against DocuSeal's cloud complies with
[docuseal.com/terms](https://www.docuseal.com/terms); see the README's Responsibility section.
