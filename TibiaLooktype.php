<?php
	namespace TibiaReader;

	class Looktype {

		private $objectInstance;

		private $looktypeColors = [];
		private $looktypeAddons = 0;

		private $mountInstance = null;



		private function generateLayer() {
			
		}

		private function generateFrame() {
			
		}



		public function setLooktype($looktypeId) {
			$this->objectInstance = new Object();
			$this->objectInstance->getLooktype($looktypeId);
		}

		public function setColors($head, $body, $legs, $feet) {
			$this->looktypeColors = [$head, $body, $legs, $feet];
		}

		public function setAddons($addons) {
			$this->looktypeAddons = $addons;
		}

		public function setMount($mountId) {
			$this->mountInstance = new Looktype();
			$this->mountInstance->setLooktype($mountId);
			return $this->mountInstance;
		}


		public function getFrame($lookDirection, $animationStep) {
			$source = $this->generateFrame($lookDirection, $animationStep);

            // if ($this->looktypeObject->objectInfo["layers"] > 1)
                // $this->colorize($source[0], $source[1], $this->looktypeColors);
		}

	}