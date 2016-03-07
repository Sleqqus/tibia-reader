<?php
	namespace TibiaReader;

	class Object {
		private $fileHandle;
		private $cachedFileMap;
		private $fileMetadata = [];
		private $fileHash;

		public $object = [];
		public $objectId = null;
		private $objectIndexInFile = null;
		private $objectAddress = null;

		private $_spriteIdSize = 2;
		private $_animationPhases = false;
		private $_frameGroups = false;

		public $fileSignature;
		public $fileItems;
		public $fileLooktypes;
		public $fileEffects;
		public $fileProjectiles;

		public $filePhysicalItems;
		public $objectsTotal;


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

			if (CONFIG::$dataFileMapping) {
				//Create and save file map if caching is set to true and file isn't already mapped
				if (!$this->isFileMapped()) {
					$this->mapFile();
				}

				$this->cachedFileMap = file(CONFIG::$dataFileCacheDir . $this->fileHash);
			}
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
			$this->objectsTotal      = ($this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects + $this->fileProjectiles);

			$this->fileHash = md5_file(CONFIG::$dataFilePath);
		}

		private function getObjectTypeByIndex($objectId) {
			if ($objectId <= $this->filePhysicalItems) {
				return "item";
			} elseif ($objectId <= ($this->filePhysicalItems + $this->fileLooktypes)) {
				return "looktype";
			} elseif ($objectId <= ($this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects)) {
				return "effect";
			} else {
				return "projectile";
			}
		}



		/* ---------------------------------------------------------------------------------------------------- */
		/* ----- OBJECT READING BEGIN                                                                           */
		/* ---------------------------------------------------------------------------------------------------- */

		private function readObjectFlags() {
			$objectFlags = [];

			$flagId = $this->fileHandle->readByte();
			while ($flagId !== 255) {
				$flagDefinition = Flags::getFlag($this->fileSignature, $flagId);

				$objectFlags[$flagId] = [$flagDefinition[0]];

				$flagParameters = $flagDefinition[1];
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

			if (($group["width"] * $group["height"]) > 1) {
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
			$object = [];

			$object["flags"] = $this->readObjectFlags();
			$object["type"] = $this->getObjectTypeByIndex($objectIndex);

			if ($this->_frameGroups) {
				if ($object["type"] === "looktype") {
					$object["frameGroupsCount"] = $this->fileHandle->readByte();
				} else {
					$object["frameGroupsCount"] = 1;
				}

				$object["frameGroups"] = [];

				for ($frameGroup = 0; $frameGroup < $object["frameGroupsCount"]; $frameGroup++) {
					if ($object["type"] === "looktype") {
						$frameGroupIndex = $this->fileHandle->readByte();
					}

					$object["frameGroups"][] = $this->readObjectProperties();
				}
			} else {
				$objectProperties = $this->readObjectProperties();
				$object = array_merge($object, $objectProperties);
			}

			return $object;
		}

		/* ---------------------------------------------------------------------------------------------------- */
		/* ----- OBJECT READING END                                                                             */
		/* ---------------------------------------------------------------------------------------------------- */



		public function mapFile() {
			$cacheFile = fopen(CONFIG::$dataFileCacheDir . $this->fileHash, "w");

			$this->fileHandle->jump(12);

			for ($objectIndex = 0; $objectIndex <= $this->objectsTotal; $objectIndex++) {
				$obejctPosition = $this->fileHandle->getPos();
				fwrite($cacheFile, ($obejctPosition."\n"));
				$this->readObject($objectIndex);
			}

			fclose($cacheFile);
		}

		public function getObjectAddress($objectId) {
			if (CONFIG::$dataFileMapping) {
				return (int)$this->cachedFileMap[$objectId];
			} else {
				$this->fileHandle->jump(12);

				for ($objectIndex = 0; $objectIndex < $objectId; $objectIndex++) {
					$this->readObject($objectIndex);
				}

				return $this->fileHandle->getPos();
			}

		}

		private function isFileMapped() {
			if (!file_exists(CONFIG::$dataFileCacheDir . $this->fileHash)) {
				return false;
			} else {
				return true;
			}
		}

		private function getObject($objectIndex) {
			$this->objectIndexInFile = $objectIndex;

			$this->objectAddress = $this->getObjectAddress($objectIndex);
			$this->fileHandle->jump($this->objectAddress);

			$this->object = $this->readObject($objectIndex);
		}

		private function getObjectIndex($id, $type) {
			switch($type) {
				case 0: $index = ($id-100); break;
				case 1: $index = ($this->filePhysicalItems + $id); break;
				case 2: $index = ($this->filePhysicalItems + $this->fileLooktypes + $id); break;
				case 3: $index = ($this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects + $id); break;
			}

			return $index;
		}

		public function getItem($itemId) {
			if (($itemId < 100) || ($itemId > $this->fileItems)) {
				throw new \Exception("Object ID out of file boundaries!");
			}

			$this->objectId = $itemId;

			$objectId = $this->getObjectIndex($itemId, 0);
			$this->getObject($objectId);
		}

		public function getLooktype($looktypeId) {
			if (($looktypeId < 1) || ($looktypeId > $this->fileLooktypes)) {
				throw new \Exception("Object ID out of file boundaries!");
			}

			$this->objectId = $looktypeId;

			$objectId = $this->getObjectIndex($looktypeId, 1);
			$this->getObject($objectId);
		}

		public function getEffect($effectId) {
			if (($effectId < 1) || ($effectId > $this->fileEffects)) {
				throw new \Exception("Object ID out of file boundaries!");
			}

			$this->objectId = $effectId;

			$objectId = $this->getObjectIndex($effectId, 2);
			$this->getObject($objectId);
		}

		public function getProjectile($projectileId) {
			if (($projectileId < 1) || ($projectileId > $this->fileProjectiles)) {
				throw new \Exception("Object ID out of file boundaries!");
			}

			$this->objectId = $projectileId;

			$objectId = $this->getObjectIndex($projectileId, 3);
			$this->getObject($objectId);
		}


		public function printObjectInformation() {
			print "Object ID: ".$this->objectId." (file index: ".$this->objectIndexInFile.", starting at byte: ".$this->objectAddress.")";
			print "<pre>";
			print_r($this->object);
			print "</pre>";
		}

		public function printFileInformation() {
			print "Data file name: " . basename(CONFIG::$dataFilePath) . "<br>";
			print "File Signature: " . $this->fileSignature . "<br>";
			print "Items: " . $this->fileItems . "<br>";
			print "Looktypes: " . $this->fileLooktypes . "<br>";
			print "Effects: " . $this->fileEffects . "<br>";
			print "Projectiles: " . $this->fileProjectiles . "<br>";
		}
	}
