# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- `composer.json` with PSR-4 autoloading (`TibiaReader\` → `src/`). `TibiaReader.php` is kept as a bootstrap for use without Composer.

### Changed

- All files use LF line endings, enforced by `.gitattributes`. Several files previously used CRLF or classic Mac (CR-only) line endings.
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
