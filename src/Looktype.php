<?php

declare(strict_types=1);

namespace TibiaReader;

use GdImage;

class Looktype
{
	public const ADDON_NONE = 0;
	public const ADDON_FIRST = 1;
	public const ADDON_SECOND = 2;
	public const ADDON_BOTH = 3;

	public const DIRECTION_NORTH = 0;
	public const DIRECTION_EAST = 1;
	public const DIRECTION_SOUTH = 2;
	public const DIRECTION_WEST = 3;

	private const TRANSPARENT_COLOR = 0xFF00FF;

	// Colors used by the outfit template (color mask) layer
	private const MASK_YELLOW = 0xFFFF00; // head
	private const MASK_RED    = 0xFF0000; // body
	private const MASK_GREEN  = 0x00FF00; // legs
	private const MASK_BLUE   = 0x0000FF; // feet

	// The 133 outfit colors selectable in the client
	private const OUTFIT_COLORS = [
		0xFFFFFF, 0xFFD4BF, 0xFFE9BF, 0xFFFFBF, 0xE9FFBF, 0xD4FFBF, 0xBFFFBF, 0xBFFFD4, 0xBFFFE9, 0xBFFFFF, 0xBFE9FF, 0xBFD4FF, 0xBFBFFF, 0xD4BFFF, 0xE9BFFF, 0xFFBFFF,
		0xFFBFE9, 0xFFBFD4, 0xFFBFBF, 0xDADADA, 0xBF9F8F, 0xBFAF8F, 0xBFBF8F, 0xAFBF8F,	0x9FBF8F, 0x8FBF8F, 0x8FBF9F, 0x8FBFAF, 0x8FBFBF, 0x8FAFBF, 0x8F9FBF, 0x8F8FBF,
		0x9F8FBF, 0xAF8FBF, 0xBF8FBF, 0xBF8FAF, 0xBF8F9F, 0xBF8F8F, 0xB6B6B6, 0xBF7F5F, 0xBFAF8F, 0xBFBF5F, 0x9FBF5F, 0x7FBF5F, 0x5FBF5F, 0x5FBF7F, 0x5FBF9F, 0x5FBFBF,
		0x5F9FBF, 0x5F7FBF, 0x5F5FBF, 0x7F5FBF, 0x9F5FBF, 0xBF5FBF, 0xBF5F9F, 0xBF5F7F, 0xBF5F5F, 0x919191, 0xBF6A3F, 0xBF943F, 0xBFBF3F, 0x94BF3F, 0x6ABF3F, 0x3FBF3F,
		0x3FBF6A, 0x3FBF94, 0x3FBFBF, 0x3F94BF, 0x3F6ABF, 0x3F3FBF, 0x6A3FBF, 0x943FBF, 0xBF3FBF, 0xBF3F94, 0xBF3F6A, 0xBF3F3F, 0x6D6D6D, 0xFF5500, 0xFFAA00, 0xFFFF00,
		0xAAFF00, 0x54FF00, 0x00FF00, 0x00FF54, 0x00FFAA, 0x00FFFF, 0x00A9FF, 0x0055FF, 0x0000FF, 0x5500FF, 0xA900FF, 0xFE00FF, 0xFF00AA, 0xFF0055, 0xFF0000, 0x484848,
		0xBF3F00, 0xBF7F00, 0xBFBF00, 0x7FBF00, 0x3FBF00, 0x00BF00, 0x00BF3F, 0x00BF7F, 0x00BFBF, 0x007FBF, 0x003FBF, 0x0000BF, 0x3F00BF, 0x7F00BF, 0xBF00BF, 0xBF007F,
		0xBF003F, 0xBF0000, 0x242424, 0x7F2A00, 0x7F5500, 0x7F7F00, 0x557F00, 0x2A7F00, 0x007F00, 0x007F2A, 0x007F55, 0x007F7F, 0x00547F, 0x002A7F, 0x00007F, 0x2A007F,
		0x54007F, 0x7F007F, 0x7F0055, 0x7F002A, 0x7F0000,
	];

	private DatObject $objectInstance;
	private ?Looktype $mountObjectInstance = null;

	private array $looktypeColors = [0, 0, 0, 0];
	private int $looktypeAddons = self::ADDON_NONE;
	private int $looktypeMounted = 0;

	private int $looktypeDirection = self::DIRECTION_SOUTH;
	private int $looktypeStep = 0;

	public function __construct(int $looktypeId)
	{
		$this->setLooktype($looktypeId);
	}

	private function setLooktype(int $looktypeId): static
	{
		$this->objectInstance = new DatObject();
		$this->objectInstance->getLooktype($looktypeId);

		return $this;
	}

