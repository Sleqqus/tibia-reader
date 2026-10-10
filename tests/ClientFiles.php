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

	/** @var array<int, array> version => statistics, see stats() */
	private static array $stats = [];

	/**
	 * Data provider: every version listed in tests/client-versions.php.
	 *
	 * @return array<string, array{int}>
	 */
	public static function versions(): array
	{
		$versions = [];
		foreach (array_keys(require __DIR__ . '/client-versions.php') as $version) {
			$versions[(string) new ClientVersion($version)] = [$version];
		}

		return $versions;
	}

	public static function datPath(int $version): string
	{
		return self::DATA_DIR . "/{$version}/Tibia.dat";
	}

	public static function sprPath(int $version): string
	{
		return self::DATA_DIR . "/{$version}/Tibia.spr";
	}

	/**
	 * Skips the calling test when the files of $version haven't been downloaded.
	 */
	public static function require(int $version): void
	{
		if (is_file(self::datPath($version)) && is_file(self::sprPath($version))) {
			return;
		}

		$url = (require __DIR__ . '/client-versions.php')[$version];
		$label = new ClientVersion($version);

		TestCase::markTestSkipped($url === null
			? "No test data for {$label}: no known download, put Tibia.dat and Tibia.spr into tests/data/{$version}/."
			: "No test data for {$label}: run `composer test-data` first.");
	}

	/**
	 * A client for the files of $version with the version set explicitly, so the files are read
	 * even when their signature isn't recognized (that is tested on its own).
	 */
	public static function client(int $version): Client
	{
		self::require($version);

		return new Client(self::datPath($version), self::sprPath($version), clientVersion: $version);
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
	public static function stats(int $version): array
	{
		if (isset(self::$stats[$version])) {
			return self::$stats[$version];
		}

		$dat = self::client($version)->dat();
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

		return self::$stats[$version] = $stats;
	}
}
