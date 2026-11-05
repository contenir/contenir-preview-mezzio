# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-29

### Added

- `PreviewMiddleware`, which renders the Contenir CMS's previews in a Mezzio
  application: piped before routing, it opens a post to
  `/api/resource/preview`, sends it down the pipeline as a visit to the page
  that shows the record, and answers with the protocol's headers and a
  visible "Preview" badge.
- A wrapper around `RouteMiddleware` that routes a preview whose resolver
  named a handler, with the target's parameters as route parameters.
- The services contenir-preview's `ConfigProvider` builds from the
  `contenir_preview` configuration, registered with the application's.
- Page caches stand aside for a preview: the request is marked
  `Cache-Control: no-cache, no-store`.
- `ShareMiddleware`, which answers shared preview links at `/preview/{id}`
  (§9 of the protocol): the code form, the pass once the code is right, and
  the record's page, as it is saved, for a visitor holding the pass. It
  leaves every request alone while the site has no `contenir_preview.share`
  configuration. `ShareResponder` gives each answer the shared preview's
  headers.

### Changed

- Requires contenir-preview 0.3, with shared previews, and so
  `minimum-stability: dev` for php-db, which has no stable release yet.
- `main` is aliased as `0.1.x-dev`, the version packages that require this
  one name.
