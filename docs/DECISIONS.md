# Product decisions

This document records stable decisions that must survive individual conversations and cloud tasks.

## Product identity

- Product name: ContactPanda.
- Developer: Kultur3 GmbH.
- The free WordPress plugin is displayed simply as `ContactPanda`; `Basic` is an internal tier description, not a second product name in the plugin list.
- Basic and Plus are separate installable plugins maintained in separate repositories.

## Commercial feature model

- ContactPanda is the free foundation. ContactPanda Plus is one installed extension whose capabilities are controlled by durable feature entitlements rather than hard-coded tariff names.
- Canonical Plus entitlements are `advanced_newsletter`, `forms_editor`, `automations`, `advanced_integrations`, and `form_migration`.
- A complete `plus` grant includes all five canonical entitlements. Historical `newsletter`, `forms`, `flows`, `integrations`, and `migration` grants are compatibility aliases and must continue to work.
- Activating the Plus plugin alone does not unlock paid capabilities. A Newsletter license, Forms license, or complete Plus license may all use the same installed Plus package.
- A normal license expiry never disables an installed capability. It ends updates, support, and new migration or compatibility profiles only. Cached grants remain effective while the license service is unreachable.
- License checks have a seven-day grace period. From day eight, ContactPanda shows a persistent administrator warning and keeps retrying without interrupting frontend, sending, forms, or automations.

## Commercial packages and prices

The public product path is `Free → Plus Complete → Pro → Business or Members → All-in`. Newsletter and Forms remain separately purchasable Plus capabilities even though complete Plus includes both.

Annual prices for one website and one twelve-month term are:

- ContactPanda Free: EUR 0;
- Plus Newsletter: EUR 49;
- Plus Forms: EUR 59;
- Plus Complete: EUR 99;
- Pro, including Plus Complete: EUR 159;
- Business, including Pro and Plus Complete: EUR 249;
- Members, including Pro and Plus Complete: EUR 279;
- All-in: EUR 399, including Plus Complete, Pro, Business, Members, and the bonus products explicitly assigned to the offer.

The installable plugins may remain technically separate. Publicly, Pro, Business, and Members are cumulative packages. Business and Members depend on Pro. Pro without Plus remains a technically valid compatibility state but is not a commercial offer.

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
- Basic also owns unlimited manually started newsletters, recipient selection, a reduced responsive editor, post content, test delivery, personalization, duplication, list-level summary statistics, unsubscribe and preference handling, and delivery through WordPress or the configured SMTP layer.
- Basic does not provide scheduled sending, a reusable named template library, detailed analytics, attachments, gallery content, WooCommerce newsletter content, communication buttons, automatic newsletters, or flows.
- `advanced_newsletter` adds scheduled sending, reusable named templates, attachments, gallery content, WooCommerce content, phone/email/WhatsApp buttons, and detailed analytics. Detailed analytics remain a development task; existing list-level counts are the free summary.
- ContactPanda Plus owns the full visual form builder and may connect forms to automations and additional processing targets.
- External form connections and the reduced newsletter signup remain free. `forms_editor` unlocks the visual responsive form builder. `form_migration` adds controlled discovery, compatibility reporting, preview, testing, replacement logging, and rollback. Source forms and submissions are never deleted without explicit confirmation.
- Newsletter mailings and reusable templates are separate concepts. A free mailing may own an internal content snapshot and may be duplicated; organizing named reusable templates is an advanced feature.
- Plus uses Basic's public contact and newsletter contracts and must not duplicate the Basic newsletter schema or runtime.

## License upgrades

