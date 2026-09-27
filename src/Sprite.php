<?php

declare(strict_types=1);

namespace TibiaReader;

use GdImage;

class Sprite
{
	public const TRANSPARENT_COLOR = 0xFF00FF;

	// File signatures at or above this value (Tibia 9.60+) store the sprites count as 32-bit
	private const SIGNATURE_32BIT_COUNT = 1341814009;

	// Each sprite entry starts with a 3-byte color key, followed by a 2-byte pixel-data size
	private const COLOR_KEY_SIZE = 3;

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
	 * Returns position of sprite content in file, or 0 if the sprite is empty.
	 */
	private function getSpriteAddress(int $spriteId): int
	{
		// Skip file signature and sprites count
		$firstSpriteAddressAt = 4 + $this->spritesCountSize;

		return $this->fileHandle->jump($firstSpriteAddressAt + (($spriteId - 1) * 4))->readInt32();
	}

	public function getSprite(int $spriteId, bool $alphaChannel = false): GdImage
	{
		if ($spriteId > $this->spritesCount) {
			throw new \OutOfRangeException("Specified sprite ID ({$spriteId}) is bigger than sprites count ({$this->spritesCount})!");
		}

		// Initialize empty image and fill it with default transparency color (pink)
		$image = imagecreatetruecolor(32, 32);
		imagefill($image, 0, 0, self::TRANSPARENT_COLOR);

		// Sprite id 0, and ids with no address assigned, are empty sprites
		$spriteAddress = ($spriteId > 0) ? $this->getSpriteAddress($spriteId) : 0;

		if ($spriteAddress !== 0) {
			$this->decodePixels($image, $spriteAddress);
		}

		if ($alphaChannel) {
			imagecolortransparent($image, self::TRANSPARENT_COLOR);
		}

		return $image;
	}

	private function decodePixels(GdImage $image, int $spriteAddress): void
	{
		$this->fileHandle->jump($spriteAddress + self::COLOR_KEY_SIZE);

		$dataSize = $this->fileHandle->readInt16();
		$spriteEnd = $this->fileHandle->getPos() + $dataSize;

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
