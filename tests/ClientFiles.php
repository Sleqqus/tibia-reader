<?php

declare(strict_types=1);

namespace TibiaReader\Tests;

use PHPUnit\Framework\TestCase;
use TibiaReader\Client;
use TibiaReader\ClientVersion;
use TibiaReader\FrameGroup;
use TibiaReader\ThingCategory;

/**
 * The real client files in tests/data/<version>/, and what the tests learn from reading them.
 */
final class ClientFiles
{
	public const DATA_DIR = __DIR__ . '/data';

	// Client ids (not the server ids of items.otb) of a backpack, a gold coin and a crystal coin, by the
	// first version that uses them. From 7.60 (the first known files after 7.50) on they stay the same,
	// also in files converted from newer clients. Before 7.40 no crystal coin is checked.
	private const KNOWN_ITEMS = [
		755 => [2854, 3031, 3043],
		750 => [2792, 2969, 2981],
		740 => [1988, 2148, 2160],
		730 => [1621, 1758, null],
		723 => [1418, 1537, null],
		721 => [1419, 1537, null],
		713 => [1419, 1531, null],
		710 => [1411, 1517, null],
	];

	/** @var array<int|string, array> key => statistics, see stats() */
	private static array $stats = [];

	/**
	 * Data provider: every entry of tests/client-versions.php, by its key (a version number, or the
	 * name of a converted client's folder).
	 *
	 * @return array<string, array{int|string}>
	 */
	public static function versions(): array
	{
		$versions = [];
		foreach (array_keys(self::sources()) as $key) {
			$versions[self::label($key)] = [$key];
		}

		return $versions;
	}

	/**
	 * @return array<int|string, string|null>
	 */
	public static function sources(): array
	{
		return require __DIR__ . '/client-versions.php';
	}

	public static function label(int|string $key): string
	{
		return is_int($key) ? (string) new ClientVersion($key) : $key;
	}

	/**
	 * The version the files are read as: the one they are listed under, or the newest format for
	 * converted clients.
	 */
	public static function version(int|string $key): ClientVersion
	{
		return is_int($key) ? new ClientVersion($key) : ClientVersion::latest();
	}

	/**
	 * Ids of a backpack, a gold coin and a crystal coin (null when not known) in the files of $key.
	 *
	 * @return array{int, int, int|null}
	 */
	public static function knownItems(int|string $key): array
	{
		$version = self::version($key)->version;
		$firstVersion = max(array_filter(array_keys(self::KNOWN_ITEMS), static fn (int $first) => $first <= $version));

		return self::KNOWN_ITEMS[$firstVersion];
	}

	public static function datPath(int|string $key): string
	{
		return self::DATA_DIR . "/{$key}/Tibia.dat";
	}

	public static function sprPath(int|string $key): string
	{
		return self::DATA_DIR . "/{$key}/Tibia.spr";
	}

	/**
	 * Skips the calling test when the files of $key haven't been downloaded. The .spr file is only
	 * required with $sprites, so the .dat tests run on their own.
	 */
	public static function require(int|string $key, bool $sprites = true): void
	{
		if (is_file(self::datPath($key)) && (!$sprites || is_file(self::sprPath($key)))) {
			return;
		}

		$label = self::label($key);
		TestCase::markTestSkipped(self::sources()[$key] === null
			? "No test data for {$label}: no known download, put Tibia.dat and Tibia.spr into tests/data/{$key}/."
			: "No test data for {$label}: run `composer test-data` first.");
	}

	/**
	 * A client for the files of $key with the version set explicitly, so the files are read even
	 * when their signature isn't recognized (that is tested on its own).
	 */
	public static function client(int|string $key, bool $sprites = true): Client
	{
		self::require($key, $sprites);

		return new Client(self::datPath($key), $sprites ? self::sprPath($key) : null, clientVersion: self::version($key));
	}

	/**
	 * Reads every object of the .dat file once and collects what the tests check.
	 *
	 * @return array{
	 *     counts: array<string, int>,
	 *     flagCounts: array<string, int>,
	 *     flagValues: array<string, array<string, list<int|string>>>,
	 *     maxSpriteId: int,
	 *     animated: int,
	 *     badAnimations: list<string>,
	 *     looktypesWithMovingGroup: int,
	 * }
	 */
	public static function stats(int|string $key): array
	{
		if (isset(self::$stats[$key])) {
			return self::$stats[$key];
		}

		$dat = self::client($key, sprites: false)->dat();
		$stats = [
			'counts'                   => [],
			'flagCounts'               => [],
			'flagValues'               => [],
			'maxSpriteId'              => 0,
			'animated'                 => 0,
			'badAnimations'            => [],
			'looktypesWithMovingGroup' => 0,
		];

		$ranges = [
			[ThingCategory::Item, 100, $dat->fileItems],
			[ThingCategory::Looktype, 1, $dat->fileLooktypes],
			[ThingCategory::Effect, 1, $dat->fileEffects],
			[ThingCategory::Projectile, 1, $dat->fileProjectiles],
		];

		foreach ($ranges as [$category, $first, $last]) {
			$stats['counts'][$category->label()] = 0;

			for ($id = $first; $id <= $last; $id++) {
				$thing = $dat->get($category, $id);
				$stats['counts'][$category->label()]++;

				foreach ($thing->flags as $name => $parameters) {
					$stats['flagCounts'][$name] = ($stats['flagCounts'][$name] ?? 0) + 1;
					foreach ($parameters as $parameter => $value) {
						$stats['flagValues'][$name][$parameter][] = $value;
					}
				}

				if ($category === ThingCategory::Looktype && isset($thing->frameGroups[FrameGroup::MOVING])) {
					$stats['looktypesWithMovingGroup']++;
				}

				foreach ($thing->frameGroups as $group) {
					if ($group->spriteIds !== []) {
						$stats['maxSpriteId'] = max($stats['maxSpriteId'], max($group->spriteIds));
					}

					if ($group->animation !== null) {
						$stats['animated']++;
						$durations = $group->animation->durations;
						$inverted = array_filter($durations, static fn (array $duration) => $duration['min'] > $duration['max']);
						if (count($durations) !== $group->phases || $inverted !== []) {
							$stats['badAnimations'][] = "{$category->label()} {$id}";
						}
					}
				}
			}
		}

		return self::$stats[$key] = $stats;
	}
}
