# ContactPanda customer portal plan

This document records the agreed architecture for an independent ContactPanda customer portal. It is a planning document; no portal implementation or database migration is implied by it.

## Product boundary

ContactPanda Basic owns the customer-facing profile and preference functionality. It must work without WooCommerce and remain fully usable when Plus, Pro, Business, Seasons, or a page builder is absent.

- **Basic** owns the authenticated profile portal, the reduced email-link preference centre, contact/profile validation, email-change confirmation, consent and subscription changes, data-export requests, deletion requests, and the public embedding contracts.
- **Plus** may extend the portal with advanced person/company account fields and may use the same form and event contracts in its visual form builder and automations.
- **Pro** owns the Support Desk and may contribute support requests and ticket history to the Basic portal. It does not own the general customer profile portal.
- **WooCommerce** contributes orders, billing and shipping addresses, payment methods, downloads, and other shop functions when active. It remains the authoritative source for those records.
- **Panda Seasons** contributes calendar subscriptions and uses the Basic preference-link contract in signup, double-opt-in, and daily calendar emails.

Newsletter signup, newsletter pre-registration, and Seasons calendar signup create or update a ContactPanda contact and its subscriptions only. They must never create a WordPress user account implicitly. A WordPress account is created only after the person deliberately chooses a persistent authenticated account, for example through WooCommerce customer registration or another explicitly offered account-registration workflow.

## Two access levels

### Authenticated customer portal

A configurable WordPress page such as “My data” or “Customer portal” renders the complete portal for every authenticated WordPress user, regardless of how the account was created and regardless of whether WooCommerce, Plus, Pro, Business, or Seasons is active. Authentication and WordPress account recovery remain WordPress responsibilities. Creating new WordPress accounts and deciding which public registration methods are offered is a separate product concern and is not a prerequisite for the portal.

Basic resolves the signed-in user to the corresponding ContactPanda contact through an explicit stored user link and, only when that link is absent, an exact normalized email match. The resolver must never expose a different person's record or silently merge ambiguous records. If no ContactPanda contact exists, the portal remains available, shows the WordPress profile and empty subscription states, and creates or links a ContactPanda contact only through a defined, auditable Basic workflow.

## Customer account registration and existing contacts

When a person deliberately chooses “I want to become a customer” through WooCommerce or another account-registration workflow, ContactPanda checks whether the person already exists as a newsletter, Seasons, event, or other contact. The registration must reuse and link that person instead of creating a duplicate.

The safe sequence is:

1. Normalize the submitted email address and look first for an existing explicit WordPress-user link, then for exactly one contact with the same normalized email address in the selected ContactPanda data source.
2. Create or activate the WordPress/WooCommerce account through the responsible registration system, without copying historical orders or changing source records.
3. Confirm control of the email address before attaching the new WordPress user ID to an existing ContactPanda contact.
4. If one unambiguous existing contact is found, add the stable account link and the customer classification to that record. Preserve its original source, contact ID, consent history, subscriptions, tags, activities, suppression status, and Seasons registrations.
5. If no contact exists, create one ContactPanda contact with the verified account link and the appropriate customer source/classification.
6. If several records match or the contact is already linked to another WordPress user, do not merge or relink automatically. Stop the linking step and provide account recovery or an administrator review workflow.

Names, telephone numbers, or addresses may be shown as possible duplicate indicators for administrator review, but they are not safe enough for an automatic account link. Customer registration never grants newsletter consent and never clears an unsubscribe, bounce, complaint, or block status. Account email confirmation and newsletter double opt-in remain separate proofs with separate audit records.

The authenticated portal may provide:

- first name, last name, salutation, email address with confirmation on change, and telephone numbers;
- ContactPanda-owned addresses and, when WooCommerce is active, clearly identified shop billing and shipping addresses;
- consent history, newsletter subscriptions, calendar subscriptions, and unsubscribe status;
- a confirmed personal data export request;
- a confirmed and recoverable deletion request workflow;
- extension panels supplied by WooCommerce, Plus, Pro, Business, or Seasons.

### Reduced preference centre

Newsletter and Seasons recipients without a WordPress account use a personal, scoped, time-limited link from an email. This view must not expose the full profile merely because the link is known.

This reduced access is the normal path for newsletter-only and Seasons-only recipients. They are not prompted to create a WordPress account merely to manage subscriptions or unsubscribe.

It may provide only the fields and actions permitted by the link purpose, initially:

- newsletter and individual calendar subscriptions;
- global versus channel-specific unsubscribe choices;
- current consent and unsubscribe status;
- confirmation of a requested email-address change;
- an explicit path to request data export or deletion with an additional email confirmation.

