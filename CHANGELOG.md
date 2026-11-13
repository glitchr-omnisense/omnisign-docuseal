# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-13

### Changed
- Neutral example data in docs and tests.

## [1.0.0] - 2026-11-13

The first stable release. Omnisign DocuSeal: documents signed on a DocuSeal instance of one's own (open source, AGPL) or DocuSeal's cloud - a submission from a template, or from a PDF where the edition allows it, its submitters, embedded signing, reminders, the signed documents and the audit log, webhooks with their X-Docuseal-Signature checked; nothing leaves for a third party when self-hosted.

### Changed

- Requires `omnisense/contracts: ^1.0`, `omnisense/omnisign: ^1.0`: stable versions, so `composer require omnisign/docuseal` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
