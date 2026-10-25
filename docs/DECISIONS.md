# Product decisions

This document records stable decisions that must survive individual conversations and cloud tasks.

## Product identity

- Product name: ContactPanda.
- Developer: Kultur3 GmbH.
- Basic display positioning: User-Friendly CRM for WordPress.
- Basic and Plus are separate installable plugins maintained in separate repositories.

## Data ownership

- Administrators choose Groundhogg or the ContactPanda database as the active contact data source when Groundhogg is present.
- Without Groundhogg, ContactPanda creates and uses its own tables.
- WooCommerce remains the order source.
- German Market remains the invoice source.
- Historical orders and invoices are never duplicated or altered by ContactPanda.
- ContactPanda deactivation or uninstallation never deletes WordPress users, roles, WooCommerce customers, orders, addresses, payments, products, downloads, invoices, or external calendar/event records. Native WordPress and integration functionality must continue without ContactPanda.
- Accounts created through a ContactPanda-assisted registration remain ordinary WordPress accounts after ContactPanda is removed.

## Migrations

- Groundhogg, WooCommerce, and Newsletter plugin migrations are non-destructive.
- Source records remain unchanged.
- Repeated imports merge by stable identifiers and email addresses as appropriate.
- Imported historical broadcasts are read-only archives, not editable email templates.

## Forms and newsletter

- Basic owns the reduced newsletter signup form, including consent and double-opt-in rules.
- ContactPanda Plus owns the full visual form builder and may connect forms to automations and additional processing targets.
- Plus uses Basic's public contact and newsletter contracts and must not duplicate the Basic newsletter schema or runtime.

## Customer portal

- Basic owns the WooCommerce-independent customer profile portal and the reduced, secure email-link preference centre.
- Every authenticated WordPress user can access the Basic portal, regardless of how the account was created and whether WooCommerce or another ContactPanda package is active. Public account creation and registration policy are separate concerns.
- Newsletter and Seasons registrations never create a WordPress account automatically. A WordPress account is created only through an explicit customer or other authenticated-account registration chosen by the person.
- Customer registration first resolves an existing ContactPanda person by a stored user link or one exact normalized email match. After account email confirmation it links and classifies that existing record instead of creating a duplicate. Ambiguous matches or an existing link to another user require recovery or administrator review.
- Becoming a customer does not imply marketing consent and never clears an unsubscribe, bounce, complaint, or permanent block.
- Shortcode, WordPress block, WooCommerce account integration, and optional Avada, Elementor, and Divi elements are presentation adapters over the same Basic renderer, validation, consent, double-opt-in, tag, subscription, and persistence services.
- A full profile requires an authenticated WordPress user. A scoped and expiring personal email link exposes only the reduced preferences permitted for that purpose.
- WooCommerce contributes shop data and shop functions when active but is not required for the ContactPanda portal.
- Panda Seasons uses Basic's preference-link contract for calendar subscriptions and unsubscribe choices.
- Plus may extend person and company account functionality and consume portal events in automations, but Basic remains usable without Plus.

## Product boundaries

- Basic owns CRM contacts, companies, consent, events, Groundhogg compatibility, and the shared newsletter schema and runtime.
- Plus owns marketing automation, full forms, messaging, delivery providers, and migration adapters.
- Pro owns the Support Desk and may add support requests and ticket history to the Basic customer portal.
- Business owns projects, tasks, reviewed imports, reports, and later commercial project administration.
- Existing table names, record identifiers, source identifiers, and newsletter histories are not moved merely to enforce these code boundaries.

## Releases and deployment

- Basic and Plus use the same version number for coordinated releases.
- ContactPanda Basic is distributed through its public WordPress/GitHub update channel.
- ContactPanda Plus remains a manual ZIP update unless its private developer test channel is explicitly enabled.
- New files or release versions are produced only after an explicit go from the user.
- Installation on live sites requires a separate explicit approval.

## Uninstallation

- ContactPanda-owned data is retained by default on uninstall.
- An explicitly enabled administrator cleanup may delete only ContactPanda-owned tables, options, transients, and ContactPanda-specific metadata. It never deletes WordPress accounts or another plugin's records.
- Optional ContactPanda packages and integration adapters feature-detect their dependencies and fail closed without disrupting the host system.

