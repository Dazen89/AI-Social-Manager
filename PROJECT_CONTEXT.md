# Monitoring Hub — Public project context

_Status: authentication, site registry, Google Analytics and Google Search Console OAuth and on-demand reports, AI integration, X OAuth connection, manual/scheduled FoOlSlide analysis, opt-in automatic X delivery and bounded post-metric snapshots are implemented foundations. Live publishing remains disabled by default and requires explicit production activation. Features not marked implemented are planned._

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
5. **Google Search Console connection and on-demand reporting — implemented foundation:** optional guided OAuth setup with the read-only `webmasters.readonly` scope, encrypted credentials and tokens, accessible-property discovery, hostname-aware suggestions, explicit property selection, token refresh and revocation. The site overview now reads finalized Search Analytics web data for the registered host, caches it for one hour, and shows aggregate metrics plus bounded top-page and top-query lists. Scheduled snapshots and deeper reports remain planned.
6. **Page catalog — planned:** import canonical pages from an approved sitemap or read-only source connector, normalize URLs and prevent duplicates. Analytics rows are metrics, not the canonical source of website pages.
7. **Measurement — implemented foundation:** query Google Analytics Data API on demand through one batched request, filter by the registered hostname and cache responses for ten minutes. Query finalized Search Console Search Analytics data on demand for the site's host, with bounded requests and a one-hour cache. Expose unavailable or delayed metrics explicitly. Scheduled snapshots and historical persistence remain planned.
8. **Site-focused dashboard — implemented foundation:** each connected site has a 7/28/90-day overview with traffic totals, a same-length previous-period comparison, daily sessions, pages, acquisition channels, devices and countries. Richer page drill-down and provider sync history remain planned.
9. **AI provider integration — implemented:** administrators can configure multiple OpenAI or Anthropic connections, choose a supported model, save each API key encrypted at rest and switch the single active integration. The verified active integration can generate an on-demand structured interpretation of GA4 data and, when available, finalized Search Console data with evidence, priorities, actions, cautions and explicit limitations. Reports and source snapshots are encrypted; URL query strings are removed and Search Console query rows are limited and screened before AI submission. Per-request token usage is recorded without storing prompts in usage logs. Exact provider billing is not inferred from tokens; OpenAI's organization Costs API requires a separate privileged Admin API key.
10. **Additional adapters — future:** other analytics, monitoring, content and social services may be introduced behind configurable contracts without coupling the core domain to one provider.
11. **Social publishing — implemented foundation:** administrators can configure one X OAuth 2.0 confidential client, authorize and verify the installation's publishing account with PKCE, and disconnect/revoke access. Credentials and user tokens are encrypted. Production-only automatic publishing is gated by an explicit environment setting, per-site enablement and per-source promotional authorization; it uses the official post-create endpoint, claims each due post once and never automatically retries an uncertain delivery. Published IDs and outcomes are retained. Public post metrics can be collected as bounded snapshots for future AI analyses. Production deployment and live-write validation remain separate operational steps; no real X writes are performed in development or tests.
12. **Editorial plan and FoOlSlide import — implemented foundation:** each site has a protected monthly calendar of proposed, approved and published X posts with AI rationale. A chapter-review area on the same page replaces the separate navigation tab for releases. Administrators can run the same import-and-analysis pipeline manually or configure a per-site source polling schedule (6/12/24-hour interval, anchor time and IANA timezone). Every run performs bounded read-only FoOlSlide import and AI analysis even when no new chapters arrive; existing AI drafts may be rescheduled, while manual edits and approved/published posts are preserved. The AI receives available GA4, Search Console and collected X post metrics; it suggests a cautious post cadence and up to three posts per analysis. The operator must explicitly authorize each source for automatic promotion; hidden, excluded, invalid-URL and off-source chapters are not publishable. Public sources require HTTPS and the registered site's public host; local development alone permits a `http://localhost` source on a non-privileged port. Publishing is disabled by default and cannot run in local development.

