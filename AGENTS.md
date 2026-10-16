# AI Social Manager — Instructions for coding agents

Read `PROJECT_CONTEXT.md` before proposing or implementing changes. Inspect the actual repository first: planned features are not necessarily implemented.

## Product and architecture
- General-purpose Laravel 13 application for social publishing, scheduling and post-level performance monitoring.
- Keep the product independent of any particular community, domain or content provider. Avoid premature multi-tenancy.
- Develop using PHP/Laravel conventions, migrations, tests, Laravel HTTP Client, Scheduler and database-backed jobs when needed.
- Support SQLite for local development and MySQL for production. Keep migrations portable and avoid vendor-specific queries where possible.
- Treat third-party publishing and analytics as optional, configurable adapters; do not assume API access or pricing.

## Privacy, security and repository hygiene
- **Assume this is a public repository.** Never commit `.env`, API keys, OAuth credentials, private URLs, connection strings, infrastructure details, user data, logs, database dumps, licensed content or private operational documentation.
- Only commit `.env.example` placeholders, generic sample data and non-sensitive tests. Check staged changes before committing.
- A local `PROJECT_PRIVATE.md` may be provided to agents running on that machine. It is intentionally ignored by Git. If absent, request missing integration details instead of inventing them; never copy private details into committed files, issues, commit messages or test snapshots.
- Never modify external source databases. Connect read-only where applicable, with least-privilege credentials.
- No real social API writes during automated tests or development; use fakes and explicit production activation/approval gates. Handle budgets, rate limits, retries, idempotency and errors.
- Protect the dashboard with authentication; no public signup. Support one initial admin and the controlled creation of more admins.
- Redirect tracking must use allowlisted destinations, avoid open redirects and minimize collection of personal data.
- Treat user-supplied content and promotional images as potentially sensitive; require explicit content eligibility/approval rules.
- Never overwrite environment configuration, production databases or persistent files during deployment. Explain safe migrations and rollback options.

## Working style
- Plan in small, reviewable increments. Before implementation, summarize the files to change and expected behavior.
- Provide local commands, tests, and deployment considerations separately. Never run production operations without explicit user approval.
- Keep infrastructure and hosting decisions configurable rather than hardcoded.
- Do not add Docker, Redis, multiple runtimes or paid dependencies without a concrete need.
