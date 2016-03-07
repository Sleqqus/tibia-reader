<?php
	namespace TibiaReader;

	define("COLOR_PINK",   16711935);
    define("COLOR_RED",    16776960);
    define("COLOR_YELLOW", 16711680);
    define("COLOR_GREEN",  65280);
    define("COLOR_BLUE",   255);

	class Looktype {

		private $objectInstance;
		private $mountObjectInstance = null;

		private $looktypeColors = [];
		private $looktypeAddons = 0;

		private $looktypeDirection = 0;
		private $looktypeMove = false;
		private $looktypeStep = 0;



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


		public function __construct($looktypeId) {
			$this->setLooktype($looktypeId);
			// return null;
		}

		private function generateLayer() {

		}

		private function hasLayers() {
			$frameGroups = isset($this->objectInstance->object["frameGroups"]);
			if ($frameGroups) {
				$frameGroup = $this->objectInstance->object["frameGroups"][0];
			} else {
				$frameGroup = $this->objectInstance->object;
			}

			return ($frameGroup["layers"] > 1)? true : false;
		}


		private function generateFrame($layer = 0, $direction = 0, $addon = 0, $mount = 0, $inMove = 0, $step = 0) {
		// private function generateFrame($layer = 0, $direction = 2, $addon = 0, $mount = 0, $inMove = false, $step = 1) {
			$looktype = $this->objectInstance->object;

			$frameGroups = isset($this->objectInstance->object["frameGroups"]);

			if ($frameGroups) {
				$multipleFrameGroups = count($this->objectInstance->object["frameGroups"]) > 1;

				if ($multipleFrameGroups) {
					$targetFrameGroup = ($step === 0) ? 0 : 1;
				} else {
					$targetFrameGroup = 0;
				}

				$targetStep = ($step === 0) ? 0 : ($step - 1);
				$frameGroup = $looktype["frameGroups"][$targetFrameGroup];
			} else {
				$targetStep = $step;
				$frameGroup = $looktype;
			}

			//METADATA
			$frameWidth = $frameGroup["width"];
			$frameHeight = $frameGroup["width"];


			//calc positions in frames map
			$spritesPerLayer     = ($frameGroup["width"] * $frameGroup["height"]);
			$spritesPerDirection = ($spritesPerLayer     * $frameGroup["layers"]);
			$spritesPerAddon     = ($spritesPerDirection * $frameGroup["divx"]);
			$spritesPerMount     = ($spritesPerAddon     * $frameGroup["divy"]);
			$spritesPerStep      = ($spritesPerMount     * $frameGroup["divz"]);

			//aim position
			$index  = ($spritesPerLayer     * $layer);
			$index += ($spritesPerDirection * $direction);
			$index += ($spritesPerMount     * $mount);
			$index += ($spritesPerAddon     * $addon);
			$index += ($spritesPerStep      * $targetStep);


			//print "<br> res: ".$spritesPerResolution;
			// print "<br> lay: ".$spritesPerLayer;
			// print "<br> dir: ".$spritesPerDirection;
			// print "<br> add: ".$spritesPerAddon;
			// print "<br> mou: ".$spritesPerMount;
			// print "<br> ste: ".$spritesPerStep;
			// print "<br>";
			// print "<br> index: ".$index;
			// print "<br><br>";

			$targetFrameSet = array_slice($frameGroup["spriteIds"], $index, $spritesPerLayer);



			$frame = imagecreatetruecolor($frameWidth*32, $frameHeight*32);
			imagefill($frame, 0, 0, imagecolorallocate($frame, 255, 0, 255)); // <-----------



			$sprite = new Sprite();

			// $frameIndex = 0;
			// for ($y = $frameHeight; $y > 0; $y--) {
			// 	for ($x = $frameWidth; $x > 0; $x--) {
			// 		$targetX = ($x-1)*32;
			// 		$targetY = ($y-1)*32;
			//
			// 		$currentSprite = $sprite->getSprite($targetFrameSet[$frameIndex]);
			// 		imagecopymerge($frame, $currentSprite, $targetX, $targetY, 0, 0, 32, 32, 100);
			//
			// 		$frameIndex++;
			// 	}
			// }

			foreach ($targetFrameSet as $key => $spriteId) {
            	$destinationX = ($frameWidth - (int)($key%$frameWidth)-1 ) *32;
            	$destinationY = ($frameHeight - (int)($key/$frameHeight)-1 ) *32;

				$currentSprite = $sprite->getSprite($targetFrameSet[$key]);

            	imagecopymerge($frame, $currentSprite, $destinationX, $destinationY, 0, 0, 32, 32, 100);
            }




			return $frame;

			// foreach ($targetFrameSet as $value) {
				// print '<img src="sprite.php?id='.$value.'">';
			// }
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
			$this->mountObjectInstance = new Looktype($mountId);
			// $this->mountObjectInstance->setLooktype($mountId);
			return $this->mountObjectInstance;
		}

		public function setStep($step) {
			$this->looktypeStep = $step;
			return $this;
		}

		public function setDirection($direction) {
			$this->looktypeDirection = $direction;
			return $this;
		}





		protected function getColorByPart($maskColor, $bodypartColors) {
			switch($maskColor) {
				case COLOR_RED:    return $bodypartColors[0];
				case COLOR_YELLOW: return $bodypartColors[1];
				case COLOR_GREEN:  return $bodypartColors[2];
				case COLOR_BLUE:   return $bodypartColors[3];
				default:           return false;
			}
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




		public function getFrame() {
			//  $layer, $direction, $addon, $mount, $inMove, $step


			$targetImage = $this->generateFrame(0, $this->looktypeDirection, 0, 0, false, $this->looktypeStep);

            if ($this->hasLayers()) {
				$colorMask = $this->generateFrame(1, $this->looktypeDirection, 0, 0, false, $this->looktypeStep);
                SELF::colorize($targetImage, $colorMask, $this->looktypeColors);
			}

			

			return $targetImage;
		}

	}
