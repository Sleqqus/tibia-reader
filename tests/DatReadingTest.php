<?php

declare(strict_types=1);

namespace TibiaReader\Tests;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TibiaReader\Client;
use TibiaReader\FileReader;
use TibiaReader\Flags;
use TibiaReader\FrameGroup;

/**
 * Reads the real .dat file of every supported version. Wrong flag tables or format switches
 * show up as unknown flags, a parse that doesn't end at the end of the file, or flag values
 * that make no sense.
 */
final class DatReadingTest extends TestCase
{
	// Client ids (not the server ids of items.otb, e.g. 2148 for a gold coin) that mean the same item
	// in every version from 7.60 to 10.x
	private const BACKPACK = 2854;
	private const GOLD_COIN = 3031;
	private const CRYSTAL_COIN = 3043;
	private const CITIZEN_OUTFIT = 128;

	// The 216 colors of the client's 6x6x6 palette, used by lights and the minimap
	private const MAX_PALETTE_COLOR = 215;

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testVersionIsDetectedFromSignature(int $version): void
	{
		ClientFiles::require($version);

		$signature = (new FileReader(ClientFiles::datPath($version)))->readInt32();
		$detected = (new Client(ClientFiles::datPath($version)))->versionIfKnown();

		$this->assertSame(
			$version,
			$detected?->version,
			"The .dat signature {$signature} is detected as " . ($detected ?? 'no known version') . '.'
		);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testEveryObjectParsesUpToTheEndOfTheFile(int $version): void
	{
		$dat = ClientFiles::client($version)->dat();

		// The offset after the last object is where the file has to end
		$end = $dat->getObjectAddress($dat->objectsTotal + 1);

		$this->assertSame(filesize(ClientFiles::datPath($version)), $end);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testObjectCountsMatchTheHeader(int $version): void
	{
		$dat = ClientFiles::client($version)->dat();
		$counts = ClientFiles::stats($version)['counts'];

		$this->assertSame($dat->fileItems - 99, $counts['item']);
		$this->assertSame($dat->fileLooktypes, $counts['looktype']);
		$this->assertSame($dat->fileEffects, $counts['effect']);
		$this->assertSame($dat->fileProjectiles, $counts['projectile']);
		$this->assertGreaterThan(1000, $counts['item']);
		$this->assertGreaterThan(100, $counts['looktype']);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testCommonFlagsAreFound(int $version): void
	{
		$flagCounts = ClientFiles::stats($version)['flagCounts'];

		$expected = [
			'ground', 'ground_border', 'on_bottom', 'on_top', 'container', 'stackable', 'multi_use',
			'writable', 'fluid_container', 'splash', 'not_walkable', 'not_moveable', 'block_projectile',
			'not_pathable', 'pickupable', 'hangable', 'hook_south', 'hook_east', 'rotateable', 'light',
			'displacement', 'elevation', 'lying_corpse', 'animate_always', 'minimap_color', 'full_ground',
		];
		if ($version >= 944) {
			$expected[] = 'cloth';
			$expected[] = 'market';
		}

		$missing = array_values(array_filter($expected, static fn (string $name) => !isset($flagCounts[$name])));
		$this->assertSame([], $missing, 'Flags no object has: ' . implode(', ', $missing));

		// Only names the version's flag table knows can appear
		$this->assertSame([], array_values(array_diff(array_keys($flagCounts), Flags::flagNames(ClientFiles::client($version)->dat()->version()))));
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testFlagValuesAreInRange(int $version): void
	{
		$values = ClientFiles::stats($version)['flagValues'];
		$fileItems = ClientFiles::client($version)->dat()->fileItems;

		$this->assertAllBetween(0, 1000, $values['ground']['speed'], 'ground speed');
		$this->assertAllBetween(1, 0xFFFF, $values['writable']['max_length'], 'writable max length');
		$this->assertAllBetween(0, self::MAX_PALETTE_COLOR, $values['light']['color'], 'light color');
		$this->assertAllBetween(0, self::MAX_PALETTE_COLOR, $values['minimap_color']['color'], 'minimap color');
		$this->assertAllBetween(0, 64, $values['elevation']['height'], 'elevation');
		$this->assertAllBetween(0, 64, $values['displacement']['x'], 'displacement x');
		$this->assertAllBetween(0, 64, $values['displacement']['y'], 'displacement y');

		// Light levels are small and colors mostly aren't; swapped fields would show the opposite
		$this->assertLessThan(
			array_sum($values['light']['color']) / count($values['light']['color']),
			array_sum($values['light']['level']) / count($values['light']['level']),
			'Light levels are larger than light colors on average; are the fields swapped?'
		);

		if ($version >= 944) {
			$this->assertAllBetween(0, 32, $values['cloth']['slot'], 'cloth slot');
			$this->assertAllBetween(0, 100, $values['market']['category'], 'market category');
			$this->assertAllBetween(0, $fileItems, $values['market']['trade_as'], 'market trade-as item');
			$this->assertAllBetween(0, $fileItems, $values['market']['show_as'], 'market show-as item');

			foreach ($values['market']['name'] as $name) {
				$this->assertMatchesRegularExpression('/^[\x20-\x7E\xA0-\xFF]+$/', $name, 'Market names are printable text.');
			}
		}
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testKnownItemsHaveTheirFlags(int $version): void
	{
		$dat = ClientFiles::client($version)->dat();

		$backpack = $dat->getItem(self::BACKPACK);
		$this->assertTrue($backpack->hasFlag('container'), 'A backpack is a container.');
		$this->assertTrue($backpack->hasFlag('pickupable'), 'A backpack is pickupable.');
		$this->assertFalse($backpack->hasFlag('stackable'), 'A backpack is not stackable.');

		foreach ([self::GOLD_COIN, self::CRYSTAL_COIN] as $coinId) {
			$coin = $dat->getItem($coinId);
			$this->assertTrue($coin->hasFlag('stackable'), "Item {$coinId} (a coin) is stackable.");
			$this->assertTrue($coin->hasFlag('pickupable'), "Item {$coinId} (a coin) is pickupable.");
			$this->assertFalse($coin->hasFlag('container'), "Item {$coinId} (a coin) is not a container.");
		}
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testOutfitsAndProjectilesHaveDirections(int $version): void
	{
		$dat = ClientFiles::client($version)->dat();

		// Outfits: four directions, and a second layer holding the color mask
		$citizen = $dat->getLooktype(self::CITIZEN_OUTFIT)->frameGroup(FrameGroup::IDLE);
		$this->assertSame(4, $citizen->patternX);
		$this->assertSame(2, $citizen->layers);

		// Projectiles: a 3x3 grid of directions
		$projectile = $dat->getProjectile(1)->frameGroup();
		$this->assertSame([3, 3], [$projectile->patternX, $projectile->patternY]);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testAnimationsAndFrameGroupsMatchTheVersion(int $version): void
	{
		$stats = ClientFiles::stats($version);
		$clientVersion = ClientFiles::client($version)->dat()->version();

		if ($clientVersion->hasEnhancedAnimations()) {
			$this->assertGreaterThan(0, $stats['animated'], 'Animated objects carry timing data.');
			$this->assertSame([], $stats['badAnimations'], 'Objects with phase durations that don\'t match their phases.');
		} else {
			$this->assertSame(0, $stats['animated']);
		}

		if ($clientVersion->hasFrameGroups()) {
			$this->assertGreaterThan(0, $stats['looktypesWithMovingGroup'], 'Outfits have a separate moving frame group.');
		} else {
			$this->assertSame(0, $stats['looktypesWithMovingGroup']);
		}
	}

	/**
	 * @param list<int> $values
	 */
	private function assertAllBetween(int $min, int $max, array $values, string $what): void
	{
		$this->assertNotEmpty($values, "No {$what} values were read.");

		$outside = array_values(array_unique(array_filter($values, static fn (int $value) => $value < $min || $value > $max)));
		$this->assertSame([], array_slice($outside, 0, 10), "Values of {$what} outside {$min}-{$max}.");
	}
}
