# tibia-reader

PHP library for reading Tibia client files — `.spr` (sprites) and `.dat` (objects) — supporting client versions **7.60 to 10.84**. Renders items, effects, projectiles and fully composed outfits (colors, addons, mounts) as GD images.

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
    {"type": "vcs", "url": "https://github.com/Sleqqus/TibiaReader"}
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
    'resources/1082Tibia.dat',
    'resources/1082Tibia.spr',
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

header('Content-type: image/png');
imagepng($image);
```

Create one `Client` per set of client files; several can be used side by side. Readers are shared within a client, so outfits, their mounts and repeated lookups reuse the open files, the offset map and decoded sprites.

### Global configuration

The static `Config` class still works and builds a default client behind the scenes, used by `new DatObject()`, `new Sprite()` and `new Looktype($id)`:

```php
use TibiaReader\Config;

Config::setDataFilePath('resources/1082Tibia.dat');
Config::setSpriteFilePath('resources/1082Tibia.spr');
Config::setDataCaching(true);

$outfit = new TibiaReader\Looktype(733);
```

### Custom clients

The client version is detected from the `.dat` file signature. For a custom client whose signature isn't recognized, set the version yourself:

```php
$client = new Client('custom.dat', 'custom.spr', clientVersion: 1098);
// or: Config::setClientVersion(1098);
```

Versions 7.55 to 10.99 are accepted.

Browsable demos live in [examples/](examples/) (`sprite_browser.php`, `object_explorer.php`). They read the client files `composer test-data` downloads, 10.75 by default:

```sh
cd examples
php -S localhost:8000                     # http://localhost:8000/object_explorer.php
TIBIA_VERSION=854 php -S localhost:8000   # any other version in tests/data/
```

## Tests

The tests read real client files of every version whose `.dat` signature is recognized (see [tests/client-versions.php](tests/client-versions.php)). The files aren't committed; download them into `tests/data/` (ignored by git) first:

```sh
composer install
composer test-data           # or: php bin/download-test-data.php 760 1050
composer test
```

The files come from the [downloads.ots.me](https://downloads.ots.me/?dir=data/tibia-clients/dat_and_spr) client archive. Versions it doesn't have (8.10, 9.85, 10.72, 10.84) are skipped until you put their `Tibia.dat` and `Tibia.spr` into `tests/data/<version>/`, e.g. `tests/data/1084/`. `composer test -- --display-skipped` lists what was skipped.

## Notes

- Supported `.dat` signatures are listed in [src/ClientVersion.php](src/ClientVersion.php). Flag names follow OTClient's naming and are listed in [src/Flags.php](src/Flags.php).
- A warning is raised when the `.spr` file looks like it comes from a different client version than the `.dat` file.
- With data caching on, a map of object byte offsets (keyed by the file's path, size and modification time) is written to `cache/data/` on first use, making object lookups O(1) on later requests. Without it, offsets are still remembered for the lifetime of the reader.

## License

MIT, see [LICENSE](LICENSE). Tibia and its client files belong to CipSoft and aren't covered by it.
