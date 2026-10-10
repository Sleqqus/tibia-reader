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
	private const GOLD_COIN = 2148;
	private const CITIZEN_OUTFIT = 128;

	// Sprites decoded per version, spread evenly over the file
	private const SAMPLE_SIZE = 500;

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testSpriteFileMatchesDetectedVersionWithoutWarning(int $version): void
	{
		ClientFiles::require($version);

		// Loaded the way users do: the version detected from the .dat file, the sprite count size guessed from the .spr signature
		$client = new Client(ClientFiles::datPath($version), ClientFiles::sprPath($version));
		if ($client->versionIfKnown() === null) {
			$this->markTestSkipped('The .dat signature is not recognized (see DatReadingTest).');
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
		$this->assertSame(ClientFiles::client($version)->sprites()->spritesCount, $spritesCount);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testDataFileOnlyUsesExistingSprites(int $version): void
	{
		$sprites = ClientFiles::client($version)->sprites();

		$this->assertGreaterThan(1000, $sprites->spritesCount);
		$this->assertLessThanOrEqual($sprites->spritesCount, ClientFiles::stats($version)['maxSpriteId']);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testSpriteAddressesPointInsideTheFile(int $version): void
	{
		$client = ClientFiles::client($version);
		$count = $client->sprites()->spritesCount;
		$fileSize = filesize(ClientFiles::sprPath($version));

		// Header: signature, then the sprite count in 2 or 4 bytes, then one 4-byte address per sprite
		$tableStart = 4 + ($client->version()->hasExtendedSprites() ? 4 : 2);
		$file = new FileReader(ClientFiles::sprPath($version));
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
	public function testSpritesDecode(int $version): void
	{
		$sprites = ClientFiles::client($version)->sprites();
		$count = $sprites->spritesCount;

		$ids = array_unique([1, ...range(1, $count, max(1, intdiv($count, self::SAMPLE_SIZE))), $count]);
		foreach ($ids as $id) {
			$image = $sprites->getSprite($id, alphaChannel: true);
			$this->assertSame([32, 32], [imagesx($image), imagesy($image)], "Sprite {$id} is 32x32.");
		}
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testItemSpriteHasPixels(int $version): void
	{
		$client = ClientFiles::client($version);
		$spriteId = $client->dat()->getItem(self::GOLD_COIN)->frameGroup()->spriteIds[0];

		$this->assertGreaterThan(0, self::countOpaquePixels($client->sprites()->getSprite($spriteId)));
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testOutfitRenders(int $version): void
	{
		$frame = ClientFiles::client($version)->looktype(self::CITIZEN_OUTFIT)->setColors(10, 20, 30, 40)->getFrame();

		$this->assertGreaterThanOrEqual(32, imagesx($frame));
		$this->assertGreaterThan(0, self::countOpaquePixels($frame));
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
