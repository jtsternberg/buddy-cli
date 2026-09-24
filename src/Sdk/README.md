# Vendored Buddy.works PHP SDK

Copied from [buddy-works/buddy-works-php-api](https://github.com/buddy-works/buddy-works-php-api) 1.4.0 (commit 3f40e7439d690f632d294707e42f9cb4d61c81ed), licensed Apache-2.0 (see `LICENSE`).

Upstream is unmaintained and pins `guzzlehttp/guzzle ~6.0`, which blocks security fixes only shipped in Guzzle 7+. Vendoring lets buddy-cli require Guzzle 7 directly.

Changes from upstream:

- Namespace `Buddy\` renamed to `BuddyCli\Sdk\` so it cannot collide with the upstream package if a consumer also installs it.
- `Buddy::$client` is `protected` (was `private`) so `BuddyCli\Api\ExtendedBuddy` can share the client without reflection.
- Reformatted by the project's Pint config.
