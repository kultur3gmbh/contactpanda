# ContactPanda Basic repository instructions

## Scope

This repository contains the free ContactPanda Basic WordPress plugin in `contactpanda/`.

Do not edit `work/`, `outputs/`, or generated release ZIP files. Those paths are intentionally excluded from version control.

## Product invariants

- Do not change historical WooCommerce orders, German Market invoices, or source-system records during imports.
- Groundhogg mode and standalone ContactPanda mode must both remain supported.
- Migrations must be non-destructive, repeatable, and deduplicated by stable source identifiers.
- Destructive operations require administrator capability checks, nonces, explicit confirmation, and a recoverable path where practical.
- Do not embed customer data, site credentials, API keys, email lists, or production database dumps in the repository or tests.
- Use sanitized fixtures for tests.

## Compatibility

- Keep ContactPanda Basic fully usable without ContactPanda Plus.
- Preserve stable extension points used by supported ContactPanda Plus versions.
- Optional integrations must feature-detect their dependencies and disappear cleanly when unavailable.
- Never assume the default `wp_` table prefix.

## Internationalization and presentation

- Source strings are English and use the `contactpanda` text domain.
- Update the POT and German PO/MO files when user-facing strings change.
- Keep CSS in the existing dedicated stylesheets; avoid large inline style blocks.
- Reuse the ContactPanda admin design system and accessible WordPress controls.

## Verification

Before handing off a change:

1. Run `./tools/check.sh`.
2. Test database upgrades from the previous released schema when schema code changes.
3. Test both standalone and Groundhogg data-source modes for affected features.
4. Test with optional dependencies absent as well as active.
5. Build release ZIPs only when the user explicitly requests a release or version creation.

## Versioning and live sites

- Do not change plugin version numbers without explicit user approval.
- Do not install or modify a live WordPress site without explicit user approval for that deployment.
- Release artifacts are generated, not committed.

