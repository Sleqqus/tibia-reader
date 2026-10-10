# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), with changes grouped by the commit that made them instead of by version, newest first. A commit can't contain its own hash, so each section is written in a later commit.

## [c6306fb] Harden and fix the examples (2026-10-10)

### Security

- The examples only run from the command line or PHP's built-in server and answer 404 under any other web server, so a web root that exposes `vendor/` doesn't expose them.
- `DatObject::printObjectInformation()` escapes its output. Text flags such as the market name come from the `.dat` file, so a crafted file could inject HTML into the page. The `printFileInformation()` methods escape the file name too.
- Query parameters are validated: an id, type or page that isn't a whole number in range gets a 400 instead of being cast to 0 or reaching the reader. Uncaught exceptions answer with a short 500 and leave the details, which can contain file paths, in the server log.

### Fixed

- The sprite browser didn't know the sprite count, so it linked pages past the last sprite and accepted page 0 or negative pages. It now stops at the last sprite and hides Prev/Next past either end.
- The object explorer shows a note for an id outside the file instead of a fatal `OutOfRangeException`.
- The outfit demo only sets mount 368 when the file has it, so it works for versions before 8.70.
- `check_anim.php` was a debug script that read every looktype on each web request. It is now a command-line tool that lists the looktypes without a moving frame group.

### Added

- `TIBIA_CLIENT_DIR` points the examples at any folder holding `Tibia.dat` and `Tibia.spr`, for installs from the Composer package, which has no `tests/data/`.

## [4321313] Install with composer require and leave tests out of the package (2026-10-10)

### Changed

- The README shows `composer require sleqq/tibia-reader` and how to install from GitHub through a `vcs` repository, instead of `composer install`.
- `tests/`, `bin/` and `phpunit.xml.dist` are left out of the package Composer downloads for a tagged release.

## [bbae3a8] Run the examples against the downloaded client files (2026-10-10)

### Changed

- The examples read the client files from `tests/data/<version>/` through `examples/config.php`, 10.75 by default; the `TIBIA_VERSION` environment variable picks another downloaded version. They used to expect files in `examples/resources/`.
- The outfit demo draws looktype 128 with mount 368, which exist in 10.75.

## [e089791] Add tests against real client files of every supported version (2026-10-10)

### Added

- PHPUnit tests that read the real `.dat` and `.spr` files of every version with a recognized signature: version detection, parsing every object up to the end of the file, flag names and values, known items, outfit and projectile patterns, animations and frame groups, the sprite address table, sprite decoding and outfit rendering. Run them with `composer test`.
- `bin/download-test-data.php` (`composer test-data`) downloads the client files into `tests/data/`, which git ignores. 8.10, 9.85, 10.72 and 10.84 have no known download and are skipped until their files are added by hand.

## [9404524] Add Client and typed objects; name flags; speed up reading (2026-10-10)

### Added

- `Client`: one set of client files (`.dat` and/or `.spr`) with shared readers. Create one per client version; several can be used side by side. `Client::looktype()` builds outfits from it, and `DatObject`, `Sprite` and `Looktype` accept a `Client` as an optional constructor argument.
- `ClientVersion` detects the version from the `.dat` signature and knows which format features it uses. Custom clients with an unrecognized signature can set it explicitly with `Config::setClientVersion()` or the `clientVersion` argument of `Client` (7.55 to 10.99).
- A warning is raised when the `.spr` file looks like it belongs to a different client version than the `.dat` file.
- `ThingType`, `FrameGroup`, `Animation` and the `ThingCategory` enum describe parsed objects. `DatObject::get()` looks up an object by category and id.

### Changed

