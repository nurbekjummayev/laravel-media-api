# Changelog

All notable changes to `nurbekjummayev/laravel-media-api` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **BREAKING:** `view`/`download` now use Laravel temporary **signed URLs** instead of a custom encrypted `?token=`. URLs are tamper-proof (validated against `APP_KEY`) and expire after `config('media.url_ttl')`.
- Renamed config key `token_ttl` → `url_ttl`.
- `Media::$url` now returns a signed route URL; added `Media::downloadUrl()` for signed downloads.

### Added
- Full test suite (Pest + Orchestra Testbench) covering the service, model, token/signature flow, the `InteractsWithMedia` trait, the `media:purge` command, and the HTTP endpoints.
- GitHub Actions CI matrix (PHP 8.3/8.4 × Laravel 12).
- `.gitignore`, `LICENSE` (MIT), and this changelog.

### Security
- Inline `view` responses now send `Content-Security-Policy: ... sandbox` and `X-Content-Type-Options: nosniff`, neutralising script execution in SVG/HTML files opened directly in the browser.

### Removed
- `MediaTokenService` (superseded by signed URLs).

## [1.0.0] - 2026-06-18

### Added
- Initial release: standalone media upload API with private/public disks, per-model linking, owner tracking, and scheduled orphan cleanup (`media:purge`).
