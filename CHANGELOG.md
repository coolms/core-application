# Changelog

All notable changes to `coolms/core-application` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versioning is described in `CONTRIBUTING.md` -- read it before assuming what a
major number means here.

!! Entries dated before 2026-09-01 were **reconstructed** from tags and commit
history when this file was created. Every entry after that is written in the
same commit as the change it describes.

## Unreleased

### Added

- `RetentionPruneRunner::preview()` gives each pruner's `population` alongside
  its `prunable` count: the number from `countPopulation()` when the pruner
  implements `coolms/core`'s `RetentionPopulationInterface`, and null -- unknown,
  never zero -- when it does not. Requires the `coolms/core` that ships the
  interface.

### Removed
- The backup ENGINE: `Backup\BackupRunner`, `Backup\BackupWriter`,
  `Backup\BackupReader`, `Backup\BundleArchiver`, `Backup\BackupTableRegistry`
  and `Backup\ContributorRestoreOrder`, with their four tests. It writes bundle
  files and restores rows into every module's tables, which makes it a
  module's to own. The contracts stay in `coolms/core`, now including the three
  ports a feature asks a bundle through, so the sync surface can produce a
  snapshot without depending on the module that answers.
- `Config\FileConfigWriter`, `Config\DbConfigWriter` and
  `Config\ChainedConfigWriter`: the write half of the config store. It wrote
  YAML into the application's `config/modules/generated` and rows into a table
  the platform installed, for data only a module ever saves. The port
  (`Config\ConfigWriterInterface`) stays; the stores are the Settings
  module's.
- `Config\ChainedConfigLoader` now reads through
  `CoolMS\Core\Config\ConfigOverrideReaderInterface` and gets the stored
  config array back, instead of an entity from a repository port. Same
  precedence, same fall-through to the files.
- `ChangeFeed\SyncChangeApplier` and `ChangeFeed\SyncBlobRegistry`: they
  apply and serve what a change feed holds, and the feed's rows are an
  installation's. The two declarations a module makes to a feed stay in
  `coolms/core`.
- `Outbox\OutboxRelay`, `Outbox\OutboxMaintenanceService` and the two
  retention pruners for the outbox and the processed-message journal: they
  drive tables, and a table belongs to whatever installs it, not to the
  platform's orchestration layer.

### Changed
- `Outbox\IdempotentRunner` is now `Messaging\IdempotentRunner`, over the
  platform's `Messaging\ProcessedMessageStoreInterface`. It orchestrates and
  writes nothing itself, which is why it stays.

### Added
- `Config\ReadOnlyConfigWriter` and `Config\NoConfigOverrides`: what the
  platform answers with when no module owns a config store -- a save that
  refuses out loud rather than reporting a write that went nowhere, and a
  reader that never finds a row. Aliased by `coolms/core-bundle`'s
  `ConfigStoreFallbackPass` only when nothing else has claimed the ports.
- `ApiManifest::$ui`: the host contracts in force -- the active theme's
  declaration and the module entries matched against it, what a host mounts.
  A contributor's `ui` section reached the builder and was dropped before this
  line, because the manifest carries only the sections it names.
- `Outbox\OutboxRelay` records a heartbeat after every completed pass through an
  optional `CoolMS\Core\Outbox\RelayHeartbeatInterface` (and an optional clock
  for its timestamp): the batch asked for and the rows published, an empty pass
  included. A pass whose claim fails leaves no beat, which is the right record
  of it. Both arguments default to null, so an application that wires neither
  keeps the relay it had.
- `Health\LivenessRunner`: collects every `CoolMS\Core\Health\LivenessProbeInterface`
  (tag `coolms.diagnostics.probe`) and returns their states, failing ones first,
  with the count of required dependencies that were asked and stayed silent --
  the number that should be zero.
  A probe that throws is caught and reported as a failing row naming the
  exception, so one broken probe never blinds an operator to the others, and the
  fault is attributed to the probe rather than silently to the dependency.

### Added

- Declares `support` -- `issues` and `source` -- so a page imported from this
  package, and the catalogue, know where a correction is filed. Packagist filled
  the gap from GitHub when the manifest was silent; the declared field is the
  one that holds on any registry.

## 2.0.0-alpha2 - 2026-09-09

### Added

**`DocumentApiManifest` carries `spacesAvailableUrl` and `spaceEnablementUrl`.**
The Document module gained an admin surface for turning document handling on or
off per site, and the FE needs both endpoints at boot.

Appended with empty-string defaults, so nothing that constructs this DTO
positionally breaks. The struct already documented itself as reserved for
exactly this growth.

Carried in the manifest rather than derived by the client from `spacesUrl`:
concatenating `/available` onto that would couple the client to a URL shape the
router owns, and would keep working until the route moved -- then fail as a 404
the UI reports as "no sites available", which is indistinguishable from the true
empty answer.

### Changed

!! **This package is now `coolms/core-application`, and its namespace nests under the
domain root.**