- **Breaking:** `getItem()`, `getLooktype()`, `getEffect()` and `getProjectile()` return a `ThingType`. Every object has `frameGroups`, also in files before 10.57, where it holds a single group keyed `0`. The deprecated `$object` array has the same shape as `ThingType::toArray()`.
- **Breaking:** flags are keyed by name instead of the byte they are stored as, which differed between versions. Names follow OTClient's naming, e.g. `pickable` → `pickupable`, `blocking` → `not_walkable`, `top_order_1` → `ground_border`, `body` → `cloth`. Flags that were mislabelled are corrected: byte 6 is `force_use` (was `corpse`), in 7.80+ files the old `floor_change` is `translucent`, the three `unknown` flags are `dont_hide` and `usable`, and in 7.60 files `useable`/`ladder` are `force_use`/`multi_use`.
- **Breaking:** the light flag's parameters are `level`, then `color`. They were labelled the other way round.
- **Breaking:** `Flags::getFlag()` takes a `ClientVersion` and returns the flag name and its parameters.
- Changing a path in `Config` takes effect for outfits rendered afterwards. Before, the first `.spr` file opened for outfits kept being used.

### Fixed

- Outfit color 40 was `0xBFAF8F`, a copy of color 21. It is `0xBF9F5F`.

### Performance

- Outfits and their mounts share one `.dat` reader instead of opening the file per outfit.
- Object offsets are remembered for the lifetime of a reader and found with a routine that skips objects without building them, so lookups without data caching no longer rescan the file from the start. Reading every looktype of a 30,000-item file without the cache went from about 42 s to under 0.1 s.
- Sprites are read with one call and decoded straight into an image, and recently used sprites are kept in memory. Rendering 20 outfit frame maps went from about 4 s to 0.3 s.

## [683573c] Write the data file map atomically (2026-09-27)

### Changed

- `DatObject::mapFile()` returns the array of object offsets it builds, so the instance that creates the map uses it directly instead of reading it back from disk.

### Fixed

- Concurrent first requests could read a partially written data file map and look up objects at wrong offsets. The map is now written to a temporary file and renamed into place.

## [ac91107] Stop hashing the whole data file on every request (2026-09-27)

### Changed

- The data file cache is keyed on the file's path, size and modification time. Before, it was keyed on an md5 of the entire `.dat` file, which was computed on every request, even with caching disabled. Maps created under the old naming are no longer used and can be deleted from `cache/data/`.

## [fdb7f2e] Fix reading the last sprite in a .spr file (2026-09-27)

### Fixed

- The last sprite in a `.spr` file could not be read. A sprite's end was taken from the next entry in the address table, which doesn't exist for the last sprite. It is now computed from the sprite's own data-size field.
- Sprite ID 0, and sprites with no data in the file, return a blank image. Before, they rendered sprite 1 instead.

## [bb17a3f] Key frame groups by their type byte (2026-09-27)

### Changed

- Looktype frame groups are keyed by the group type stored in the file (0 = idle, 1 = moving) instead of by the order they appear in. That type byte used to be read and then ignored.

## [0479cc1] Reuse a single sprite reader when rendering outfits (2026-09-27)

### Changed

- Outfit rendering reuses one sprite reader instead of opening the `.spr` file again for every layer. A full frame map previously opened it dozens of times.

## [78830fe] Fix XSS in object explorer example (2026-09-27)

### Security

- The object explorer example printed the `id` query parameter into the page unescaped, and used `type` directly as an array key. Both are now cast to integers, which closes a cross-site scripting (XSS) hole.

## [4bde912] Fix misspelled flag names (2026-09-27)

### Changed

- **Breaking:** misspelled flag names fixed in parsed objects: `immoble` → `immobile` and `block_missle` → `block_missile`.

## [36b0aa7] Add README (2026-09-27)

### Added

- README with installation instructions and usage examples.

## [85e5e14] Modernize for PHP 8: PSR-4 layout, Composer, typed code (2026-09-27)

### Added

