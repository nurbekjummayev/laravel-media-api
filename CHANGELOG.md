# Changelog

All notable changes to `nurbekjummayev/laravel-media-api` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **BREAKING:** `view`/`download` now use Laravel temporary **signed URLs** instead of a custom encrypted `?token=`. URLs are tamper-proof (validated against `APP_KEY`) and expire after `config('media.url_ttl')`.
- Centralised signed-URL generation and validation in a new `MediaUrlService` (`viewUrl()`, `downloadUrl()`, `resolveSigned()`); the model and controller now delegate to it instead of duplicating the signing logic.
- Renamed config key `token_ttl` → `url_ttl`.
- `Media::$url` now returns a signed route URL; added `Media::downloadUrl()` for signed downloads.

### Added
- Atomic uploads: `MediaService::store()` now verifies the file actually landed on disk, deletes the file if the `Media` record can't be saved, and removes written files if the surrounding transaction rolls back (`DB::afterRollback`). The upload endpoint wraps the whole request in a transaction, so a multi-file upload is all-or-nothing with no orphan files.
- Cascading media cleanup: deleting a model that uses `InteractsWithMedia` now deletes its FK-column media (`mediaColumns()`) and pivot media (new `mediaRelations()`), detaching the pivot links.
- The physical file is now removed only **after the surrounding DB transaction commits** (`DB::afterCommit`) — a rolled-back delete keeps the model, the media row, and the file. Applies to `MediaService::delete()`, the `DELETE` endpoint, and trait-driven cascades.
- Full test suite (Pest + Orchestra Testbench) covering the service, model, signed-URL flow, the `InteractsWithMedia` trait, cascading delete + commit deferral, the `media:purge` command, and the HTTP endpoints.
- GitHub Actions CI matrix (PHP 8.3/8.4 × Laravel 12).
- `.gitignore`, `LICENSE` (MIT), and this changelog.

### Security
- Inline `view` responses now send `Content-Security-Policy: ... sandbox` and `X-Content-Type-Options: nosniff`, neutralising script execution in SVG/HTML files opened directly in the browser.
- Hardened upload against MIME spoofing and script smuggling: `MediaService::store()` validates the extension against the allow/deny list *before* writing to disk (so direct calls can't drop `.php`/`.phtml`/`.htaccess`/`.exe`), stores files only under `Y/m/d/<uuid>.<ext>` with a sanitised extension (no client path/name on disk), strips directory parts and control/CRLF characters from the kept display `name` (prevents `Content-Disposition` header injection), and records the server-detected MIME instead of the client-supplied value.
- `StoreMediaRequest` now also blocks dangerous extensions found in the content-guessed extension, and refuses active content (`public_blocked_extensions`: SVG/XML/…) on the public disk, which is served without a CSP. Expanded the default `blocked_extensions` list (php7/php8/phps/pht, scr/jar, pl/py/rb/cgi, xhtml/shtml, htpasswd, …).
- Added a dedicated upload attack-surface test suite (`UploadSecurityTest`).

### Removed
- `MediaTokenService` (superseded by signed URLs).

## [1.0.0] - 2026-06-18

### Added
- Initial release: standalone media upload API with private/public disks, per-model linking, owner tracking, and scheduled orphan cleanup (`media:purge`).
