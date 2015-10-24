<?php
	namespace TibiaReader;

	class Looktype {

		private $objectInstance;
		private $mountObjectInstance = null;

		private $looktypeColors = [];
		private $looktypeAddons = 0;

		private $looktypeDirection = 0;
		private $looktypeFrame = 0;



		public function __construct($looktypeId) {
			$this->setLooktype($looktypeId);
			// return null;
		}

		private function generateLayer() {
			
		}

		private function generateFrame($layer = 0, $direction = 0, $addon = 0, $mount = 0, $inMove = 0, $step = 0) {
			$looktype = $this->objectInstance->object;

			$frameGroups = isset($this->objectInstance->object["frameGroups"]);

			if ($frameGroups) {
				$targetFrameGroup = ($step === 0) ? 0 : 1;
				$targetStep = ($step === 0) ? 0 : ($step - 1);
				$frameGroup = $looktype["frameGroups"][$targetFrameGroup];
			} else {
				$targetStep = $step;
				$frameGroup = $looktype;
			}

			$spritesPerLayer     = ($frameGroup["width"] * $frameGroup["height"]);
			$spritesPerDirection = ($spritesPerLayer     * $frameGroup["layers"]);
			$spritesPerAddon     = ($spritesPerDirection * $frameGroup["divx"]);
			$spritesPerMount     = ($spritesPerAddon     * $frameGroup["divy"]);
			$spritesPerStep      = ($spritesPerMount     * $frameGroup["divz"]);

			$index  = ($spritesPerLayer     * $layer);
			$index += ($spritesPerDirection * $direction);
			$index += ($spritesPerMount     * $mount);
			$index += ($spritesPerAddon     * $addon);
			$index += ($spritesPerStep      * $targetStep);
			

			// print "<br> res: ".$spritesPerResolution;
			// print "<br> lay: ".$spritesPerLayer;
			// print "<br> dir: ".$spritesPerDirection;
			// print "<br> add: ".$spritesPerAddon;
			// print "<br> mou: ".$spritesPerMount;
			// print "<br> ste: ".$spritesPerStep;
			// print "<br>";
			// print "<br> index: ".$index;
			// print "<br><br>";
			
			$targetFrameSet = array_slice($frameGroup["spriteIds"], $index, $spritesPerLayer);

			foreach ($targetFrameSet as $value) {
				print '<img src="sprite.php?id='.$value.'">';
			}
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

		public function setFrame($frame) {
			$this->looktypeFrame = $frame;
			return $this;
		}

		public function setDirection($direction) {
			$this->looktypeDirection = $direction;
			return $this;
		}


		public function getFrame() {
			$source = $this->generateFrame(0, 2, 0, 0, false, 0);

            // if ($this->looktypeObject->objectInfo["layers"] > 1)
                // $this->colorize($source[0], $source[1], $this->looktypeColors);
		}

	}