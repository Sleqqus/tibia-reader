<?php

declare(strict_types=1);

namespace TibiaReader;

class DatObject
{
	public const TYPE_ITEM = 0;
	public const TYPE_LOOKTYPE = 1;
	public const TYPE_EFFECT = 2;
	public const TYPE_PROJECTILE = 3;

	// Signatures up to 10.70 are client build timestamps; 10.72+ uses short revision
	// numbers, which the SIGNATURE_SHORT_REVISION_MAX comparison catches
	private const SIGNATURE_SPRITE_ID_32BIT = 1341813964;   // 9.60
	private const SIGNATURE_ANIMATION_PHASES = 1404454414;  // 10.50
	private const SIGNATURE_FRAME_GROUPS = 1411724793;      // 10.57
	private const SIGNATURE_SHORT_REVISION_MAX = 1000000000;

	private FileReader $fileHandle;
	private array $cachedFileMap = [];

	public array $object = [];
	public ?int $objectId = null;
	private ?int $objectIndexInFile = null;
	private ?int $objectAddress = null;

	private int $spriteIdSize = 2;
	private bool $animationPhases = false;
	private bool $frameGroups = false;

	public int $fileSignature;
	public int $fileItems;
	public int $fileLooktypes;
	public int $fileEffects;
	public int $fileProjectiles;

	public int $filePhysicalItems;
	public int $objectsTotal;

	public function __construct()
	{
		if (Config::$dataFilePath === null) {
			throw new \RuntimeException('Data file not specified! Please set data file path first.');
		}

		$this->fileHandle = new FileReader(Config::$dataFilePath);

		$this->readFileSignature();

		if (!Flags::isSupported($this->fileSignature)) {
			throw new \RuntimeException('Specified data file is not supported!');
		}

		$this->readFileMetadata();

		$this->determineSpriteIdSize();
		$this->determineAnimationPhases();
		$this->determineFrameGroups();

		if (Config::$dataFileMapping) {
			$this->cachedFileMap = $this->isFileMapped()
				? file($this->cacheFilePath(), FILE_IGNORE_NEW_LINES)
				: $this->mapFile();
		}
	}

	// Tibia version 9.60 and newer uses 4-bytes long sprite ids instead of 2
	private function determineSpriteIdSize(): void
	{
		if ($this->fileSignature >= self::SIGNATURE_SPRITE_ID_32BIT || $this->fileSignature < self::SIGNATURE_SHORT_REVISION_MAX) {
			$this->spriteIdSize = 4;
		}
	}

	// Animation phases were introduced in version 10.50
	private function determineAnimationPhases(): void
	{
		if ($this->fileSignature >= self::SIGNATURE_ANIMATION_PHASES || $this->fileSignature < self::SIGNATURE_SHORT_REVISION_MAX) {
			$this->animationPhases = true;
		}
	}

	// Frame groups were introduced in version 10.57
	private function determineFrameGroups(): void
	{
		if ($this->fileSignature >= self::SIGNATURE_FRAME_GROUPS || $this->fileSignature < self::SIGNATURE_SHORT_REVISION_MAX) {
			$this->frameGroups = true;
		}
	}

	private function readFileSignature(): void
	{
		$this->fileHandle->jump(0);
		$this->fileSignature = $this->fileHandle->readInt32();
	}

	private function readFileMetadata(): void
	{
		$this->fileHandle->jump(4);
		$this->fileItems       = $this->fileHandle->readInt16();
		$this->fileLooktypes   = $this->fileHandle->readInt16();
		$this->fileEffects     = $this->fileHandle->readInt16();
		$this->fileProjectiles = $this->fileHandle->readInt16();

		// Item ids start at 100
		$this->filePhysicalItems = $this->fileItems - 100;
		$this->objectsTotal      = $this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects + $this->fileProjectiles;
	}

	private function getObjectTypeByIndex(int $objectIndex): string
	{
		if ($objectIndex <= $this->filePhysicalItems) {
			return 'item';
		}
		if ($objectIndex <= $this->filePhysicalItems + $this->fileLooktypes) {
			return 'looktype';
		}
		if ($objectIndex <= $this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects) {
			return 'effect';
		}

		return 'projectile';
	}