- Supported upgrade paths are Newsletter or Forms to Plus Complete, then Pro, then Business or Members, and finally All-in.
- Every upgrade combines the unused time value of eligible licenses, starts a fresh twelve-month term, and does not inherit the old expiry date.
- The credit formula is `paid license price × remaining days ÷ original term days`. The amount due is the new annual price minus all eligible credits.
- Price paid, original period, credited amount, target term, provider transaction, and calculation basis must be stored as auditable commercial events on the license server; the WordPress client only consumes the resulting entitlements and dates.
- An early price may remain the renewal price while the subscription is renewed without interruption. The preserved price and its eligibility conditions are immutable commercial history, not a mutable runtime entitlement.

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
- Free also includes advanced email and phone handling, import/export, deduplication and merging, read-only WooCommerce customer and order views, simple Newsletter Plugin and Groundhogg person migration, external form adapters, and the reduced newsletter including signup, unsubscribe, personalization, and test delivery.
- Plus owns advanced newsletter and form features, reusable templates, detailed statistics, scheduled delivery, attachments, galleries and product content, communication buttons, SMTP configuration, delivery logs, bounce management, automations, SMS/WhatsApp, advanced integrations, and paid migration profiles.
- Pro owns the shared commercial customer layer: advanced customer management, Support Desk, contracts, recurring subscriptions and payments, the protected customer portal, customer groups, and the common authorization model. Pro includes the Plus Complete commercial grant.
- Business depends on Pro and owns projects, tasks, phases, responsibilities, due dates, reminders, time tracking, budgets, files, customer collaboration, corrections, approvals, capacity planning, and project reports. It must reuse Pro customers, authorization, contracts, payments, and portal services instead of defining parallel models.
- Members depends on Pro and owns member groups, memberships, dues, organization-specific responsibilities, and member access. It must reuse Pro authorization, contracts, recurring payments, and portal services.
- Existing table names, record identifiers, source identifiers, and newsletter histories are not moved merely to enforce these code boundaries.

## Authorization and audit architecture

- Basic must expose one family-wide authorization contract used by the WordPress administration, customer portal, REST API, MCP, and future mobile applications.
- WordPress roles remain identity containers but are not sufficient as the complete authorization model.
- Permissions are separate for viewing, creating, editing, deleting, exporting, merging, bulk actions, and accessing protected fields.
- Permission scopes may restrict access by module, team, assignment, record, project, or future tenant.
- Internal employees, support agents, project participants, customer contacts, portal users, and later members are distinct principals or assignments.
- Sensitive fields, exports, merges, and bulk actions require explicit permissions. A broad menu capability must never be treated as authorization for every underlying action.
- Material changes must produce an audit event identifying actor, action, object, timestamp, and relevant before/after information without logging secrets.
- Portal users see only records and fields explicitly released to them. Business extends the Pro permission vocabulary for projects, tasks, time entries, budgets, files, comments, corrections, and approvals. Members extends it for groups, memberships, dues, and organization responsibilities.

## API and mobile preparation

- The ContactPanda family exposes stable, versioned APIs through a shared transport and shared authorization service. Family extensions register domain resources and actions without implementing separate authentication rules.
- Token-based sessions must be revocable per device or session. Web, REST, MCP, and future mobile clients call the same policy checks.
- Pro is the API foundation for customers, support, contracts, subscriptions, payments, portal data, and read-only WooCommerce order views. Business adds projects, tasks, dates, time entries, comments, files, and approvals.
- WooCommerce orders are initially read-only through the API. Any later write operation requires a separate explicit permission and must use WooCommerce public APIs.
- API contracts must allow later push notifications and optional offline synchronization. Stable UUIDs, update timestamps, revision fields, pagination, and conflict-aware writes are required for synchronizable resources.
- Sensitive fields and actions remain hidden unless the authenticated principal has the same explicit permission that the WordPress interface would require.

## Releases and deployment

- Basic and Plus use the same version number for coordinated releases.
- The next coordinated family milestone for Basic, Plus, Pro, and Business is `0.8.40`. Intermediate `0.8.3x` builds are permitted only when a separately testable correction or migration step makes them useful.
- ContactPanda Basic is distributed through its public WordPress/GitHub update channel.
- ContactPanda Plus remains a manual ZIP update unless its private developer test channel is explicitly enabled.
- New files or release versions are produced only after an explicit go from the user.
- Installation on live sites requires a separate explicit approval.

## Uninstallation

- ContactPanda-owned data is retained by default on uninstall.
- An explicitly enabled administrator cleanup may delete only ContactPanda-owned tables, options, transients, and ContactPanda-specific metadata. It never deletes WordPress accounts or another plugin's records.
- Optional ContactPanda packages and integration adapters feature-detect their dependencies and fail closed without disrupting the host system.