- `composer.json` with PSR-4 autoloading (`TibiaReader\` → `src/`). `TibiaReader.php` is kept as a bootstrap for use without Composer.

### Changed

- Requires PHP 8.1+ with the GD extension. The code now uses strict types, typed properties, and parameter and return types throughout.
- Classes moved to `src/`, one class per file: `FileReader`, `Config`, `Flags`, `Sprite`, `DatObject` and `Looktype`.
- **Breaking:** `Object` renamed to `DatObject`, and `CONFIG` renamed to `Config`.
- **Breaking:** the global `ADDON_*` and `COLOR_*` constants are replaced by class constants, e.g. `Looktype::ADDON_FIRST`. New `Looktype::DIRECTION_*` constants name the four directions.
- Cache directories are created the first time caching is used, instead of whenever the library is included.
- Invalid object or sprite IDs throw `OutOfRangeException`. Invalid configuration throws `InvalidArgumentException`. Both extend `Exception`, so existing `catch` blocks still work.

### Removed

- `TibiaObject.php`, `TibiaSprite.php`, `TibiaLooktype.php` and `flags.php`, which are replaced by the classes in `src/`.
- The unused global `LOOKTYPE_MOUNTED` and `LOOKTYPE_UNMOUNTED` constants.

### Fixed

- The library failed to load on PHP 7.2 and newer, because `Object` became a reserved word.
- Outfit rendering raised a PHP 8 deprecation warning (an optional `$mounted` parameter was declared before the required `$step`).
- Binary reads now always use little-endian byte order instead of the machine's native order.
- Reading past the end of a truncated file throws a clear exception instead of failing inside `unpack()` or looping forever.
- Unknown flags throw an exception naming the flag and file signature, instead of an undefined-index error.
- The error messages of `setSpriteFilePath()` and `setDataFilePath()` were swapped.
- `Sprite` declares its `$fileSignature` property. It was created dynamically before, which PHP 8.2+ deprecates.

## [766a02c] Normalize line endings to LF and add changelog (2026-09-27)

### Changed

- All files use LF line endings, enforced by `.gitattributes`. Several files previously used CRLF or classic Mac (CR-only) line endings.

[4321313]: https://github.com/Sleqqus/TibiaReader/commit/43213134dff54ccec45410b0a03aade2d1816fae
[bbae3a8]: https://github.com/Sleqqus/TibiaReader/commit/bbae3a88a1fc0c4883d00d487091c2c058aa5b71
[e089791]: https://github.com/Sleqqus/TibiaReader/commit/e0897917234bb8a94e6762bd6594cc7d8539d9e2
[9404524]: https://github.com/Sleqqus/TibiaReader/commit/9404524138587dfca04cf203753693be56b65e91
[683573c]: https://github.com/Sleqqus/TibiaReader/commit/683573cb8afa0a8e78c2e981502559009b1aecae
[ac91107]: https://github.com/Sleqqus/TibiaReader/commit/ac911076e86d5ae0bf315e7b817ea1359d0fc6df
[fdb7f2e]: https://github.com/Sleqqus/TibiaReader/commit/fdb7f2e8027c9366071874d005c7da6817598de7
[bb17a3f]: https://github.com/Sleqqus/TibiaReader/commit/bb17a3fe7ce260e54421674a2f0895ff812259c7
[0479cc1]: https://github.com/Sleqqus/TibiaReader/commit/0479cc14bea66430607a471a7457be869eac924b
[78830fe]: https://github.com/Sleqqus/TibiaReader/commit/78830fe56a686d5b4368568cf4f0715b7fed1827
[4bde912]: https://github.com/Sleqqus/TibiaReader/commit/4bde9127533610449299019f2e42466dba2bce94
[36b0aa7]: https://github.com/Sleqqus/TibiaReader/commit/36b0aa7781bac4e195b37f81dbb1807e8f2b685d
[85e5e14]: https://github.com/Sleqqus/TibiaReader/commit/85e5e141a7b212bff22f6f5514f26ea6eec3b957
[766a02c]: https://github.com/Sleqqus/TibiaReader/commit/766a02c8b9a891f75d11194792bed37b691e5880