Tokens must be random or cryptographically signed, contain no readable email address, be purpose-scoped, expire, support revocation, and be stored only as a hash when persisted. Sensitive actions require a fresh confirmation even when the preference link is otherwise valid.

## One renderer and one processing layer

All embedding variants call the same Basic renderer and the same server-side commands. They may select a layout or styling mode but must not implement their own validation, consent, double-opt-in, tag, subscription, or persistence logic.

Planned embeddings:

1. a shortcode;
2. a server-rendered WordPress block;
3. optional Avada, Elementor, and Divi adapter elements that feature-detect their builder;
4. the existing WooCommerce “My account” adapter;
5. extension panels registered through stable filters and actions.

The renderer accepts a context and capability set, for example authenticated portal, limited preference link, WooCommerce extension, or builder preview. Builder integrations are thin adapters only.

## Form and automation integration

Basic provides shared field definitions and commands for profile data, email-change confirmation, consent, subscriptions, export, and deletion requests. The reduced Basic newsletter form continues to use these services. Plus's visual form builder consumes the public field and validation contracts instead of copying portal forms.

Successful changes emit stable domain events after persistence, including profile changed, email change requested/confirmed, consent changed, subscription changed, export requested, and deletion requested. Plus automations may react to those events but cannot become necessary for the Basic portal to work.

Tags and consent records are updated only through the existing ContactPanda store contracts. Groundhogg mode and standalone mode remain supported. The selected source remains authoritative; no parallel write to both contact stores is introduced.

## WooCommerce decoupling

The current WooCommerce account class mixes ContactPanda profile rendering with WooCommerce endpoints. It should be separated incrementally into:

1. Basic profile/preference services and renderers with no WooCommerce dependency;
2. a WordPress page/shortcode/block controller;
3. a WooCommerce adapter that embeds the same renderer and contributes shop-only panels;
4. optional builder adapters.

No historical order, payment, invoice, download, or address record is copied or rewritten during this separation.

## Styling

Every embedding supports either the neutral ContactPanda design system or inherited theme/page-builder styling. Styling mode affects markup classes and presentation only. Accessibility, field semantics, validation messages, and actions remain identical.

## Data and migration constraints

The first implementation should reuse existing WordPress user links, ContactPanda contacts, consent records, metadata, and subscription data. Any new token, request, or subscription table must be additive, prefix-safe, repeatable, and tested from the previous released schema. Existing Groundhogg mappings and newsletter histories remain unchanged.

## Deactivation and uninstallation independence

ContactPanda may enhance WordPress, WooCommerce, event calendars, or other integrations only through runtime hooks and adapters. It must never make their native records or core functions dependent on ContactPanda remaining installed.

- Deactivation immediately stops ContactPanda hooks and scheduled work; native WordPress, WooCommerce, and calendar behaviour resumes without a migration or repair step.
- WordPress users, passwords, roles, customer accounts, WooCommerce orders, addresses, payment data, downloads, invoices, products, event records, bookings, and tickets are never deleted or rewritten by ContactPanda uninstallation.
- WordPress accounts originally created through a ContactPanda-assisted registration remain normal WordPress accounts after ContactPanda is removed.
- By default, ContactPanda-owned data also remains stored on uninstall so that reinstalling can recover it.
- The existing administrator-confirmed “delete ContactPanda data on uninstall” option may remove only ContactPanda-owned tables, options, transients, and ContactPanda-specific metadata. It must not delete WordPress users, external-plugin data, or historical source records.
- Plus, Pro, Business, Seasons, and Woo adapters must feature-detect Basic. If Basic is missing, they stop their ContactPanda functions cleanly instead of producing fatal errors or blocking the host system.
- A page that previously embedded the ContactPanda portal may lose that ContactPanda view after removal, but the surrounding WordPress page and all native account/shop/calendar functions continue to work.

## Implementation stages

1. Extract the profile and preference domain services from the WooCommerce-specific presentation without changing current behaviour.
2. Add the configurable portal page, authenticated renderer, shortcode, and central WordPress-user-to-contact resolver, including the verified existing-contact linking workflow for customer registration.
3. Add the reduced preference-link token service and preference centre.
4. Add the server-rendered block and thin Avada, Elementor, and Divi adapters.
5. Reconnect WooCommerce through an adapter and add extension panels.
6. Connect Seasons links and Plus form/automation events through public contracts.
7. Add export/deletion workflows with audit entries, confirmations, and recovery rules.
8. Verify deactivation and uninstall recovery with WooCommerce and supported calendar integrations both active and absent.

Each stage must be testable without WooCommerce and with optional plugins absent.
