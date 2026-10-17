# Monitoring Hub — Public project context

_Status: authentication, the site registry, the Google Analytics OAuth connection flow, a first site-level analytics overview and the installation-level AI provider configuration are implemented. Features not marked implemented are planned._

## Purpose
Monitoring Hub is a private, reusable dashboard for small teams that need to centralize the services used to monitor websites they own or are explicitly authorized to manage.

The product is site-focused: an administrator registers a website, connects one or more optional monitoring providers, imports or discovers its canonical pages and reviews metrics in a single interface. Google Analytics 4 is the first provider. Social publishing and other services may be added later as independent adapters; they are not prerequisites for the monitoring dashboard.

The dashboard may run on a different subdomain from the monitored website. It must access provider data through official, authorized server-side APIs rather than scraping dashboards or assuming same-origin access.

## Stack
- Laravel 13, PHP 8.4 compatible.
- Local development with SQLite; production with MySQL. Database schema changes must work on both.
- Laravel-native UI (Blade and, only if useful, Livewire), Laravel Scheduler, database queues if required, and Laravel HTTP Client for external APIs.
- Code is developed locally, versioned on GitHub and deployed to a hosted Laravel application; secrets and environment-specific settings stay outside Git.

## Product scope
1. **Authentication — implemented:** private dashboard, login/logout, no public registration, initial administrator provisioning through an environment-backed seeder.
2. **Site registry — implemented:** administrators first register owned sites using a canonical base URL and manage whether monitoring is active.
3. **Service selection — implemented foundation:** after creating a site, administrators manage it from a dedicated page and review the optional services available for connection. Provider-specific fields do not belong in the initial site form.
4. **Google Analytics connection — implemented:** guided installation-level OAuth credential setup, read-only OAuth flow, encrypted credential and token storage, token refresh, account/property discovery, explicit server-verified property selection, matching web-stream discovery, disconnect/revocation and clear connection health. Manual provider identifiers are not the primary setup path.
5. **Page catalog — planned:** import canonical pages from an approved sitemap or read-only source connector, normalize URLs and prevent duplicates. Analytics rows are metrics, not the canonical source of website pages.
6. **Measurement — implemented foundation:** query Google Analytics Data API on demand through one batched request, filter by the registered hostname, cache responses for ten minutes and expose unavailable or delayed metrics explicitly. Scheduled snapshots and historical persistence remain planned.
7. **Site-focused dashboard — implemented foundation:** each connected site has a 7/28/90-day overview with traffic totals, daily sessions, pages, acquisition channels, devices and countries. Comparisons, richer page drill-down and provider sync history remain planned.
8. **AI provider integration — implemented foundation:** administrators can select OpenAI or Anthropic, choose a supported model and save an API key encrypted at rest. A server-side adapter exposes text generation through the provider's official API without exposing credentials to the browser. Product workflows that consume generated content remain planned.
9. **Additional adapters — future:** other analytics, monitoring, content and social services may be introduced behind configurable contracts without coupling the core domain to one provider.

## Domain principles
- A site is shared within the single private administrative workspace; this project does not introduce multi-tenancy prematurely.
- The setup experience must be self-explanatory for third-party installations: every provider field should state whether it is required, what it identifies, where to find it and whether it contains a secret. Link to official provider documentation where useful and never assume prior analytics expertise.
- Provider identities and OAuth connections are separate from the sites and properties they can access, so one authorized connection may serve multiple owned sites.
- A Google Analytics account can expose multiple GA4 properties and data streams. Reports are queried by property, while host and page dimensions are used to associate metrics with canonical site pages.
- Store provider identifiers separately from credentials. Installation administrators may save OAuth application credentials through the dashboard; secrets and refresh tokens must be encrypted at rest. Environment variables remain an optional advanced override and take precedence when configured.
- Prefer on-demand cached reports first, then add scheduled snapshots where trend history, quota control or performance requires them.

## Safeguards
- Only register sites that the operator owns or is explicitly authorized to monitor.
- Request the narrowest provider scopes possible; Google Analytics integration starts read-only.
- Validate OAuth state, protect callback routes, encrypt tokens, support revocation and never expose credentials or tokens in logs, URLs, views or test fixtures.
- Treat analytics and page metadata as potentially sensitive. Do not collect unnecessary personal data and do not present unavailable metrics as zero.
- Respect provider quotas, retry guidance and reporting limitations. Use bounded retries, backoff, caching and controlled background jobs.
- Keep real domains, account identifiers, credentials, user data, database dumps and installation-specific documentation outside the public repository.

## Proposed milestones
**A. Foundation — implemented:** login/logout, administrator authorization, protected dashboard, initial-admin seeder and feature tests.

**B. Site registry and service selection — implemented foundation:** site management UI, URL normalization and a separate area for optional integrations.

**C. Google Analytics OAuth — implemented:** guided Google Cloud application setup, encrypted installation credentials with an optional environment override, read-only authorization, encrypted token lifecycle, account/property discovery, verified metadata and disconnect flow.

**D. Page catalog:** sitemap or source connector, canonical URL normalization, sync history and deduplication.

**E. Analytics reports — implemented foundation:** site and page metrics, hostname filtering, bounded retries and short-lived cache. Quota telemetry and optional persistent snapshots remain planned.

**F. Monitoring dashboard — implemented foundation:** date ranges and a useful site overview. Comparisons, detailed sync health and page views remain planned.

**G. AI integration — implemented foundation:** installation-level provider, model and encrypted API-key settings, official setup guidance and a server-side text-generation adapter. Content-generation workflows, approvals and usage controls remain planned.

**H. Additional services:** introduce new providers only when there is a concrete use case and keep them optional.

## Development rules
- Never commit secrets, local data files, dumps or installation-specific documentation.
- Keep installation and integration screens actionable: use field-level help, safe examples, precise validation messages and an explicit next step when a feature is not yet connected automatically.
- Use safe synthetic domains and identifiers in tests. Request unknown integration details instead of guessing.
- Review database migrations for both SQLite and MySQL.
- No real provider API writes during automated tests or development; use HTTP fakes and explicit production activation gates.
- Do not change production data or run deployment commands without explicit authorization. Explain migrations and rollback implications.

Installation-specific facts may be documented locally in `PROJECT_PRIVATE.md`; that file is deliberately excluded from Git and is **not** part of the open-source specification.
