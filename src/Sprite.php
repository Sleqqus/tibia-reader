<?php

declare(strict_types=1);

namespace TibiaReader;

use GdImage;

/**
 * Reader for .spr files: decodes 32x32 sprites into GD images.
 */
class Sprite
{
	public const TRANSPARENT_COLOR = 0xFF00FF;

	// Each sprite entry starts with a 3-byte color key, followed by a 2-byte pixel-data size
	private const COLOR_KEY_SIZE = 3;

	// Decoded sprites kept in memory (about 3 KB each), so outfits reuse sprites they draw repeatedly
	private const DECODED_CACHE_LIMIT = 2048;

	private const BMP_HEADER_SIZE = 54;

	private FileReader $fileHandle;
	private int $spritesCountSize = 2;
	private string $spriteFilePath;
	private bool $gdReadsBmp;

	public int $fileSignature;
	public int $spritesCount;

	/** @var array<int, string> sprite id => 32x32 24-bit BMP file */
	private array $decoded = [];

	public function __construct(?Client $client = null)
	{
		$client ??= Config::client();

		if ($client->spriteFilePath === null) {
			throw new \RuntimeException('Sprite file not specified! Please set sprite file path first.');
		}

		$this->spriteFilePath = $client->spriteFilePath;
		$this->fileHandle = new FileReader($client->spriteFilePath);
		$this->gdReadsBmp = (gd_info()['BMP Support'] ?? false) === true;

		$this->fileSignature = $this->fileHandle->jump(0)->readInt32();
		$this->spritesCountSize = $this->usesExtendedCount($client) ? 4 : 2;
		$this->spritesCount = $this->fileHandle->readBytes($this->spritesCountSize);
	}

	private function usesExtendedCount(Client $client): bool
	{
		$extendedBySignature = ClientVersion::sprSignatureIsExtended($this->fileSignature);

		// An explicitly set version wins, since custom clients may use any signature
		if ($client->clientVersion !== null) {
			return $client->versionIfKnown()->hasExtendedSprites();
		}

		$datVersion = $client->versionIfKnown();
		if ($datVersion !== null && $datVersion->hasExtendedSprites() !== $extendedBySignature) {
			trigger_error(
				"The sprite file looks like it belongs to a different client version than the data file ({$datVersion}).",
				E_USER_WARNING
			);
		}

		return $extendedBySignature;
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

		$bitmap = $this->decoded[$spriteId] ?? $this->decode($spriteId);
		$image = $this->gdReadsBmp ? imagecreatefromstring($bitmap) : self::imageFromBitmap($bitmap);

		if ($alphaChannel) {
			imagecolortransparent($image, self::TRANSPARENT_COLOR);
		}

		return $image;
	}

	/**
	 * Decodes a sprite into a 24-bit BMP file, with transparent pixels in TRANSPARENT_COLOR.
	 */
	private function decode(int $spriteId): string
	{
		// BMP stores pixels as BGR; magenta is the same bytes either way
		$pixels = str_repeat("\xFF\x00\xFF", 32 * 32);

		// Sprite id 0, and ids with no address assigned, are empty sprites
		$spriteAddress = ($spriteId > 0) ? $this->getSpriteAddress($spriteId) : 0;

		if ($spriteAddress !== 0) {
			$this->fileHandle->jump($spriteAddress + self::COLOR_KEY_SIZE);
			$data = $this->fileHandle->readText($this->fileHandle->readInt16());
			$dataEnd = strlen($data);

			// Pixel data is run-length encoded: pairs of (transparent pixels, colored pixels),
			// followed by 3 bytes of RGB per colored pixel
			$position = 0;
			$pixel = 0;
			while ($position + 4 <= $dataEnd) {
				[, $transparent, $colored] = unpack('v2', $data, $position);
				$position += 4;
				$pixel += $transparent;

				for ($end = min($pixel + $colored, 1024); $pixel < $end && $position + 3 <= $dataEnd; $pixel++, $position += 3) {
					// BMP rows run bottom to top
					$offset = ((31 - ($pixel >> 5)) * 32 + ($pixel & 31)) * 3;
					$pixels[$offset]     = $data[$position + 2];
					$pixels[$offset + 1] = $data[$position + 1];
					$pixels[$offset + 2] = $data[$position];
				}
			}
		}

		$bitmap = 'BM' . pack('V', self::BMP_HEADER_SIZE + strlen($pixels)) . "\0\0\0\0" . pack('V', self::BMP_HEADER_SIZE)
			. pack('VVVvvVVVVVV', 40, 32, 32, 1, 24, 0, strlen($pixels), 2835, 2835, 0, 0) . $pixels;

		if (count($this->decoded) >= self::DECODED_CACHE_LIMIT) {
			unset($this->decoded[array_key_first($this->decoded)]);
		}

		return $this->decoded[$spriteId] = $bitmap;
	}

	// Fallback for GD builds without BMP support
	private static function imageFromBitmap(string $bitmap): GdImage
	{
		$image = imagecreatetruecolor(32, 32);
		imagefill($image, 0, 0, self::TRANSPARENT_COLOR);

		for ($i = 0; $i < 1024; $i++) {
			$offset = self::BMP_HEADER_SIZE + $i * 3;
			$color = (ord($bitmap[$offset + 2]) << 16) | (ord($bitmap[$offset + 1]) << 8) | ord($bitmap[$offset]);
			if ($color !== self::TRANSPARENT_COLOR) {
				imagesetpixel($image, $i & 31, 31 - ($i >> 5), $color);
			}
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
		print 'Sprite file name: ' . htmlspecialchars(basename($this->spriteFilePath)) . '<br>';
		print 'Sprites: ' . $this->spritesCount . '<br>';
	}
}
