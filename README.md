# tibia-reader

PHP library for reading Tibia client files — `.spr` (sprites) and `.dat` (objects) — supporting **every client version that ships them, 7.10 to 11.00**, as well as files converted back to `.dat`/`.spr` from newer clients. Renders items, effects, projectiles and fully composed outfits (colors, addons, mounts) as GD images.

Requires **PHP 8.1+** with the **GD** extension.

## Installation

With Composer:

```sh
composer require sleqq/tibia-reader
```

```php
require 'vendor/autoload.php';
```

To install it straight from GitHub instead of Packagist, add the repository to your project's `composer.json` first:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/Sleqqus/tibia-reader"}
]
```

Or without Composer, from a copy of this repository:

```php
require 'TibiaReader.php';
```

## Usage

```php
use TibiaReader\Client;
use TibiaReader\Looktype;

$client = new Client(
    'resources/1098Tibia.dat',
    'resources/1098Tibia.spr',
    dataCaching: true,       // cache a byte-offset map of the .dat file for fast lookups
);

// Single sprite as a GdImage
$image = $client->sprites()->getSprite(1234, alphaChannel: true);

// Object properties: flags, frame groups, sprite ids
$item = $client->dat()->getItem(2400);   // items use client ids (100+)
$item->hasFlag('pickupable');            // flags are keyed by name
$item->flag('light');                    // ['level' => ..., 'color' => ...], or null
$item->frameGroup()->spriteIds;          // also width, height, layers, patternX/Y/Z, phases, animation

$client->dat()->getLooktype(128);        // or getEffect() / getProjectile()

// Composed outfit
$outfit = $client->looktype(733);
$outfit->setColors(100, 20, 40, 31)   // head, body, legs, feet (0-132)
    ->setAddons(Looktype::ADDON_BOTH)
    ->setDirection(Looktype::DIRECTION_SOUTH);
$outfit->setMount(726)->setColors(123, 123, 123, 123);

$image = $outfit->getFrame();        // single frame
$frameMap = $outfit->getFrameMap();  // all directions x animation steps

// Standing animation (breathing, flapping wings): 1 phase when the looktype stands still
$outfit->idlePhases();               // e.g. 8
$outfit->idleDurations();            // milliseconds per phase, e.g. [100, 100, ...]
$outfit->setStep(0)->setIdlePhase(3)->getFrame();

header('Content-type: image/png');
imagepng($image);
```

Create one `Client` per set of client files; several can be used side by side. Readers are shared within a client, so outfits, their mounts and repeated lookups reuse the open files, the offset map and decoded sprites.

### Global configuration

The static `Config` class still works and builds a default client behind the scenes, used by `new DatObject()`, `new Sprite()` and `new Looktype($id)`:

```php
use TibiaReader\Config;

Config::setDataFilePath('resources/1098Tibia.dat');
Config::setSpriteFilePath('resources/1098Tibia.spr');
Config::setDataCaching(true);

$outfit = new TibiaReader\Looktype(733);
```

### Client versions and custom clients

The client version is detected from the `.dat` file signature, which is known for about 120 versions from 7.10 to 11.00. A file with an unknown signature, such as a build missing from the list, a custom client or a file converted from a newer client, is read as each known file format in turn, newest first, and gets the newest version whose format reads the whole file. `$client->versionIsGuessed()` tells when that happened. The size of the sprite count in the `.spr` file is told from the file's layout.

To skip detection, or for a custom client that detection gets wrong, set the version yourself. Custom clients that use 32-bit sprite ids or frame durations before the official versions did (9.60 and 10.50) take a `ClientVersion` with those features:

```php
use TibiaReader\ClientVersion;

$client = new Client('custom.dat', 'custom.spr', clientVersion: 1098);
$client = new Client('custom.dat', 'custom.spr', clientVersion: new ClientVersion(860, extendedSprites: true));
// or: Config::setClientVersion(1098);
```

Versions 7.10 to 11.00 are accepted. `ClientVersion::LATEST` is the newest one.

Clients from 11.x on store objects as protobuf "appearances" and sprites in compressed sprite sheets instead of `.dat` and `.spr`; reading those is not supported. Files converted from them back to `.dat` and `.spr`, as OTClient-based clients use them, are read in the 11.00 format.

Browsable demos live in [examples/](examples/) (`sprite_browser.php`, `object_explorer.php`). They run only from the command line or PHP's built-in server, never under another web server. In a clone of this repository they read the client files `composer test-data` downloads, the newest version (`ClientVersion::LATEST`) by default:

```sh
cd examples
php -S localhost:8000                     # http://localhost:8000/object_explorer.php
TIBIA_VERSION=854 php -S localhost:8000   # any other version in tests/data/
```

The installed Composer package has no `tests/data/`, so point `TIBIA_CLIENT_DIR` at a folder holding `Tibia.dat` and `Tibia.spr`:

```sh
TIBIA_CLIENT_DIR=/path/to/client php -S localhost:8000 -t vendor/sleqq/tibia-reader/examples
```

## Tests

The tests read real client files of every version whose `.dat` signature is recognized, and of converted 12.x to 15.x files (see [tests/client-versions.php](tests/client-versions.php)). The files aren't committed; download them into `tests/data/` (ignored by git) first. All of them take about 9 GB:

```sh
composer install
composer test-data           # or: php bin/download-test-data.php 760 1050
composer test
```

The files come from the [downloads.ots.me](https://downloads.ots.me/?dir=data/tibia-clients/dat_and_spr) client archive. Versions it doesn't have (such as 7.55, 8.10, 9.85 or 10.72) are skipped until you put their `Tibia.dat` and `Tibia.spr` into `tests/data/<version>/`, e.g. `tests/data/1072/`. `composer test -- --display-skipped` lists what was skipped.

## Notes

- Known `.dat` signatures are listed in [src/ClientVersion.php](src/ClientVersion.php). Flag names follow OTClient's naming and are listed in [src/Flags.php](src/Flags.php).
- Before 7.55 objects have no third pattern dimension and the displacement flag stores no offset (the client always shifts by 8 pixels), so `flag('displacement')` is an empty array there.
- A warning is raised when the `.spr` file looks like it comes from a different client version than the `.dat` file.
- With data caching on, a map of object byte offsets (keyed by the file's path, size and modification time) is written on first use, making object lookups O(1) on later requests. Without it, offsets are still remembered for the lifetime of the reader.
- The map goes to `tibia-reader/v1/data/` in the system temp directory (`Client::defaultCacheDir()`), so it survives `composer update` and needs no setup. If that folder can't be written, the map is kept in memory only. Pass `cacheDir:` to the `Client` (or set `Config::$mainCacheDir`) to keep it somewhere that outlives reboots; a folder you set must be writable, or an exception is thrown. Rebuilding a lost map takes about 50 ms.

## License

MIT, see [LICENSE](LICENSE). Tibia and its client files belong to CipSoft and aren't covered by it.
