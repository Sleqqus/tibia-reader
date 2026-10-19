<?php

declare(strict_types=1);

namespace TibiaReader\Tests;

use GdImage;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TibiaReader\Client;
use TibiaReader\FileReader;
use TibiaReader\Sprite;

/**
 * Reads the real .spr file of every supported version and checks it against its .dat file.
 */
final class SpriteReadingTest extends TestCase
{
	private const CITIZEN_OUTFIT = 128;

	// Sprites decoded per version, spread evenly over the file
	private const SAMPLE_SIZE = 500;

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testSpriteFileMatchesDetectedVersionWithoutWarning(int|string $key): void
	{
		ClientFiles::require($key);

		// Loaded the way users do: the version detected from the .dat file, the sprite count size told from the .spr layout
		$client = new Client(ClientFiles::datPath($key), ClientFiles::sprPath($key));
		if ($client->versionIfKnown() === null) {
			$this->markTestSkipped('The .dat file is not recognized (see DatReadingTest).');
		}

		$warnings = [];
		set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
			$warnings[] = $message;
			return true;
		}, E_USER_WARNING);

		try {
			$spritesCount = $client->sprites()->spritesCount;
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $warnings);
		$this->assertSame(ClientFiles::client($key)->sprites()->spritesCount, $spritesCount);
	}

	/**
	 * Without a .dat file the size of the sprite count is told from the layout of the .spr file alone.
	 */
	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testSpriteCountIsReadWithoutDataFile(int|string $key): void
	{
		ClientFiles::require($key);

		$this->assertSame(
			ClientFiles::client($key)->sprites()->spritesCount,
			(new Client(spriteFilePath: ClientFiles::sprPath($key)))->sprites()->spritesCount
		);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testDataFileOnlyUsesExistingSprites(int|string $key): void
	{
		$sprites = ClientFiles::client($key)->sprites();

		$this->assertGreaterThan(1000, $sprites->spritesCount);
		$this->assertLessThanOrEqual($sprites->spritesCount, ClientFiles::stats($key)['maxSpriteId']);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testSpriteAddressesPointInsideTheFile(int|string $key): void
	{
		$client = ClientFiles::client($key);
		$count = $client->sprites()->spritesCount;
		$fileSize = filesize(ClientFiles::sprPath($key));

		// Header: signature, then the sprite count in 2 or 4 bytes, then one 4-byte address per sprite
		$tableStart = 4 + ($client->version()->hasExtendedSprites() ? 4 : 2);
		$file = new FileReader(ClientFiles::sprPath($key));
		$addresses = array_values(unpack('V*', $file->jump($tableStart)->readText($count * 4)));

		$outside = [];
		foreach ($addresses as $index => $address) {
			// 0 marks an empty sprite; others hold a 3-byte color key and a 2-byte size before the pixels
			if ($address !== 0 && ($address < $tableStart + $count * 4 || $address + 5 > $fileSize)) {
				$outside[] = $index + 1;
			}
		}

		$this->assertSame([], array_slice($outside, 0, 10), 'Sprites whose address is outside the file.');
		$this->assertGreaterThan($count / 2, count(array_filter($addresses)), 'Most sprites are not empty.');
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testSpritesDecode(int|string $key): void
	{
		$sprites = ClientFiles::client($key)->sprites();
		$count = $sprites->spritesCount;

		$ids = array_unique([1, ...range(1, $count, max(1, intdiv($count, self::SAMPLE_SIZE))), $count]);
		foreach ($ids as $id) {
			$image = $sprites->getSprite($id, alphaChannel: true);
			$this->assertSame([32, 32], [imagesx($image), imagesy($image)], "Sprite {$id} is 32x32.");
		}
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testItemSpriteHasPixels(int|string $key): void
	{
		$client = ClientFiles::client($key);
		$spriteId = $client->dat()->getItem(ClientFiles::knownItems($key)[1])->frameGroup()->spriteIds[0];

		$this->assertGreaterThan(0, self::countOpaquePixels($client->sprites()->getSprite($spriteId)));
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testOutfitRenders(int|string $key): void
	{
		$frame = ClientFiles::client($key)->looktype(self::CITIZEN_OUTFIT)->setColors(10, 20, 30, 40)->getFrame();

		$this->assertGreaterThanOrEqual(32, imagesx($frame));
		$this->assertGreaterThan(0, self::countOpaquePixels($frame));
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testCitizenStandsStill(int|string $key): void
	{
		$citizen = ClientFiles::client($key)->looktype(self::CITIZEN_OUTFIT);

		$this->assertSame(1, $citizen->idlePhases());
		$this->assertCount(1, $citizen->idleDurations());
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testIdlePhasesRender(int|string $key): void
	{
		$client = ClientFiles::client($key);
		$dat = $client->dat();

		// The first looktype that moves while standing still, if the version has one
		for ($id = 1; $id <= $dat->fileLooktypes; $id++) {
			$outfit = $client->looktype($id);
			if ($outfit->idlePhases() > 1) {
				break;
			}
		}
		if ($outfit->idlePhases() < 2) {
			$this->markTestSkipped("{$key} has no looktype that moves while standing still");
		}

		$durations = $outfit->idleDurations();
		$this->assertCount($outfit->idlePhases(), $durations);
		$this->assertGreaterThan(0, array_sum($durations));

		$first = $outfit->setIdlePhase(0)->getFrame();
		$last = $outfit->setIdlePhase($outfit->idlePhases() - 1)->getFrame();
		$this->assertNotSame(self::pixels($first), self::pixels($last), "looktype {$id} changes while standing");
	}

	private static function pixels(GdImage $image): string
	{
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	private static function countOpaquePixels(GdImage $image): int
	{
		$transparent = imagecolortransparent($image);
		$opaque = 0;

		for ($x = 0; $x < imagesx($image); $x++) {
			for ($y = 0; $y < imagesy($image); $y++) {
				$color = imagecolorat($image, $x, $y);
				$alpha = imageistruecolor($image) ? ($color >> 24) & 0x7F : 0;
				if ($color !== $transparent && ($color & 0xFFFFFF) !== Sprite::TRANSPARENT_COLOR && $alpha < 127) {
					$opaque++;
				}
			}
		}

		return $opaque;
	}
}
