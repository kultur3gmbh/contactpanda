# ContactPanda Basic

ContactPanda Basic is the free foundation of the modular ContactPanda contact and company management system for WordPress. It includes the standalone contact database, WordPress and WooCommerce integration, registration, customer account views, companies, tags, consent, and optional Groundhogg compatibility. Since 0.6.0, Basic also contains the shared newsletter implementation, which is permanently unlocked by either a Newsletter license or ContactPanda Plus; license expiry affects updates and support only.

The installable plugin is stored in `contactpanda/`. Optional commercial modules are developed separately in the private ContactPanda Plus repository.

## Development principles

- WooCommerce orders and German Market invoices remain the authoritative historical records.
- ContactPanda never rewrites historical invoices during a migration.
- Groundhogg can remain the active data source, or contacts can be stored in the ContactPanda database.
- Migrations are non-destructive and repeatable unless an administrator explicitly starts a destructive reset.
- All user-facing text must be translatable. English source strings and German translations are stored in each plugin's own `languages/` directory.
- Public Basic interfaces remain backward compatible with supported ContactPanda Plus versions.

## Checks and release packages

Run:

```sh
./tools/check.sh
./tools/build-release.sh
```

`check.sh` validates PHP and JavaScript syntax and key plugin metadata. `build-release.sh` creates installable ZIP files from committed source files in `dist/`.

See [Cloud setup](docs/CLOUD_SETUP.md), [testing](docs/TESTING.md), and [release process](docs/RELEASES.md) for details.