	public function setColors(int $head, int $body, int $legs, int $feet): static
	{
		$this->looktypeColors = [$head, $body, $legs, $feet];

		return $this;
	}

	public function setAddons(int $addons): static
	{
		$this->looktypeAddons = $addons;

		return $this;
	}

	public function setMount(int|bool $mountId): ?Looktype
	{
		$this->looktypeMounted = 1;

		if ($mountId !== true) {
			$this->mountObjectInstance = new Looktype($mountId);

			return $this->mountObjectInstance;
		}

		return null;
	}

	public function setStep(int $step): static
	{
		$this->looktypeStep = $step;

		return $this;
	}

	public function setDirection(int $direction): static
	{
		$this->looktypeDirection = $direction;

		return $this;
	}

	public function getFrame(): GdImage
	{
		return self::getColorizedOutfitFrameWithAddons(
			$this->objectInstance->object,
			$this->looktypeColors,
			$this->looktypeDirection,
			$this->looktypeAddons,
			$this->looktypeStep,
			$this->looktypeMounted
		);
	}

	public function getFrameMap(): GdImage
	{
		$frameMap = imagecreatetruecolor(512, 256);

		for ($direction = 0; $direction <= 3; $direction++) {
			for ($step = 0; $step <= 7; $step++) {
				$image = self::getColorizedOutfitFrameWithAddons($this->objectInstance->object, $this->looktypeColors, $direction, $this->looktypeAddons, $step + 1);
				self::copyImageToPosition($frameMap, $image, $step * 64, $direction * 64);
			}
		}

		return $frameMap;
	}

	/* ---------------------------------------------------------------------------------------------------- */
	/* ----- FRAME COMPOSITING                                                                               */
	/* ---------------------------------------------------------------------------------------------------- */

	private static function getDefaultFramegroup(array $looktypeObject): array
	{
		return $looktypeObject['frameGroups'][0] ?? $looktypeObject;
	}

	private static function looktypeHasLayers(array $looktypeObject): bool
	{
		return self::getDefaultFramegroup($looktypeObject)['layers'] > 1;
	}

	private static function looktypeHasAddons(array $looktypeObject): bool
	{
		return self::getDefaultFramegroup($looktypeObject)['divy'] > 1;
	}

	private static function looktypeHasMount(array $looktypeObject): bool
	{
		return self::getDefaultFramegroup($looktypeObject)['divz'] > 1;
	}

	private static function generateFrameLayer(array $looktypeObject, int $layer = 0, int $direction = 0, int $addon = 0, int $mounted = 0, int $step = 0): GdImage
	{
		// Get target frameGroup (if any) and step value
		if (isset($looktypeObject['frameGroups'])) {
			$multipleFrameGroups = count($looktypeObject['frameGroups']) > 1;

			// Step 0 is the idle frame group; moving steps live in the second group
			$targetFrameGroup = ($multipleFrameGroups && $step !== 0) ? 1 : 0;
			$targetStep = ($step === 0) ? 0 : ($step - 1);
			$frameGroup = $looktypeObject['frameGroups'][$targetFrameGroup];
		} else {
			$targetStep = $step;
			$frameGroup = $looktypeObject;
		}

		// Calculate parts of frame set
		$spritesPerLayer     = $frameGroup['width'] * $frameGroup['height'];
		$spritesPerDirection = $spritesPerLayer * $frameGroup['layers'];
		$spritesPerAddon     = $spritesPerDirection * $frameGroup['divx'];
		$spritesPerMount     = $spritesPerAddon * $frameGroup['divy'];
		$spritesPerStep      = $spritesPerMount * $frameGroup['divz'];

		// Calculate position in frame set
		$index  = $spritesPerLayer * $layer;
		$index += $spritesPerDirection * $direction;
		$index += $spritesPerMount * $mounted;
		$index += $spritesPerAddon * $addon;
		$index += $spritesPerStep * $targetStep;

		// Merge sprites to flat image
		$frameSet = array_slice($frameGroup['spriteIds'], $index, $spritesPerLayer);

		$frame = self::createBlankImage($frameGroup['width'], $frameGroup['height']);
		$sprite = new Sprite();

		for ($y = $frameGroup['height']; $y > 0; $y--) {
			for ($x = $frameGroup['width']; $x > 0; $x--) {
				$currentSprite = $sprite->getSprite((int) array_shift($frameSet));
				self::copySpriteToPosition($frame, $currentSprite, $x, $y);
			}
		}

		return $frame;
	}