	/* ---------------------------------------------------------------------------------------------------- */
	/* ----- OBJECT READING                                                                                  */
	/* ---------------------------------------------------------------------------------------------------- */

	private function readObjectFlags(): array
	{
		$objectFlags = [];

		$flagId = $this->fileHandle->readByte();
		while ($flagId !== 255) {
			$flagDefinition = Flags::getFlag($this->fileSignature, $flagId);

			$objectFlags[$flagId] = [$flagDefinition[0]];

			$flagParameters = $flagDefinition[1];

			if (count($flagParameters) > 0) {
				$flagParams = [];
				foreach ($flagParameters as $parameter) {
					$parameterValue = $this->fileHandle->readBytes($parameter[1]);

					// Parameters with a third element are length-prefixed text
					if (isset($parameter[2])) {
						$flagParams[$parameter[0]] = $this->fileHandle->readText($parameterValue);
					} else {
						$flagParams[$parameter[0]] = $parameterValue;
					}
				}
				$objectFlags[$flagId][1] = $flagParams;
			}

			$flagId = $this->fileHandle->readByte();
		}

		return $objectFlags;
	}

	private function readObjectProperties(): array
	{
		$group = [];

		$group['width']  = $this->fileHandle->readByte();
		$group['height'] = $this->fileHandle->readByte();

		if (($group['width'] * $group['height']) > 1) {
			$group['cropsize'] = $this->fileHandle->readByte();
		}

		$group['layers']     = $this->fileHandle->readByte();
		$group['divx']       = $this->fileHandle->readByte();
		$group['divy']       = $this->fileHandle->readByte();
		$group['divz']       = $this->fileHandle->readByte();
		$group['animations'] = $this->fileHandle->readByte();

		if ($group['animations'] > 1 && $this->animationPhases) {
			$group['_isAnimation']         = true;
			$group['animation_type']       = $this->fileHandle->readByte();
			$group['animation_loopCount']  = $this->fileHandle->readInt32();
			$group['animation_startPhase'] = $this->fileHandle->readByte();
			$group['animation_durations']  = [];

			for ($phase = 0; $phase < $group['animations']; $phase++) {
				$group['animation_durations'][$phase] = [
					'min' => $this->fileHandle->readInt32(),
					'max' => $this->fileHandle->readInt32(),
				];
			}
		}

		$group['sprites'] = $group['width'] * $group['height'] * $group['layers'] * $group['divx'] * $group['divy'] * $group['divz'] * $group['animations'];

		$group['spriteIds'] = [];
		for ($sprite = 0; $sprite < $group['sprites']; $sprite++) {
			$group['spriteIds'][] = $this->fileHandle->readBytes($this->spriteIdSize);
		}

		return $group;
	}

	private function readObject(int $objectIndex): array
	{
		$object = [];

		$object['flags'] = $this->readObjectFlags();
		$object['type'] = $this->getObjectTypeByIndex($objectIndex);

		if ($this->frameGroups) {
			if ($object['type'] === 'looktype') {
				$object['frameGroupsCount'] = $this->fileHandle->readByte();
			} else {
				$object['frameGroupsCount'] = 1;
			}

			$object['frameGroups'] = [];

			for ($frameGroup = 0; $frameGroup < $object['frameGroupsCount']; $frameGroup++) {
				$frameGroupIndex = $frameGroup;
				if ($object['type'] === 'looktype') {
					$frameGroupIndex = $this->fileHandle->readByte();
				}

				$object['frameGroups'][$frameGroupIndex] = $this->readObjectProperties();
			}
		} else {
			$object = array_merge($object, $this->readObjectProperties());
		}

		return $object;
	}

	/* ---------------------------------------------------------------------------------------------------- */
	/* ----- OBJECT LOOKUP                                                                                   */
	/* ---------------------------------------------------------------------------------------------------- */

