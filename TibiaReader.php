<?php
	namespace TibiaReader;

	class FileReader {
		private $fileHandle;

		public function openFile($file) {
			$this->fileHandle = fopen($file, 'r');
		}

		public function __construct($file) {
			if ($file) {
				$this->openFile($file);
			}
		}

		public function __destruct() {
			if ($this->fileHandle) {
				fclose($this->fileHandle);
			}
		}

		public function jump($newAddress) {
			fseek($this->fileHandle, $newAddress);
			return $this;
		}

		public function forward($bytes) {
			fseek($this->fileHandle, ( ftell($this->fileHandle) + $bytes ) );
			return $this;
		}

		public function backward($bytes) {
			fseek($this->fileHandle, ( ftell($this->fileHandle) - $bytes ) );
			return $this;
		}

		public function readByte() {
			$data = fread($this->fileHandle, 1);
			return ord($data);
		}

		public function readInt16() {
			$data = fread($this->fileHandle, 2);
			return unpack("S", $data)[1];
		}

		public function readInt32() {
			$data = fread($this->fileHandle, 4);
			return unpack("L", $data)[1];
		}

		public function getPos() {
			return ftell($this->fileHandle);
		}

		public function readBytes($bytes) {
			switch ($bytes) {
				case 2:  return $this->readInt16();
				case 4:  return $this->readInt32();
				default: return $this->readByte();
			}
		}

		public function readText($bytes) {
			$data = fread($this->fileHandle, $bytes);
			return $data;
		}
	}

	class CONFIG {
		public static $dataFilePath = null;
		public static $spriteFilePath = null;

		public static $dataFileMapping = false;
		public static $dataFileCacheDir = null;

		public static function setSpriteFilePath($path) {
			if (!is_file($path)) {
				throw new \Exception("Specified data file does not exist.");
			}

			self::$spriteFilePath = $path;
		}

		public static function setDataFilePath($path) {
			if (!is_file($path)) {
				throw new \Exception("Specified sprite file does not exist.");
			}

			self::$dataFilePath = $path;
		}

		public static function setDataFileMapping($mapping) {
			self::$dataFileMapping = $mapping;
		}
	}
	
	CONFIG::$dataFileCacheDir = dirname(__FILE__).DIRECTORY_SEPARATOR."cache".DIRECTORY_SEPARATOR;

	if (!file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR."cache")) {
		mkdir(dirname(__FILE__).DIRECTORY_SEPARATOR."cache");
	}

	require(dirname(__FILE__).DIRECTORY_SEPARATOR."TibiaSprite.php");
	require(dirname(__FILE__).DIRECTORY_SEPARATOR."TibiaObject.php");
	require(dirname(__FILE__).DIRECTORY_SEPARATOR."flags.php");
	require(dirname(__FILE__).DIRECTORY_SEPARATOR."TibiaLooktype.php");