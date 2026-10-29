# Shared license center contract

## WordPress client

ContactPanda Basic owns the single **Settings → Licenses** screen. Active family
plugins register their product label, installed version, package entitlement and
optional module entitlements through `contactpanda_license_products` and
`contactpanda_license_modules`.

An administrator may activate multiple individual product keys or one MasterKey.
The raw key is sent once to the HTTPS activation endpoint and is never persisted.
Only the returned activation ID, activation token, masked label, signed
entitlements, dates and limits are stored. Product and plan permissions returned
for a MasterKey are authoritative; the client never infers additional grants.

The license screen shows, per installed product, the license status, installed
version, update term, support term and website usage supplied by the server.
Panda Seasons remains usable without ContactPanda; when Basic is active it joins
the same overview without adding a competing license endpoint.

## Key rotation

Rotating a key on the license server invalidates every registered website and
activation token belonging to the previous key. At the next check the WordPress
client clears that activation token, marks the connection as disconnected and
requires activation with the new key. Cached feature grants remain subject to the
existing product policy, but updates and support require a valid activation.

## License server and customer portal responsibilities

The Panda License application owns key disclosure and rotation. Authorized
administrators can view a key in its protected detail view and generate a
replacement. Customers can do the same for their own licenses in the customer
portal. Both interfaces must audit access and rotation and must never return keys
through the public WordPress update metadata.

## Deferred account connection

A future “Connect license account” flow may exchange a short-lived authorization
code for product activations. It must use the same stored activation records and
must not create a second entitlement model. This account connection is documented
only and is not implemented in the current client.