	/**
	 * Builds the byte offset of every object and saves it to the cache.
	 *
	 * @return list<int>
	 */
	public function mapFile(): array
	{
		$fileMap = [];

		$this->fileHandle->jump(12);

		for ($objectIndex = 0; $objectIndex <= $this->objectsTotal; $objectIndex++) {
			$fileMap[] = $this->fileHandle->getPos();
			$this->readObject($objectIndex);
		}

		// Written to a temp file and renamed so concurrent requests never read a partial map
		$cachePath = $this->cacheFilePath();
		$tempPath = $cachePath . '.' . bin2hex(random_bytes(4)) . '.tmp';

		if (file_put_contents($tempPath, implode("\n", $fileMap)) === false || !rename($tempPath, $cachePath)) {
			@unlink($tempPath);
			throw new \RuntimeException('Unable to write data file map to cache directory.');
		}

		return $fileMap;
	}

	public function getObjectAddress(int $objectIndex): int
	{
		if (Config::$dataFileMapping) {
			return (int) $this->cachedFileMap[$objectIndex];
		}

		$this->fileHandle->jump(12);

		for ($index = 0; $index < $objectIndex; $index++) {
			$this->readObject($index);
		}

		return $this->fileHandle->getPos();
	}

	private function isFileMapped(): bool
	{
		return file_exists($this->cacheFilePath());
	}

	// Keyed on path, size and modification time so the data file isn't hashed on every request
	private function cacheFilePath(): string
	{
		$path = realpath(Config::$dataFilePath);
		$key = md5($path . '|' . filesize($path) . '|' . filemtime($path));

		return Config::dataCacheDir() . $key;
	}

	private function getObject(int $objectIndex): void
	{
		$this->objectIndexInFile = $objectIndex;

		$this->objectAddress = $this->getObjectAddress($objectIndex);
		$this->fileHandle->jump($this->objectAddress);

		$this->object = $this->readObject($objectIndex);
	}

	private function getObjectIndex(int $id, int $type): int
	{
		return match ($type) {
			self::TYPE_ITEM       => $id - 100,
			self::TYPE_LOOKTYPE   => $this->filePhysicalItems + $id,
			self::TYPE_EFFECT     => $this->filePhysicalItems + $this->fileLooktypes + $id,
			self::TYPE_PROJECTILE => $this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects + $id,
		};
	}

	public function getItem(int $itemId): void
	{
		if ($itemId < 100 || $itemId > $this->fileItems) {
			throw new \OutOfRangeException('Item ID out of file boundaries!');
		}

		$this->objectId = $itemId;
		$this->getObject($this->getObjectIndex($itemId, self::TYPE_ITEM));
	}

	public function getLooktype(int $looktypeId): void
	{
		if ($looktypeId < 1 || $looktypeId > $this->fileLooktypes) {
			throw new \OutOfRangeException('Looktype ID out of file boundaries!');
		}

		$this->objectId = $looktypeId;
		$this->getObject($this->getObjectIndex($looktypeId, self::TYPE_LOOKTYPE));
	}

	public function getEffect(int $effectId): void
	{
		if ($effectId < 1 || $effectId > $this->fileEffects) {
			throw new \OutOfRangeException('Effect ID out of file boundaries!');
		}

		$this->objectId = $effectId;
		$this->getObject($this->getObjectIndex($effectId, self::TYPE_EFFECT));
	}

	public function getProjectile(int $projectileId): void
	{
		if ($projectileId < 1 || $projectileId > $this->fileProjectiles) {
			throw new \OutOfRangeException('Projectile ID out of file boundaries!');
		}

		$this->objectId = $projectileId;
		$this->getObject($this->getObjectIndex($projectileId, self::TYPE_PROJECTILE));
	}

	public function printObjectInformation(): void
	{
		print 'Object ID: ' . $this->objectId . ' (file index: ' . $this->objectIndexInFile . ', starting at byte: ' . $this->objectAddress . ')';
		print '<pre>';
		print_r($this->object);
		print '</pre>';
	}

	public function printFileInformation(): void
	{
		print 'Data file name: ' . basename(Config::$dataFilePath) . '<br>';
		print 'File Signature: ' . $this->fileSignature . '<br>';
		print 'Items: ' . $this->fileItems . '<br>';
		print 'Looktypes: ' . $this->fileLooktypes . '<br>';
		print 'Effects: ' . $this->fileEffects . '<br>';
		print 'Projectiles: ' . $this->fileProjectiles . '<br>';
	}
}
