<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * Object flags ("attributes") of the .dat format.
 *
 * Flags are identified by stable names, which follow OTClient's ThingAttr list. The byte a flag
 * is stored as depends on the client version, so each version range has its own byte => name map.
 */
class Flags
{
	// Parameters each flag carries: [name, size in bytes, is length-prefixed text]
	private const PARAMETERS = [
		'ground'         => [['speed', 2]],
		'writable'       => [['max_length', 2]],
		'writable_once'  => [['max_length', 2]],
		'light'          => [['level', 2], ['color', 2]],
		'displacement'   => [['x', 2], ['y', 2]],
		'elevation'      => [['height', 2]],
		'minimap_color'  => [['color', 2]],
		'lens_help'      => [['help', 2]],
		'cloth'          => [['slot', 2]],
		'market'         => [['category', 2], ['trade_as', 2], ['show_as', 2], ['name', 2, true], ['profession', 2], ['level', 2]],
		'default_action' => [['action', 2]],
	];

	// 8.60 - 9.86 layout; the other layouts are derived from it in flagNames()
	private const BASE_FLAGS = [
		0  => 'ground',
		1  => 'ground_border',
		2  => 'on_bottom',
		3  => 'on_top',
		4  => 'container',
		5  => 'stackable',
		6  => 'force_use',
		7  => 'multi_use',
		8  => 'writable',
		9  => 'writable_once',
		10 => 'fluid_container',
		11 => 'splash',
		12 => 'not_walkable',
		13 => 'not_moveable',
		14 => 'block_projectile',
		15 => 'not_pathable',
		16 => 'pickupable',
		17 => 'hangable',
		18 => 'hook_south',
		19 => 'hook_east',
		20 => 'rotateable',
		21 => 'light',
		22 => 'dont_hide',
		23 => 'translucent',
		24 => 'displacement',
		25 => 'elevation',
		26 => 'lying_corpse',
		27 => 'animate_always',
		28 => 'minimap_color',
		29 => 'lens_help',
		30 => 'full_ground',
		31 => 'ignore_look',
		32 => 'cloth',
		33 => 'market',
	];

	/** @var array<int, array<int, string>> */
	private static array $flagNamesCache = [];

	/**
	 * Returns [name, parameters] for the flag stored as $flagByte in files of the given version.
	 */
	public static function getFlag(ClientVersion $version, int $flagByte): array
	{
		$name = self::flagNames($version)[$flagByte] ?? null;
		if ($name === null) {
			throw new \RuntimeException("Unknown flag {$flagByte} for client version {$version}.");
		}

		return [$name, self::PARAMETERS[$name] ?? []];
	}

	public static function isSupported(int $fileSignature): bool
	{
		return ClientVersion::fromDatSignature($fileSignature) !== null;
	}

	/**
	 * @return array<int, string> flag byte => flag name
	 */
	public static function flagNames(ClientVersion $version): array
	{
		$v = $version->version;

		return self::$flagNamesCache[$v] ??= match (true) {
			// 10.00+: byte 16 is "no move animation", the rest from 16 on shift up by one
			$v >= 1000 => array_slice(self::BASE_FLAGS, 0, 16)
				+ [16 => 'no_move_animation']
				+ array_combine(range(17, 34), array_slice(self::BASE_FLAGS, 16, 18))
				+ [35 => 'default_action', 36 => 'wrappable', 37 => 'unwrappable', 38 => 'top_effect', 254 => 'usable'],

			$v >= 860 => self::BASE_FLAGS,

			// 7.80 - 8.54: byte 8 is "chargeable", the rest from 8 on shift up by one; no cloth or market
			$v >= 780 => array_slice(self::BASE_FLAGS, 0, 8)
				+ [8 => 'chargeable']
				+ array_combine(range(9, 32), array_slice(self::BASE_FLAGS, 8, 24)),

			// 7.55 - 7.72: like 8.60, but byte 23 is "floor change"; no cloth or market
			default => array_replace(array_slice(self::BASE_FLAGS, 0, 32), [23 => 'floor_change']),
		};
	}
}
