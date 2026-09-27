# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), with changes grouped by the commit that made them instead of by version, newest first. A commit can't contain its own hash, so each section is written in a later commit.

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

[c8901a5]: https://bitbucket.org/Vencis/tibia-reader/commits/c8901a5c3d8368aed42c1d62b59c2d6cbe93487e
[5a60f45]: https://bitbucket.org/Vencis/tibia-reader/commits/5a60f458c27bd942fedab9c2a210da95c7b94681
[0e927aa]: https://bitbucket.org/Vencis/tibia-reader/commits/0e927aae8f8e99e101c0a6658c9563332e27b789
[f234ead]: https://bitbucket.org/Vencis/tibia-reader/commits/f234eadceb80188ee132039a940dde5d025deac8
[a5f8aee]: https://bitbucket.org/Vencis/tibia-reader/commits/a5f8aee413ed9d2adb0e768872d8da5291f9503c
[93b2ecd]: https://bitbucket.org/Vencis/tibia-reader/commits/93b2ecd7f889d364b1d75344a490a2a7c1882141
[dce3861]: https://bitbucket.org/Vencis/tibia-reader/commits/dce38614a3a1b5490449e3ade9b0a0ac3ec1e37d
[e830913]: https://bitbucket.org/Vencis/tibia-reader/commits/e8309137ddc21be3abe179a929e16eb6493556b8
[adc0b24]: https://bitbucket.org/Vencis/tibia-reader/commits/adc0b2448c0b3573edcd4dce052bb1060c3bd913
[45d95e4]: https://bitbucket.org/Vencis/tibia-reader/commits/45d95e4d2c272f19c4e08c1712c6eb12d953d1d8