## Domain principles
- A site is shared within the single private administrative workspace; this project does not introduce multi-tenancy prematurely.
- The setup experience must be self-explanatory for third-party installations: every provider field should state whether it is required, what it identifies, where to find it and whether it contains a secret. Link to official provider documentation where useful and never assume prior analytics expertise.
- Provider identities and OAuth connections are separate from the sites and properties they can access, so one authorized connection may serve multiple owned sites.
- A Google Analytics account can expose multiple GA4 properties and data streams. Reports are queried by property, while host and page dimensions are used to associate metrics with canonical site pages.
- Search Console properties may identify an entire domain (`sc-domain:`) or one URL prefix. Keep their identifiers separate from GA4 properties and require the administrator to confirm the suggested match.
- Store provider identifiers separately from credentials. Installation administrators may save OAuth application credentials through the dashboard; secrets and refresh tokens must be encrypted at rest. Environment variables remain an optional advanced override and take precedence when configured.
- Prefer on-demand cached reports first, then add scheduled snapshots where trend history, quota control or performance requires them.

## Safeguards
- Only register sites that the operator owns or is explicitly authorized to monitor.
- Request the narrowest provider scopes possible; Google Analytics and Search Console integrations are read-only.
- Validate OAuth state, protect callback routes, encrypt tokens, support revocation and never expose credentials or tokens in logs, URLs, views or test fixtures.
- Treat analytics and page metadata as potentially sensitive. Do not collect unnecessary personal data and do not present unavailable metrics as zero.
- Respect provider quotas, retry guidance and reporting limitations. Use bounded retries, backoff, caching and controlled background jobs.
- Keep real domains, account identifiers, credentials, user data, database dumps and installation-specific documentation outside the public repository.

## Proposed milestones
**A. Foundation — implemented:** login/logout, administrator authorization, protected dashboard, initial-admin seeder and feature tests.

**B. Site registry and service selection — implemented foundation:** site management UI, URL normalization and a separate area for optional integrations.

**C. Google Analytics OAuth — implemented:** guided Google Cloud application setup, encrypted installation credentials with an optional environment override, read-only authorization, encrypted token lifecycle, account/property discovery, verified metadata and disconnect flow.

**D. Google Search Console OAuth — implemented:** guided Google Cloud and Search Console setup, optional reuse of an OAuth client, encrypted installation credentials with an optional environment override, read-only authorization, accessible-property discovery, verified selection and disconnect flow.

**E. Page catalog:** sitemap or source connector, canonical URL normalization, sync history and deduplication.

**F. Analytics reports — implemented foundation:** GA4 and Search Console site metrics, hostname filtering, bounded retries and short-lived caches. Deeper Search Console reporting, quota telemetry and optional persistent snapshots remain planned.

**G. Monitoring dashboard — implemented foundation:** date ranges, same-length period comparison, a useful site overview and on-demand AI interpretation. Detailed sync health and page views remain planned.

**H. AI integration — implemented foundation:** installation-level provider, model and encrypted API-key settings, multi-connection summary with one active integration, official setup guidance, structured GA4/Search Console insights when both sources are available, and token-usage telemetry. Monetary budget enforcement, content-generation workflows and approvals remain planned.

**I. Additional services:** introduce new providers only when there is a concrete use case and keep them optional.

**J. Social publishing — implemented foundation:** guided X Developer setup, encrypted installation credentials, OAuth 2.0 PKCE account authorization, verified account identity and revocation; source-level promotional authorization, AI approval under an explicit production gate, due-post delivery with uncertain-outcome quarantine, and bounded public post-metric snapshots. Live delivery still requires production configuration and operational validation.

## Development rules
- Never commit secrets, local data files, dumps or installation-specific documentation.
- Keep installation and integration screens actionable: use field-level help, safe examples, precise validation messages and an explicit next step when a feature is not yet connected automatically.
- Use safe synthetic domains and identifiers in tests. Request unknown integration details instead of guessing.
- Review database migrations for both SQLite and MySQL.
- No real provider API writes during automated tests or development; use HTTP fakes and explicit production activation gates.
- Do not change production data or run deployment commands without explicit authorization. Explain migrations and rollback implications.

Installation-specific facts may be documented locally in `PROJECT_PRIVATE.md`; that file is deliberately excluded from Git and is **not** part of the open-source specification.
