---
title: omnisign/docuseal
order: 1
---

# omnisign/docuseal

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

## Verified, and not

| | |
|---|---|
| The whole way on an open-source instance | **done on 2026-10-08**: `docuseal/docuseal` run in Docker, set up, a one-page PDF uploaded as a template and given a signature field through the API; then `create()` from the template (embedded), `signingUrl()` (`/s/<slug>`), `fetch()` (`SENT`, the tenant `WAITING`), the tenant completed through the API, `fetch()` (`COMPLETED`, signed at...), `download()` (the signed PDF, 17,829 bytes, and the audit log, 54,542 bytes), a second submission `cancel()`ed and read back `CANCELED`. `Tests/Fixtures` holds the answers recorded that day |
| `/submissions/pdf` | seen refused by the open-source edition (404, "available in Pro Edition"); its body follows DocuSeal's OpenAPI, **not verified** on Pro or the cloud |
| `remind()` | accepted by the instance (200); the e-mail not observed - no mail server configured |
| Webhooks | the signature by the tests, as DocuSeal's code computes it; no callback received from the instance |
| A signer signing in a browser | not done: completed through the API |
