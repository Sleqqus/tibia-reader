# tibia-reader

PHP library for reading Tibia client files — `.spr` (sprites) and `.dat` (objects) — supporting client versions **7.60 to 10.84**. Renders items, effects, projectiles and fully composed outfits (colors, addons, mounts) as GD images.

Requires **PHP 8.1+** with the **GD** extension.

## Installation

With Composer (PSR-4 autoloading):

```sh
composer install
```

```php
require 'vendor/autoload.php';
```

Or without Composer:

```php
require 'TibiaReader.php';
```

## Usage

```php
use TibiaReader\Config;
use TibiaReader\Sprite;
use TibiaReader\DatObject;
use TibiaReader\Looktype;

Config::setDataFilePath('resources/1082Tibia.dat');
Config::setSpriteFilePath('resources/1082Tibia.spr');
Config::setDataCaching(true); // cache a byte-offset map of the .dat file for fast lookups

// Single sprite as a GdImage
$sprite = new Sprite();
$image = $sprite->getSprite(1234, alphaChannel: true);

// Object properties (flags, dimensions, sprite ids)
$object = new DatObject();
$object->getItem(2400);      // items use client ids (100+)
$object->getLooktype(128);   // or getEffect() / getProjectile()
print_r($object->object);

// Composed outfit
$outfit = new Looktype(733);
$outfit->setColors(100, 20, 40, 31)   // head, body, legs, feet (0-132)
    ->setAddons(Looktype::ADDON_BOTH)
    ->setDirection(Looktype::DIRECTION_SOUTH);
$outfit->setMount(726)->setColors(123, 123, 123, 123);

$image = $outfit->getFrame();        // single frame
$frameMap = $outfit->getFrameMap();  // all directions x animation steps

header('Content-type: image/png');
imagepng($image);
```

Browsable demos live in [examples/](examples/) (`sprite_browser.php`, `object_explorer.php`) — drop your client files into `examples/resources/` first.

## Notes

- Client version is detected automatically from the file signature; supported `.dat` signatures are listed in [src/Flags.php](src/Flags.php).
- With `setDataCaching(true)`, a map of object byte offsets (keyed by the file's md5) is written to `cache/data/` on first use, making subsequent object lookups O(1).