	/*
	 * Returns colorized outfit frame with addons (if looktype has no mask layer, raw colors remain)
	 */
	private static function getColorizedOutfitFrameWithAddons(array $looktypeObject, array $colors, int $direction, int $addons, int $step, int $mounted = 0): GdImage
	{
		if ($mounted && !self::looktypeHasMount($looktypeObject)) {
			$mounted = 0;
		}

		$targetImage = self::generateFrameLayer($looktypeObject, 0, $direction, self::ADDON_NONE, $mounted, $step);

		$hasColorLayer = self::looktypeHasLayers($looktypeObject);
		if ($hasColorLayer) {
			$colorMask = self::generateFrameLayer($looktypeObject, 1, $direction, self::ADDON_NONE, $mounted, $step);
		}

		if ($addons && self::looktypeHasAddons($looktypeObject)) {
			foreach ([self::ADDON_FIRST, self::ADDON_SECOND] as $addonLayer) {
				if (!($addons & $addonLayer)) {
					continue;
				}

				$addonImage = self::generateFrameLayer($looktypeObject, 0, $direction, $addonLayer, $mounted, $step);
				self::copyImage($targetImage, $addonImage, true);

				if ($hasColorLayer) {
					$addonImageColorMask = self::generateFrameLayer($looktypeObject, 1, $direction, $addonLayer, $mounted, $step);
					self::copyImage($colorMask, $addonImageColorMask, true);
				}
			}
		}

		if ($hasColorLayer) {
			self::colorize($targetImage, $colorMask, $colors);
		}

		return $targetImage;
	}

	/* ---------------------------------------------------------------------------------------------------- */
	/* ----- COLORIZING                                                                                      */
	/* ---------------------------------------------------------------------------------------------------- */

	private static function getColorByPart(int $maskColor, array $bodypartColors): int|false
	{
		return match ($maskColor) {
			self::MASK_YELLOW => $bodypartColors[0], // head
			self::MASK_RED    => $bodypartColors[1], // body
			self::MASK_GREEN  => $bodypartColors[2], // legs
			self::MASK_BLUE   => $bodypartColors[3], // feet
			default           => false,
		};
	}

	private static function colorizePixel(int $originalPixel, int $targetColor): int
	{
		$newColor = self::OUTFIT_COLORS[$targetColor];

		$r = (int) (((($originalPixel >> 16) & 0xFF)) * ((($newColor >> 16) & 0xFF) / 255));
		$g = (int) (((($originalPixel >> 8) & 0xFF))  * ((($newColor >> 8) & 0xFF) / 255));
		$b = (int) ((($originalPixel & 0xFF))         * (($newColor & 0xFF) / 255));

		return ($r << 16) + ($g << 8) + $b;
	}

	public static function colorize(GdImage $image, GdImage $colorMask, array $looktypeColors): void
	{
		$width = imagesx($image);
		$height = imagesy($image);

		for ($x = 0; $x < $width; $x++) {
			for ($y = 0; $y < $height; $y++) {
				$originalPixel = imagecolorat($image, $x, $y);

				if ($originalPixel === self::TRANSPARENT_COLOR) {
					continue;
				}

				$maskColor = imagecolorat($colorMask, $x, $y);
				$targetColor = self::getColorByPart($maskColor, $looktypeColors);

				if ($targetColor === false || $targetColor === 0) {
					continue;
				}

				imagesetpixel($image, $x, $y, self::colorizePixel($originalPixel, $targetColor));
			}
		}
	}

	/* ---------------------------------------------------------------------------------------------------- */
	/* ----- IMAGE HELPERS                                                                                   */
	/* ---------------------------------------------------------------------------------------------------- */

	/*
	 * Creates blank (pink) image. $width and $height in sprites (1 = 32px)
	 */
	private static function createBlankImage(int $width = 1, int $height = 1): GdImage
	{
		$frame = imagecreatetruecolor($width * 32, $height * 32);
		imagefill($frame, 0, 0, self::TRANSPARENT_COLOR);

		return $frame;
	}

	private static function copyImageToPosition(GdImage $target, GdImage $source, int $targetX, int $targetY, bool $transparent = false): void
	{
		if ($transparent) {
			imagecolortransparent($source, self::TRANSPARENT_COLOR);
		}
		imagecopymerge($target, $source, $targetX, $targetY, 0, 0, imagesx($source), imagesy($source), 100);
	}

	private static function copySpriteToPosition(GdImage $target, GdImage $source, int $targetX, int $targetY, bool $transparent = false): void
	{
		self::copyImageToPosition($target, $source, ($targetX - 1) * 32, ($targetY - 1) * 32, $transparent);
	}

	private static function copyImage(GdImage $target, GdImage $source, bool $transparent = false): void
	{
		self::copyImageToPosition($target, $source, 0, 0, $transparent);
	}
}
