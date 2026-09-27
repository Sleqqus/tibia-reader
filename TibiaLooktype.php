<?php
	namespace TibiaReader;

	define("COLOR_PINK",   16711935);
    define("COLOR_RED",    16776960);
    define("COLOR_YELLOW", 16711680);
    define("COLOR_GREEN",  65280);
    define("COLOR_BLUE",   255);

	define("ADDON_NONE",    0);
	define("ADDON_FIRST",   1);
	define("ADDON_SECOND",  2);
	define("ADDON_BOTH",    3);

	define("LOOKTYPE_MOUNTED",   1);
	define("LOOKTYPE_UNMOUNTED", 0);

	class Looktype {

		private $objectInstance;
		private $mountObjectInstance = null;

		private $looktypeColors = [0, 0, 0, 0];
		private $looktypeAddons = 0;
		private $looktypeMounted = 0;

		private $looktypeDirection = 2;
		// private $looktypeMove = false;
		private $looktypeStep = 0;



		// STATIC PART
		static protected $colors = array(
			0xFFFFFF, 0xFFD4BF, 0xFFE9BF, 0xFFFFBF, 0xE9FFBF, 0xD4FFBF, 0xBFFFBF, 0xBFFFD4, 0xBFFFE9, 0xBFFFFF, 0xBFE9FF, 0xBFD4FF, 0xBFBFFF, 0xD4BFFF, 0xE9BFFF, 0xFFBFFF,
			0xFFBFE9, 0xFFBFD4, 0xFFBFBF, 0xDADADA, 0xBF9F8F, 0xBFAF8F, 0xBFBF8F, 0xAFBF8F,	0x9FBF8F, 0x8FBF8F, 0x8FBF9F, 0x8FBFAF, 0x8FBFBF, 0x8FAFBF, 0x8F9FBF, 0x8F8FBF,
			0x9F8FBF, 0xAF8FBF, 0xBF8FBF, 0xBF8FAF, 0xBF8F9F, 0xBF8F8F, 0xB6B6B6, 0xBF7F5F, 0xBFAF8F, 0xBFBF5F, 0x9FBF5F, 0x7FBF5F, 0x5FBF5F, 0x5FBF7F, 0x5FBF9F, 0x5FBFBF,
			0x5F9FBF, 0x5F7FBF, 0x5F5FBF, 0x7F5FBF, 0x9F5FBF, 0xBF5FBF, 0xBF5F9F, 0xBF5F7F, 0xBF5F5F, 0x919191, 0xBF6A3F, 0xBF943F, 0xBFBF3F, 0x94BF3F, 0x6ABF3F, 0x3FBF3F,
			0x3FBF6A, 0x3FBF94, 0x3FBFBF, 0x3F94BF, 0x3F6ABF, 0x3F3FBF, 0x6A3FBF, 0x943FBF, 0xBF3FBF, 0xBF3F94, 0xBF3F6A, 0xBF3F3F, 0x6D6D6D, 0xFF5500, 0xFFAA00, 0xFFFF00,
			0xAAFF00, 0x54FF00, 0x00FF00, 0x00FF54, 0x00FFAA, 0x00FFFF, 0x00A9FF, 0x0055FF, 0x0000FF, 0x5500FF, 0xA900FF, 0xFE00FF, 0xFF00AA, 0xFF0055, 0xFF0000, 0x484848,
			0xBF3F00, 0xBF7F00, 0xBFBF00, 0x7FBF00, 0x3FBF00, 0x00BF00, 0x00BF3F, 0x00BF7F, 0x00BFBF, 0x007FBF, 0x003FBF, 0x0000BF, 0x3F00BF, 0x7F00BF, 0xBF00BF, 0xBF007F,
			0xBF003F, 0xBF0000, 0x242424, 0x7F2A00, 0x7F5500, 0x7F7F00, 0x557F00, 0x2A7F00, 0x007F00, 0x007F2A, 0x007F55, 0x007F7F, 0x00547F, 0x002A7F, 0x00007F, 0x2A007F,
			0x54007F, 0x7F007F, 0x7F0055, 0x7F002A, 0x7F0000
		);


		private static function getDefaultFramegroup($looktypeObject) {
			$frameGroups = isset($looktypeObject["frameGroups"]);
			if ($frameGroups) {
				return $looktypeObject["frameGroups"][0];
			} else {
				return $looktypeObject;
			}
		}

		private static function looktypeHasLayers($looktypeObject) {
			$frameGroup = SELF::getDefaultFramegroup($looktypeObject);
			return ($frameGroup["layers"] > 1);
		}

		private static function looktypeHasAddons($looktypeObject) {
			$frameGroup = SELF::getDefaultFramegroup($looktypeObject);
			return ($frameGroup["divy"] > 1);
		}

		private static function looktypeHasMount($looktypeObject) {
			$frameGroup = SELF::getDefaultFramegroup($looktypeObject);
			return ($frameGroup["divz"] > 1);
		}




		protected static function getColorByPart($maskColor, $bodypartColors) {
			switch($maskColor) {
				case COLOR_RED:    return $bodypartColors[0];
				case COLOR_YELLOW: return $bodypartColors[1];
				case COLOR_GREEN:  return $bodypartColors[2];
				case COLOR_BLUE:   return $bodypartColors[3];
				default:           return false;
			}
		}


		/*
		 *  Creates blank (pink) image. $width and $height in sprites (1 = 32px)
		 */
		static protected function createBlankImage($width = 1, $height = 1) {
			$frame = imagecreatetruecolor($width*32, $height*32);
			imagefill($frame, 0, 0, imagecolorallocate($frame, 255, 0, 255));
			return $frame;
		}


		static protected function copyImageToPosition(&$target, $source, $targetX, $targetY, $transparent = false) {
			if ($transparent) {
				imagecolortransparent($source, ((255 << 16) + (0 << 8) + (255)));
			}
			imagecopymerge($target, $source, $targetX, $targetY, 0, 0, imagesx($source), imagesy($source), 100);
		}

		static protected function copySpriteToPosition(&$target, $source, $targetX, $targetY, $transparent = false) {
			SELF::copyImageToPosition($target, $source, ($targetX-1)*32, ($targetY-1)*32, $transparent);
		}

		static protected function copyImage(&$target, $source, $transparent = false) {
			SELF::copyImageToPosition($target, $source, 0, 0, $transparent);
		}


		static protected function toRGB($color) {
			$r = ($color >> 16) & 0xFF;
			$g = ($color >> 8) & 0xFF;
			$b = $color & 0xFF;
			return Array($r, $g, $b);
		}

		static protected function toIndex($r, $g, $b) {
            return ($r << 16) + ($g << 8) + $b;
        }

		static protected function colorizePixel($originalPixel, $targetColor) {
			$newColor      = SELF::$colors[$targetColor];
			$originalRGB   = SELF::toRGB($originalPixel);

			$newColor_R = ($newColor & 0xFF0000) >> 16;
			$newColor_G = ($newColor & 0xFF00) >> 8;
			$newColor_B = ($newColor & 0xFF);
			$r = (int)($originalRGB[0] * ($newColor_R / 255));
			$g = (int)($originalRGB[1] * ($newColor_G / 255));
			$b = (int)($originalRGB[2] * ($newColor_B / 255));

			$value = SELF::toIndex($r, $g, $b);
			return $value;
		}

		static function colorize($image, $colorMask, $looktypeColors) {
			$width = imagesx($image);
            $height = imagesy($image);

            for($x = 0; $x < $width; $x++) {
                for($y = 0; $y < $height; $y++) {

                    $originalPixel = ImageColorAt($image, $x, $y);

                    if ($originalPixel == COLOR_PINK)
                        continue;

                    $maskColor = ImageColorAt($colorMask, $x, $y);
                    $targetColor = SELF::getColorByPart($maskColor, $looktypeColors);

                    if (($targetColor === false) || ($targetColor == 0))
                        continue;

                    $pixelColor = self::colorizePixel($originalPixel, $targetColor);
                    imagesetpixel($image, $x, $y, $pixelColor);
                }
            }
		}



		static private function generateFrameLayer($looktypeObject, $layer = 0, $direction = 0, $addon = 0, $mounted = 0, $inMove = 0, $step = 0) {

			// Get target frameGroup (if any) and step value
			$frameGroups = isset($looktypeObject["frameGroups"]);

			if ($frameGroups) {
				$multipleFrameGroups = count($looktypeObject["frameGroups"]) > 1;

				if ($multipleFrameGroups) {
					$targetFrameGroup = ($step === 0) ? 0 : 1;
				} else {
					$targetFrameGroup = 0;
				}

				$targetStep = ($step === 0) ? 0 : ($step - 1);
				$frameGroup = $looktypeObject["frameGroups"][$targetFrameGroup];
			} else {
				$targetStep = $step;
				$frameGroup = $looktypeObject;
			}


			// Calculate parts of frame set
			$spritesPerLayer     = ($frameGroup["width"] * $frameGroup["height"]);
			$spritesPerDirection = ($spritesPerLayer     * $frameGroup["layers"]);
			$spritesPerAddon     = ($spritesPerDirection * $frameGroup["divx"]);
			$spritesPerMount     = ($spritesPerAddon     * $frameGroup["divy"]);
			$spritesPerStep      = ($spritesPerMount     * $frameGroup["divz"]);


			// Calculate position in frame set
			$index  = ($spritesPerLayer     * $layer);
			$index += ($spritesPerDirection * $direction);
			$index += ($spritesPerMount     * $mounted);
			$index += ($spritesPerAddon     * $addon);
			$index += ($spritesPerStep      * $targetStep);


			// Merge sprites to flat image
			$frameSet = array_slice($frameGroup["spriteIds"], $index, $spritesPerLayer);

			$frame = SELF::createBlankImage($frameGroup["width"], $frameGroup["height"]);
			$sprite = new Sprite();

			for ($y = $frameGroup["height"]; $y > 0; $y--) {
				for ($x = $frameGroup["width"]; $x > 0; $x--) {

					$currentSprite = $sprite->getSprite( array_shift($frameSet) );
					SELF::copySpriteToPosition($frame, $currentSprite, $x, $y);
				}
			}

			return $frame;
		}


		/*
		 * return colorized outfit frame with (?)addons (if looktype hasn't mask layer, raw colors will remain)
		 */
		 private static function getColorizedOutfitFrameWithAddons($looktypeObject, $colors, $direction, $addons, $mounted = 0, /* $inMove,*/ $step) {
			//VERIFY HERE IF LOOKTYPE HAS MOUNT
			if ($mounted && !SELF::looktypeHasMount($looktypeObject)) {
				$mounted = 0;
			}

 			$targetImage = SELF::generateFrameLayer($looktypeObject, 0, $direction, ADDON_NONE, $mounted, false, $step);

 			$hasColorLayer = SELF::looktypeHasLayers($looktypeObject);
 			if ($hasColorLayer) {
 				$colorMask = SELF::generateFrameLayer($looktypeObject, 1, $direction, ADDON_NONE, $mounted, false, $step);
 			}

 			if ($addons && SELF::looktypeHasAddons($looktypeObject)) {
 				if ($addons === 1 || $addons === 3) {
 					$addonImage = SELF::generateFrameLayer($looktypeObject, 0, $direction, ADDON_FIRST, $mounted, false, $step);
 					self::copyImage($targetImage, $addonImage, true);

 					if ($hasColorLayer) {
 						$addonImageColorMask = SELF::generateFrameLayer($looktypeObject, 1, $direction, ADDON_FIRST, $mounted, false, $step);
 						self::copyImage($colorMask, $addonImageColorMask, true);
 					}
 				}

 				if ($addons === 2 || $addons === 3) {
 					$addonImage = SELF::generateFrameLayer($looktypeObject, 0, $direction, ADDON_SECOND, $mounted, false, $step);
 					self::copyImage($targetImage, $addonImage, true);


 					if ($hasColorLayer) {
 						$addonImageColorMask = SELF::generateFrameLayer($looktypeObject, 1, $direction, ADDON_SECOND, $mounted, false, $step);
 						self::copyImage($colorMask, $addonImageColorMask, true);
 					}
 				}
 			}

 			if ($hasColorLayer) {
 				SELF::colorize($targetImage, $colorMask, $colors);
 			}

 			return $targetImage;
 		}





		public function __construct($looktypeId) {
			$this->setLooktype($looktypeId);
		}

		private function setLooktype($looktypeId) {
			$this->objectInstance = new Object();
			$this->objectInstance->getLooktype($looktypeId);
			return $this;
		}

		public function setColors($head, $body, $legs, $feet) {
			$this->looktypeColors = [$head, $body, $legs, $feet];
			return $this;
		}

		public function setAddons($addons) {
			$this->looktypeAddons = $addons;
			return $this;
		}

		public function setMount($mountId) {
			$this->looktypeMounted = 1;

			if ($mountId !== true) {
				$this->mountObjectInstance = new Looktype($mountId);
				// $this->mountObjectInstance->setLooktype($mountId);
				return $this->mountObjectInstance;
			}
		}

		public function setStep($step) {
			$this->looktypeStep = $step;
			return $this;
		}

		public function setDirection($direction) {
			$this->looktypeDirection = $direction;
			return $this;
		}













		public function getFrameMap() {
			$frameMap = imagecreatetruecolor(512, 256);

			for ($direction = 0; $direction <= 3; $direction++) {
				// $dirImage = $this->generateFrameLayer(0, $direction, 0, 0, false, 0);
				// self::copyImageToPosition($frameMap, $dirImage, 0, $direction*64);

				for ($step = 0; $step <= 7; $step++) {
					$image = SELF::getColorizedOutfitFrameWithAddons($this->objectInstance->object, $this->looktypeColors, $direction, $this->looktypeAddons, 0, ($step+1));
					self::copyImageToPosition($frameMap, $image, $step*64, $direction*64);
				}
			}

			return $frameMap;
		}

		public function getFrame() {
			$targetImage = SELF::getColorizedOutfitFrameWithAddons(
				$this->objectInstance->object, $this->looktypeColors, $this->looktypeDirection, $this->looktypeAddons, $this->looktypeMounted/*mounted*/, /* $inMove,*/ $this->looktypeStep
			);

			return $targetImage;
		}

	}






	//print "<br> res: ".$spritesPerResolution;
	// print "<br> lay: ".$spritesPerLayer;
	// print "<br> dir: ".$spritesPerDirection;
	// print "<br> add: ".$spritesPerAddon;
	// print "<br> mou: ".$spritesPerMount;
	// print "<br> ste: ".$spritesPerStep;
	// print "<br>";
	// print "<br> index: ".$index;
	// print "<br><br>";
