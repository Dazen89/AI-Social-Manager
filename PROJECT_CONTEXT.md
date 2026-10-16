# AI Social Manager — Public project context

_Status: initial Laravel installation; features described below are planned unless marked implemented._

## Purpose
AI Social Manager is a reusable web application to help small teams publish social posts with limited manual effort and evaluate results **per post**, in the same interface where the published material is listed.

The initial implementation targets X, while keeping integrations modular enough to support other social platforms and source websites in the future. Low running cost and operational simplicity are design priorities. AI-assisted copywriting may be introduced later; do not make it a prerequisite.

## Stack
- Laravel 13, PHP 8.4 compatible.
- Local development with SQLite; production with MySQL. Database schema changes must work on both.
- Laravel-native UI (Blade and, only if useful, Livewire), Laravel Scheduler, database queues if required, HTTP Client for external APIs.
- Code is developed locally, versioned on GitHub and deployed to a hosted Laravel application; environment-specific settings stay outside Git.

## Product scope
1. **Authentication:** private dashboard; login/logout; no public registration; an initial admin can create additional admin accounts through controlled functionality.
2. **Content sources:** configurable read-only connectors that can import titles, descriptions, destination URLs and expressly approved promotional assets. Begin with one source connector; do not couple business logic to any particular publisher's schema.
3. **Editorial workflow:** drafts, review/approval, publication scheduling, duplicate prevention and complete post history. Store text, media references, timestamps, external post ID, destination and publication status.
4. **Social publishing:** use official APIs; support failures/retries, idempotency, cost limits and a default-disabled publishing mode for development.
5. **Analytics:** obtain available per-post social metrics at controlled intervals; retain snapshots. Attribute visits using tagged links and a separately configured website analytics provider when permitted.
6. **Outbound link tracking:** optional transparent redirect service for destinations on allowlisted domains. A redirect click is not equivalent to a website visit or conversion.
7. **Post-focused dashboard:** show the post content next to impressions (if available), likes, reposts, replies, attributable website visits and outbound clicks. Make unavailable metrics explicit, not zero. Provide filters, reporting by content type and comparisons normalized by impressions where available.

## Safeguards
- Confirm current API prices, availability and permissions before implementation; never embed tariff assumptions in permanent business logic.
- Do not generate or publish posts or images from arbitrary source material without configurable eligibility and approval controls.
- Consider consent, privacy requirements and sensitive page metadata when connecting analytics platforms. Avoid sending personal or sensitive information into logs, URLs or third-party services.
- Keep real content, domain-specific settings and source database structure outside the public repository.

## Proposed milestones
**A. Foundation:** inspect repository; implement login/logout, authorization, protected dashboard and controlled admin creation; add feature tests.

**B. Content catalog:** connector contract, source configuration, local fixtures, import/sync and deduplication.

**C. Publishing:** drafts, approvals, schedule, social API adapter, fakes, idempotency and spending controls.

**D. Measurement:** social metric snapshots, UTM attribution adapter and optional allowlisted redirect tracker.

**E. Post performance UI:** published-post list with metrics in context, filters and comparisons.

**F. Pilot:** limited approved posting and a measurement period before increasing automation.

## Development rules
- Never commit secrets, local data files, dumps or installation-specific documentation.
- Use safe synthetic fixtures in tests. Request unknown connector schemas rather than guessing.
- Review database migrations for both SQLite and MySQL.
- Do not change production data or perform social API writes without explicit authorization.

Installation-specific facts may be documented locally in `PROJECT_PRIVATE.md`; that file is deliberately excluded from Git and is **not** part of the open-source specification.
