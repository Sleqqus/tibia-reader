<?php
	namespace TibiaReader;

	class Sprite {
		private $fileHandle;
		private $_spritesCountSize = 2;

		public $spritesCount;

		public function __construct() {
			if (CONFIG::$spriteFilePath === null) {
				throw new \Exception("Sprite file not specified! Please set data file path first.");
			}

			$this->fileHandle = new FileReader(CONFIG::$spriteFilePath);

			$this->determineFileVersion();
			$this->getFileMetadata();
		}


		private function getFileMetadata() {
			$this->fileHandle->jump(0);
			$this->fileSignature = $this->fileHandle->readInt32();
			$this->spritesCount = $this->fileHandle->readBytes($this->_spritesCountSize);
		}

		// Check if file signature points at Tibia version 9.60 or later
		// which contains 32-bytes long sprites count
		private function determineFileVersion() {
			$versionSignature = $this->fileHandle->jump(0)->readInt32();

			if ($versionSignature >= 1341814009) {
				$this->_spritesCountSize = 4;
			}
		}
		
		//Returns start & end position of sprite content in file
		private function getSpriteAddress($spriteId) {
			//Skip file signature and sprites count
			$firstSpriteAddressAt = (4 + $this->_spritesCountSize);

			$spriteAddressAt = $firstSpriteAddressAt + (($spriteId - 1) * 4);

			//If sprite has no address in file assigned, jump to first, blank sprite
			if ($this->fileHandle->jump($spriteAddressAt)->readInt32() === 0)
				$spriteAddressAt = $firstSpriteAddressAt;

			$spriteBegin = $this->fileHandle->jump($spriteAddressAt)->readInt32();

			//Next sprite address is also end of previous sprite
			//If next sprite has no address, jump to next until found
			$spriteEnd = $this->fileHandle->jump($spriteAddressAt+4)->readInt32();
			while ($spriteEnd === 0)
				$spriteEnd = $this->fileHandle->readInt32();

			return [$spriteBegin, $spriteEnd];
		}

		public function getSprite($spriteId, $alphaChannel = false) {
			//Check if sprite ID isn't bigger than sprites count
			if ($spriteId > $this->spritesCount) {
				throw new \Exception("Specified sprite ID (".$spriteId.") is bigger than sprites count (".$this->spritesCount.")!");
			}

			$spriteAdress = $this->getSpriteAddress($spriteId);
			
			//Jump to first pixel information (not sure why actually there is +5, because I wrote first version few years ago)
			$this->fileHandle->jump($spriteAdress[0] + 5);

			//Initialize empty image and fill it with default transparency color (pink)
			$image = imagecreatetruecolor(32, 32);
			imagefill($image, 0, 0, ((255 << 16) + (0 << 8) + (255)));

			$pixel = 1;
			while ($this->fileHandle->getPos() < $spriteAdress[1]) {
				$pixel += $this->fileHandle->readInt16();
				$coloredPixelsEnd = ( $this->fileHandle->readInt16() + $pixel );
				while ($pixel < $coloredPixelsEnd) {
					$color = ($this->fileHandle->readByte() << 16) + ($this->fileHandle->readByte() << 8) + ($this->fileHandle->readByte());
					imagesetpixel($image, ($pixel-1)%32, floor(($pixel-1)/32), $color);
					$pixel++;
				}
			}

			if ($alphaChannel) {
				imagecolortransparent($image, ((255 << 16) + (0 << 8) + (255)));
			}

			return $image;
		}

		public function printSprite($spriteId, $alphaChannel = false) {
			$image = $this->get($spriteId, $alphaChannel);

			header("Content-type: image/png");
			Imagepng($image);
		}
	}