| before | after |
|---|---|
| `coolms/core-module` | `coolms/core-application` |
| `CoolMS\CoreModule\` | `CoolMS\Core\Application\` |

`coolms/core-module` is abandoned and points here. Update the requirement and the
imports together; nothing else about the classes changed -- same names, same
contracts, same requires.

The rule underneath: a suffix names a superstructure and the absence of one
names the subject, so the domain package keeps the root prefix and every layer
above it carries a segment equal to its suffix.

- `ContextContributorInterface` moved down to `coolms/core`. The name here
  survives as a subtype with nothing added, so an implementation of it still
  satisfies a parameter typed against the parent.
- The document manifest carries the filter-audience entity, so the admin can
  compare against a value the server owns instead of a constant compiled into a
  published bundle.
- Comments, docblocks and changelogs are ascii and no longer carry internal
  slice ids.
- Development-only files are export-ignored.

## 2.0.0-alpha1 - 2026-09-01

**A pre-release. It carries no compatibility promise**, which is the honest
statement of where the platform is: the shape is still moving, and a stable tag
would be a promise that cannot be kept yet.

Composer will not install it under default stability. Set

```json
"minimum-stability": "alpha",
"prefer-stable": true
```

in your root `composer.json`, then:

```
composer require coolms/core-application:^2.0 coolms/core-doctrine:^2.0
```

`prefer-stable` keeps every other dependency of yours on its newest stable
release, so this loosening applies to what actually needs it and nothing else.

!! **The adapter is part of the command, not an extra.** `coolms/core-application`
requires a persistence implementation, which is a virtual package: nothing
provides it until you choose an implementation, and Composer reports the
virtual name, which reads like a broken package rather than a missing
argument.

!! **A per-package flag is not enough here.** `composer require
coolms/core-application:^2.0@alpha` admits the alpha of the package it names and
**nothing behind it**, so the siblings this one pulls in still fail to resolve.
Composer reports it against the sibling, not against what you asked for.

A bare `composer require coolms/core-application` does not install the wrong thing
quietly -- it refuses, naming `coolms/core-persistence-implementation`. That
is a virtual package, so the message reads like a broken dependency rather
than a missing argument.

Releases are suspended while development is moving fast and there are no
external consumers of these packages. This tag establishes the baseline the
documentation describes; nothing follows it until somebody outside the project
installs one, at which point the release policy resumes.

### Added: the config loader reads packages' `config/` too

`FileConfigLoader` scans every registered bundle's `config/modules` in addition
to the application's, so a package ships a definition instead of asking an
integrator to install one into the application's own config.

The application is scanned FIRST. This loader returns the first match, so first
is highest priority and an installation can always override what a package
ships -- never the other way round.

### Fixed: the installation command in the readme names the adapter

`composer require coolms/core-application` on its own cannot resolve. This package
requires a virtual persistence-implementation package, and only an adapter
provides one, so Composer reports that the virtual package "could not be found
in any version" -- which reads like a broken package rather than a missing
argument.

The readme now leads with the command that works:
`composer require coolms/core-application coolms/core-doctrine`.

### The v2 generation -- a version number, and nothing else

This release moves `coolms/core-application` to `2.0.0` **without a single change to its
code**. Nothing was added, removed, renamed or fixed.

Every CoolMS platform package -- everything that requires `coolms/core` --
shares a major number, so that a set of packages carrying the same major is
known to work together. The whole set crosses to v2 at once, and this package
has nothing else in the crossing.

Before the shared major existed, `composer require coolms/entity-bundle`
resolved the entire set backwards onto its first generation -- including a
template engine from before output encoding existed -- and Composer reported
success. A shared major makes that resolution unreachable by accident.

**Upgrading: widen your constraint from `^1.0` to `^2.0`. There is nothing
else to do.** No class, signature, or behaviour changed. Breaks are announced as
deprecations in a minor and removed at a generation boundary; this boundary
removes none, because there were none to remove.

The standalone libraries published alongside the platform -- `coolms/rql`,
`coolms/rql-doctrine`, `coolms/dtmpl`, `coolms/dtmpl-bundle` -- do **not** take
this major. They have users who never touch CoolMS, and their numbers answer to
their own APIs.

### Changed: sibling constraints move to the v2 generation

- `coolms/core`: `^1.0` to `^2.0`
- `coolms/core-persistence-implementation`: `^1.0` to `^2.0` -- the virtual
  package `coolms/core-doctrine` provides
- `coolms/core-doctrine` (development): `^1.0` to `^2.0`


The constraints on `coolms/rql` and `coolms/rql-doctrine` are unchanged. Those
are standalone libraries and do not take the platform generation.

## 1.0.2 - 2026-08-17

### Fixed

Require an implementation of the persistence seam for development. The package
depends on the virtual `coolms/core-persistence-implementation`, which nothing
satisfied in a bare checkout, so `composer install` in this repository alone
could not resolve.

## 1.0.1 - 2026-08-17

### Fixed

Raise the `symfony/translation-contracts` floor to 3.4.2. The declared floor
admitted a version without the interface this package calls.

## 1.0.0 - 2026-08-17

First release. The platform composition layer over `coolms/core`: the
application services that wire the kernel contracts to whatever implements
them -- config loading, backup and restore, the API manifest, outbox dispatch,
retention, and label resolution.
