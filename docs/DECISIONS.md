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

## Migrations

- Groundhogg, WooCommerce, and Newsletter plugin migrations are non-destructive.
- Source records remain unchanged.
- Repeated imports merge by stable identifiers and email addresses as appropriate.
- Imported historical broadcasts are read-only archives, not editable email templates.

## Forms and newsletter

- Form integrations belong to ContactPanda Basic rather than requiring ContactPanda Plus.
- In Basic, form integrations initially connect only to the newsletter signup workflow, including the existing consent and double-opt-in rules.
- ContactPanda Plus extends the same integration points with automations and additional processing targets; it must not duplicate the Basic form connectors.
- This boundary is scheduled for the next coordinated Basic and Plus update and is not yet implemented.

## Releases and deployment

- Basic and Plus use the same version number for coordinated releases.
- ContactPanda Basic is distributed through its public WordPress/GitHub update channel.
- ContactPanda Plus remains a manual ZIP update unless its private developer test channel is explicitly enabled.
- New files or release versions are produced only after an explicit go from the user.
- Installation on live sites requires a separate explicit approval.

