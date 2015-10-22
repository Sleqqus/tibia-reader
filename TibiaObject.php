<?php
	namespace TibiaReader;

	class Object {
		private $fileHandle;
		private $fileMetadata = [];
		private $objectData = [];

		private $_spriteIdSize = 2;
		private $_animationPhases = false;
		private $_frameGroups = false;

		public $fileSignature;
		public $fileItems;
		public $fileLooktypes;
		public $fileEffects;
		public $fileProjectiles;

		public $filePhysicalItems;


		public function __construct() {
			if (CONFIG::$dataFilePath === null) {
				throw new \Exception("Data file not specified! Please set data file path first.");
			}

			$this->fileHandle = new FileReader(CONFIG::$dataFilePath);

			$this->getFileSignature();

			if (!Flags::isSupported($this->fileSignature)) {
				throw new \Exception("Specified data file is not supported!");
			}

			$this->getFileMetadata();

			$this->determineSpriteIdSize();
			$this->determineAnimationPhases();
			$this->determineFrameGroups();
		}


		//Determine how many bytes is used for sprite id
		//Tibia version 9.60 and newer uses 4-bytes long sprite ids instead of 2
		private function determineSpriteIdSize() {
			if (($this->fileSignature >= 1341813964) || ($this->fileSignature < 1000000000)) {
				$this->_spriteIdSize = 4;
			}
		}

		//Determine if file uses animation phases (version 10.50 or newer)
		private function determineAnimationPhases() {
			if (($this->fileSignature >= 1404454414) || ($this->fileSignature < 1000000000)) {
				$this->_animationPhases = true;
			}
		}

		//Determine if file uses frame groups (version 10.57 or newer)
		private function determineFrameGroups() {
			if (($this->fileSignature >= 1411724793) || ($this->fileSignature < 1000000000)) {
				$this->_frameGroups = true;
			}
		}


		private function getFileSignature() {
			$this->fileHandle->jump(0);
			$this->fileSignature = $this->fileHandle->readInt32();
		}
 
		private function getFileMetadata() {
			$this->fileHandle->jump(4);
			$this->fileItems         = $this->fileHandle->readInt16();
			$this->fileLooktypes     = $this->fileHandle->readInt16();
			$this->fileEffects       = $this->fileHandle->readInt16();
			$this->fileProjectiles   = $this->fileHandle->readInt16();

			$this->filePhysicalItems = ($this->fileItems - 100);
		}


		private function readObjectFlags() {
			$flagId = $this->fileHandle->readByte();

			$objectFlags = [];

			while ($flagId !== 255) {
				$flag = Flags::getFlag($this->fileSignature, $flagId);

				$objectFlags[$flagId] = [$flag[0]];

				$flagParameters = $flag[1];
				$flagParametersCount = count($flagParameters);

				if ($flagParametersCount > 0) {
					$flagParams = [];
					for ($parameter = 0; $parameter < $flagParametersCount; $parameter++) {
						$parameterValue = $this->fileHandle->readBytes($flagParameters[$parameter][1]);

						if (isset($flagParameters[$parameter][2])) {
							$flagParams[$flagParameters[$parameter][0]] = $this->fileHandle->readText($parameterValue);
						} else {
							$flagParams[$flagParameters[$parameter][0]] = $parameterValue;
						}
					}
					$objectFlags[$flagId][1] = $flagParams;
				}


				$flagId = $this->fileHandle->readByte();
			}

			return $objectFlags;
		}

		private function readObjectProperties() {
			$group = [];

			$group["width"]          = $this->fileHandle->readByte();
			$group["height"]         = $this->fileHandle->readByte();

			$group["_sprites4frame"] = ($group["width"] * $group["height"]);

			if ($group["_sprites4frame"] > 1) {
				$group["cropsize"]   = $this->fileHandle->readByte();
			}
			$group["layers"]         = $this->fileHandle->readByte();
			$group["divx"]           = $this->fileHandle->readByte();
			$group["divy"]           = $this->fileHandle->readByte();
			$group["divz"]           = $this->fileHandle->readByte();
			$group["animations"]     = $this->fileHandle->readByte();


			if (($group["animations"] > 1) && ($this->_animationPhases)) {
				$group["_isAnimation"]         = true;
				$group["animation_type"]       = $this->fileHandle->readByte();
				$group["animation_loopCount"]  = $this->fileHandle->readInt32();
				$group["animation_startPhase"] = $this->fileHandle->readByte();
				$group["animation_durations"]  = [];	

				for ($phase = 0; $phase < $group["animations"]; $phase++) {
					$group["animation_durations"][$phase] = [
						"min" => $this->fileHandle->readInt32(),
						"max" => $this->fileHandle->readInt32()
					];
				}
			}

			$group["sprites"] = $group["width"] * $group["height"] * $group["layers"] * $group["divx"] * $group["divy"] * $group["divz"] * $group["animations"];

			$group["spriteIds"] = [];
			for ($sprite = 0; $sprite < $group["sprites"]; $sprite++) { 
				$group["spriteIds"][] = $this->fileHandle->readBytes($this->_spriteIdSize);
			}

			return $group;		
		}

		private function readObject($objectIndex) {

			if ($objectIndex <= $this->filePhysicalItems) {
				$this->objectData["type"] = "item";
			} elseif ($objectIndex <= ($this->filePhysicalItems+$this->fileLooktypes)) {
				$this->objectData["type"] = "looktype";
			} elseif ($objectIndex <= ($this->filePhysicalItems+$this->fileLooktypes+$this->fileEffects)) {
				$this->objectData["type"] = "effect";
			} else {
				$this->objectData["type"] = "projectile";
			}

			$this->objectData["flags"] = $this->readObjectFlags();

			//Frame groups were implemented in version 10.57
			if ($this->_frameGroups) {
				if ($this->objectData["type"] === "looktype") {
					$this->objectData["frameGroupsCount"] = $this->fileHandle->readByte();
				} else {
					$this->objectData["frameGroupsCount"] = 1;
				}

				$this->objectData["frameGroups"] = [];

				for ($frameGroup = 0; $frameGroup < $this->objectData["frameGroupsCount"]; $frameGroup++) {
					if ($this->objectData["type"] === "looktype") {
						$frameGroupIndex = $this->fileHandle->readByte();
					}

					$this->objectData["frameGroups"][] = $this->readObjectProperties();
				}

			} else {

				$objectProperties = $this->readObjectProperties();
				$this->objectData = array_merge($this->objectData, $objectProperties);

			}

		}

		public function mapFile($itemId) {
			br(); print "mapping file";
			br(); print_r($this->fileMetadata);
			br();

			$objectsTotal = ($this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects + $this->fileProjectiles);
			// $objectsTotal = 1000;
			br(); print "total: ".$objectsTotal;

			
			$this->fileHandle->jump(12);

			
			for ($objectIndex = 0; $objectIndex <= $objectsTotal; $objectIndex++) {
				//Reset array (necessary if iterating through more than 1 object)
				$this->objectData = [];
				
				$objectData = $this->readObject($objectIndex);
				
				$this->printObjectInformation($this->objectData, $objectIndex);
			}
		}

		private function isFileMapped() {
			return false;
		}

		public function loadItem($itemId) {
			if (!$this->isFileMapped()) {
				$this->mapFile($itemId);
			}
		}

		public function loadLooktype($looktypeId) {

		}

		public function getInformation() {

		}

		public function printObjectInformation($object, $objectIndex) {
				br(); print "--------------------------------------------------------------------------";
				br(); print "object: ".$objectIndex;

				br(); print_r($object);
		}
	}