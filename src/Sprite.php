<?php

declare(strict_types=1);

namespace TibiaReader;

use GdImage;

class Sprite
{
	public const TRANSPARENT_COLOR = 0xFF00FF;

	// File signatures at or above this value (Tibia 9.60+) store the sprites count as 32-bit
	private const SIGNATURE_32BIT_COUNT = 1341814009;

	// Each sprite entry starts with a 3-byte color key and a 2-byte pixel-data size
	private const SPRITE_HEADER_SIZE = 5;

	private FileReader $fileHandle;
	private int $spritesCountSize = 2;

	public int $fileSignature;
	public int $spritesCount;

	public function __construct()
	{
		if (Config::$spriteFilePath === null) {
			throw new \RuntimeException('Sprite file not specified! Please set sprite file path first.');
		}

		$this->fileHandle = new FileReader(Config::$spriteFilePath);

		$this->determineFileVersion();
		$this->readFileMetadata();
	}

	private function determineFileVersion(): void
	{
		$versionSignature = $this->fileHandle->jump(0)->readInt32();

		if ($versionSignature >= self::SIGNATURE_32BIT_COUNT) {
			$this->spritesCountSize = 4;
		}
	}

	private function readFileMetadata(): void
	{
		$this->fileHandle->jump(0);
		$this->fileSignature = $this->fileHandle->readInt32();
		$this->spritesCount = $this->fileHandle->readBytes($this->spritesCountSize);
	}

	/**
	 * Returns start & end position of sprite content in file.
	 *
	 * @return array{0: int, 1: int}
	 */
	private function getSpriteAddress(int $spriteId): array
	{
		// Skip file signature and sprites count
		$firstSpriteAddressAt = 4 + $this->spritesCountSize;

		$spriteAddressAt = $firstSpriteAddressAt + (($spriteId - 1) * 4);

		// If sprite has no address in file assigned, jump to first, blank sprite
		if ($this->fileHandle->jump($spriteAddressAt)->readInt32() === 0) {
			$spriteAddressAt = $firstSpriteAddressAt;
		}

		$spriteBegin = $this->fileHandle->jump($spriteAddressAt)->readInt32();

		// Next sprite address is also end of previous sprite
		// If next sprite has no address, jump to next until found
		$spriteEnd = $this->fileHandle->jump($spriteAddressAt + 4)->readInt32();
		while ($spriteEnd === 0) {
			$spriteEnd = $this->fileHandle->readInt32();
		}

		return [$spriteBegin, $spriteEnd];
	}

	public function getSprite(int $spriteId, bool $alphaChannel = false): GdImage
	{
		if ($spriteId === 0) {
			$spriteId = 1;
		}

		if ($spriteId > $this->spritesCount) {
			throw new \OutOfRangeException("Specified sprite ID ({$spriteId}) is bigger than sprites count ({$this->spritesCount})!");
		}

		[$spriteBegin, $spriteEnd] = $this->getSpriteAddress($spriteId);

		$this->fileHandle->jump($spriteBegin + self::SPRITE_HEADER_SIZE);

		// Initialize empty image and fill it with default transparency color (pink)
		$image = imagecreatetruecolor(32, 32);
		imagefill($image, 0, 0, self::TRANSPARENT_COLOR);

		// Pixel data is run-length encoded: pairs of (transparent pixels, colored pixels),
		// followed by 3 bytes of RGB per colored pixel
		$pixel = 1;
		while ($this->fileHandle->getPos() < $spriteEnd) {
			$pixel += $this->fileHandle->readInt16();
			$coloredPixelsEnd = $this->fileHandle->readInt16() + $pixel;
			while ($pixel < $coloredPixelsEnd) {
				$color = ($this->fileHandle->readByte() << 16) + ($this->fileHandle->readByte() << 8) + $this->fileHandle->readByte();
				imagesetpixel($image, ($pixel - 1) % 32, intdiv($pixel - 1, 32), $color);
				$pixel++;
			}
		}

		if ($alphaChannel) {
			imagecolortransparent($image, self::TRANSPARENT_COLOR);
		}

		return $image;
	}

	public function printSprite(int $spriteId, bool $alphaChannel = false): void
	{
		$image = $this->getSprite($spriteId, $alphaChannel);

		header('Content-type: image/png');
		imagepng($image);
	}

	public function printFileInformation(): void
	{
		print 'Sprite file name: ' . basename(Config::$spriteFilePath) . '<br>';
		print 'Sprites: ' . $this->spritesCount . '<br>';
	}
}
