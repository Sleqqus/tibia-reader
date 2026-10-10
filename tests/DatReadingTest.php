<?php

declare(strict_types=1);

namespace TibiaReader\Tests;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TibiaReader\Client;
use TibiaReader\ClientVersion;
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
	private const CITIZEN_OUTFIT = 128;

	// The 216 colors of the client's 6x6x6 palette, used by lights and the minimap
	private const MAX_PALETTE_COLOR = 215;

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testVersionIsDetected(int|string $key): void
	{
		ClientFiles::require($key, sprites: false);

		$signature = (new FileReader(ClientFiles::datPath($key)))->readInt32();
		$client = new Client(ClientFiles::datPath($key));
		$detected = $client->versionIfKnown();
		$message = "The .dat signature {$signature} is detected as " . ($detected ?? 'no known version') . '.';

		if (is_int($key)) {
			$this->assertSame($key, $detected?->version, $message);
			$this->assertFalse($client->versionIsGuessed(), 'Known signatures are detected by signature.');
		} else {
			// Converted files: by signature or by structure, but in the newest format
			$this->assertNotNull($detected, $message);
			$this->assertTrue($detected->hasSameFormatAs(ClientVersion::latest()), $message);
		}
	}

	/**
	 * The same file with a signature no version has must still be read in its own format.
	 */
	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testFormatIsDetectedWithoutSignature(int|string $key): void
	{
		ClientFiles::require($key, sprites: false);

		$path = tempnam(sys_get_temp_dir(), 'tibia-reader-test-');
		try {
			file_put_contents($path, pack('V', 0xFFFFFFFF) . substr(file_get_contents(ClientFiles::datPath($key)), 4));
			$client = new Client($path);
			$detected = $client->versionIfKnown();
		} finally {
			unlink($path);
		}

		$this->assertTrue($client->versionIsGuessed());
		$this->assertNotNull($detected, 'No known format reads the whole file.');

		// 7.55 - 7.72 files also read as 8.60 - 9.54, which stores the same flags as the same bytes
		// up to the last one 7.72 uses. The newer format wins, with "translucent" for "floor change".
		$expected = ClientFiles::version($key);
		if ($expected->flagLayout() === 755) {
			$expected = new ClientVersion(954);
		}

		$this->assertTrue($detected->hasSameFormatAs($expected), "Detected as {$detected}, which reads files differently than {$expected}.");
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testEveryObjectParsesUpToTheEndOfTheFile(int|string $key): void
	{
		$dat = ClientFiles::client($key, sprites: false)->dat();

		// The offset after the last object is where the file has to end
		$end = $dat->getObjectAddress($dat->objectsTotal + 1);

		$this->assertSame(filesize(ClientFiles::datPath($key)), $end);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testObjectCountsMatchTheHeader(int|string $key): void
	{
		$dat = ClientFiles::client($key, sprites: false)->dat();
		$counts = ClientFiles::stats($key)['counts'];

		$this->assertSame($dat->fileItems - 99, $counts['item']);
		$this->assertSame($dat->fileLooktypes, $counts['looktype']);
		$this->assertSame($dat->fileEffects, $counts['effect']);
		$this->assertSame($dat->fileProjectiles, $counts['projectile']);
		$this->assertGreaterThan(1000, $counts['item']);
		$this->assertGreaterThan(100, $counts['looktype']);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testCommonFlagsAreFound(int|string $key): void
	{
		$flagCounts = ClientFiles::stats($key)['flagCounts'];
		$version = ClientFiles::version($key);

		$expected = [
			'ground', 'on_bottom', 'on_top', 'container', 'stackable', 'multi_use', 'writable', 'fluid_container',
			'splash', 'not_walkable', 'not_moveable', 'block_projectile', 'not_pathable', 'pickupable', 'light',
			'displacement', 'elevation', 'lying_corpse', 'animate_always', 'minimap_color', 'full_ground',
		];
		// 7.10 - 7.30 have a rotateable flag, but no object uses it yet
		if ($version->version >= 740) {
			array_push($expected, 'rotateable', 'hangable', 'hook_south', 'hook_east');
		}
		if ($version->version >= 755) {
			$expected[] = 'ground_border';
		}
		if ($version->version >= 944) {
			array_push($expected, 'cloth', 'market');
		}

		$missing = array_values(array_filter($expected, static fn (string $name) => !isset($flagCounts[$name])));
		$this->assertSame([], $missing, 'Flags no object has: ' . implode(', ', $missing));

		// Only names the version's flag table knows can appear
		$this->assertSame([], array_values(array_diff(array_keys($flagCounts), Flags::flagNames($version))));
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testFlagValuesAreInRange(int|string $key): void
	{
		$values = ClientFiles::stats($key)['flagValues'];
		$fileItems = ClientFiles::client($key, sprites: false)->dat()->fileItems;
		$version = ClientFiles::version($key);

		// 10.94 added grounds with speed 1200
		$this->assertAllBetween(0, 1500, $values['ground']['speed'], 'ground speed');
		$this->assertAllBetween(1, 0xFFFF, $values['writable']['max_length'], 'writable max length');
		$this->assertAllBetween(0, self::MAX_PALETTE_COLOR, $values['light']['color'], 'light color');
		$this->assertAllBetween(0, self::MAX_PALETTE_COLOR, $values['minimap_color']['color'], 'minimap color');
		$this->assertAllBetween(0, 64, $values['elevation']['height'], 'elevation');

		// Before 7.55 the displacement flag has no offset
		if ($version->hasDisplacementOffset()) {
			$this->assertAllBetween(0, 64, $values['displacement']['x'], 'displacement x');
			$this->assertAllBetween(0, 64, $values['displacement']['y'], 'displacement y');
		} else {
			$this->assertArrayNotHasKey('displacement', $values);
		}

		// Light levels are small and colors mostly aren't; swapped fields would show the opposite
		$this->assertLessThan(
			array_sum($values['light']['color']) / count($values['light']['color']),
			array_sum($values['light']['level']) / count($values['light']['level']),
			'Light levels are larger than light colors on average; are the fields swapped?'
		);

		if ($version->version >= 944) {
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
	public function testKnownItemsHaveTheirFlags(int|string $key): void
	{
		$dat = ClientFiles::client($key, sprites: false)->dat();

		[$backpackId, $goldCoinId, $crystalCoinId] = ClientFiles::knownItems($key);

		$backpack = $dat->getItem($backpackId);
		$this->assertTrue($backpack->hasFlag('container'), "Item {$backpackId} (a backpack) is a container.");
		$this->assertTrue($backpack->hasFlag('pickupable'), "Item {$backpackId} (a backpack) is pickupable.");
		$this->assertFalse($backpack->hasFlag('stackable'), "Item {$backpackId} (a backpack) is not stackable.");

		foreach (array_filter([$goldCoinId, $crystalCoinId]) as $coinId) {
			$coin = $dat->getItem($coinId);
			$this->assertTrue($coin->hasFlag('stackable'), "Item {$coinId} (a coin) is stackable.");
			$this->assertTrue($coin->hasFlag('pickupable'), "Item {$coinId} (a coin) is pickupable.");
			$this->assertFalse($coin->hasFlag('container'), "Item {$coinId} (a coin) is not a container.");
		}
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testOutfitsAndProjectilesHaveDirections(int|string $key): void
	{
		$dat = ClientFiles::client($key, sprites: false)->dat();

		// Outfits: four directions, and a second layer holding the color mask
		$citizen = $dat->getLooktype(self::CITIZEN_OUTFIT)->frameGroup(FrameGroup::IDLE);
		$this->assertSame(4, $citizen->patternX);
		$this->assertSame(2, $citizen->layers);

		// Projectiles: a 3x3 grid of directions
		$projectile = $dat->getProjectile(1)->frameGroup();
		$this->assertSame([3, 3], [$projectile->patternX, $projectile->patternY]);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testAnimationsAndFrameGroupsMatchTheVersion(int|string $key): void
	{
		$stats = ClientFiles::stats($key);
		$clientVersion = ClientFiles::version($key);

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
