<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * Reader for .dat files: object flags, sizes, animations and sprite ids.
 */
class DatObject
{
	public const TYPE_ITEM = 0;
	public const TYPE_LOOKTYPE = 1;
	public const TYPE_EFFECT = 2;
	public const TYPE_PROJECTILE = 3;

	// File signature (4 bytes) and the four object counts (2 bytes each)
	private const HEADER_SIZE = 12;

	private Client $client;
	private FileReader $fileHandle;
	private ClientVersion $version;
	private int $spriteIdSize;

	/** @var array<int, array{0: string, 1: array}> flag byte => [name, parameters] */
	private array $flagDefinitions = [];

	/**
	 * Byte offset of each object by its index in the file. Filled from the cache file when data
	 * caching is on, otherwise as far as the objects looked up so far.
	 *
	 * @var list<int>
	 */
	private array $offsets = [];

	/** @deprecated Use the ThingType returned by getItem() and the other getters. */
	public array $object = [];
	/** @deprecated Use the ThingType returned by getItem() and the other getters. */
	public ?int $objectId = null;
	private ?int $objectIndexInFile = null;
	private ?int $objectAddress = null;

	public int $fileSignature;
	public int $fileItems;
	public int $fileLooktypes;
	public int $fileEffects;
	public int $fileProjectiles;

	public int $filePhysicalItems;
	public int $objectsTotal;

	public function __construct(?Client $client = null)
	{
		$this->client = $client ?? Config::client();

		if ($this->client->dataFilePath === null) {
			throw new \RuntimeException('Data file not specified! Please set data file path first.');
		}

		$this->fileHandle = new FileReader($this->client->dataFilePath);

		$this->readFileSignature();
		$this->version = $this->client->version();
		$this->readFileMetadata();

		// Tibia 9.60 and newer uses 4-byte sprite ids instead of 2
		$this->spriteIdSize = $this->version->hasExtendedSprites() ? 4 : 2;

		foreach (Flags::flagNames($this->version) as $flagByte => $name) {
			$this->flagDefinitions[$flagByte] = Flags::getFlag($this->version, $flagByte);
		}

		if ($this->client->dataCaching) {
			$this->offsets = $this->isFileMapped() ? $this->readFileMap() : $this->mapFile();
		}
	}

	public function version(): ClientVersion
	{
		return $this->version;
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

	private function getCategoryByIndex(int $objectIndex): ThingCategory
	{
		if ($objectIndex <= $this->filePhysicalItems) {
			return ThingCategory::Item;
		}
		if ($objectIndex <= $this->filePhysicalItems + $this->fileLooktypes) {
			return ThingCategory::Looktype;
		}
		if ($objectIndex <= $this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects) {
			return ThingCategory::Effect;
		}

		return ThingCategory::Projectile;
	}

	/* ---------------------------------------------------------------------------------------------------- */
	/* ----- OBJECT READING                                                                                  */
	/* ---------------------------------------------------------------------------------------------------- */

	private function flagDefinition(int $flagByte): array
	{
		if (!isset($this->flagDefinitions[$flagByte])) {
			throw new \RuntimeException("Unknown flag {$flagByte} for client version {$this->version}.");
		}

		return $this->flagDefinitions[$flagByte];
	}

	/**
	 * @return array<string, array<string, int|string>>
	 */
	private function readObjectFlags(): array
	{
		$objectFlags = [];

		while (($flagByte = $this->fileHandle->readByte()) !== 255) {
			[$name, $parameters] = $this->flagDefinition($flagByte);

			$flagParams = [];
			foreach ($parameters as $parameter) {
				$parameterValue = $this->fileHandle->readBytes($parameter[1]);

				// Parameters with a third element are length-prefixed text
				$flagParams[$parameter[0]] = isset($parameter[2]) ? $this->fileHandle->readText($parameterValue) : $parameterValue;
			}

			$objectFlags[$name] = $flagParams;
		}

		return $objectFlags;
	}

	private function readFrameGroup(): FrameGroup
	{
		$width  = $this->fileHandle->readByte();
		$height = $this->fileHandle->readByte();
		$exactSize = ($width * $height > 1) ? $this->fileHandle->readByte() : null;

		[, $layers, $patternX, $patternY, $patternZ, $phases] = unpack('C5', $this->fileHandle->readText(5));

		$animation = null;
		if ($phases > 1 && $this->version->hasEnhancedAnimations()) {
			$type       = $this->fileHandle->readByte();
			$loopCount  = $this->fileHandle->readInt32();
			$startPhase = $this->fileHandle->readByte();

			$durations = [];
			foreach (array_chunk(unpack('V*', $this->fileHandle->readText($phases * 8)), 2) as [$min, $max]) {
				$durations[] = ['min' => $min, 'max' => $max];
			}

			$animation = new Animation($type, $loopCount, $startPhase, $durations);
		}

		$spriteCount = $width * $height * $layers * $patternX * $patternY * $patternZ * $phases;
		$spriteData = $this->fileHandle->readText($spriteCount * $this->spriteIdSize);
		$spriteIds = $spriteCount > 0 ? array_values(unpack($this->spriteIdSize === 4 ? 'V*' : 'v*', $spriteData)) : [];

		return new FrameGroup($width, $height, $exactSize, $layers, $patternX, $patternY, $patternZ, $phases, $animation, $spriteIds);
	}

	private function readObject(int $objectIndex): ThingType
	{
		$flags = $this->readObjectFlags();
		$category = $this->getCategoryByIndex($objectIndex);

		$frameGroups = [];
		if ($category === ThingCategory::Looktype && $this->version->hasFrameGroups()) {
			$frameGroupCount = $this->fileHandle->readByte();
			for ($i = 0; $i < $frameGroupCount; $i++) {
				$frameGroupType = $this->fileHandle->readByte();
				$frameGroups[$frameGroupType] = $this->readFrameGroup();
			}
		} else {
			$frameGroups[0] = $this->readFrameGroup();
		}

		return new ThingType($category, $this->getIdByIndex($objectIndex, $category), $flags, $frameGroups);
	}

	/**
	 * Moves past one object without building it; used to find object offsets.
	 */
	private function skipObject(int $objectIndex): void
	{
		$file = $this->fileHandle;

		while (($flagByte = $file->readByte()) !== 255) {
			foreach ($this->flagDefinition($flagByte)[1] as $parameter) {
				$parameterValue = $file->readBytes($parameter[1]);
				if (isset($parameter[2])) {
					$file->forward($parameterValue);
				}
			}
		}

		$hasFrameGroupHeaders = $this->version->hasFrameGroups() && $this->getCategoryByIndex($objectIndex) === ThingCategory::Looktype;
		$frameGroupCount = $hasFrameGroupHeaders ? $file->readByte() : 1;

		for ($i = 0; $i < $frameGroupCount; $i++) {
			if ($hasFrameGroupHeaders) {
				$file->forward(1);
			}

			$width  = $file->readByte();
			$height = $file->readByte();
			if ($width * $height > 1) {
				$file->forward(1);
			}

			[, $layers, $patternX, $patternY, $patternZ, $phases] = unpack('C5', $file->readText(5));

			if ($phases > 1 && $this->version->hasEnhancedAnimations()) {
				// type, loop count, start phase, then min/max duration per phase
				$file->forward(1 + 4 + 1 + $phases * 8);
			}

			$file->forward($width * $height * $layers * $patternX * $patternY * $patternZ * $phases * $this->spriteIdSize);
		}
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
		$this->offsets = [];
		$this->getObjectAddress($this->objectsTotal);
		$fileMap = $this->offsets;

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
		if (isset($this->offsets[$objectIndex])) {
			return $this->offsets[$objectIndex];
		}

		// Continue from the last object whose offset is known
		$this->offsets[0] ??= self::HEADER_SIZE;
		$index = array_key_last($this->offsets);
		$this->fileHandle->jump($this->offsets[$index]);

		while ($index < $objectIndex) {
			$this->skipObject($index);
			$this->offsets[++$index] = $this->fileHandle->getPos();
		}

		return $this->offsets[$objectIndex];
	}

	private function isFileMapped(): bool
	{
		return file_exists($this->cacheFilePath());
	}

	/**
	 * @return list<int>
	 */
	private function readFileMap(): array
	{
		return array_map('intval', file($this->cacheFilePath(), FILE_IGNORE_NEW_LINES));
	}

	// Keyed on path, size and modification time so the data file isn't hashed on every request
	private function cacheFilePath(): string
	{
		$path = realpath($this->client->dataFilePath);
		$key = md5($path . '|' . filesize($path) . '|' . filemtime($path));

		return $this->client->ensureDataCacheDir() . $key;
	}

	private function getObject(int $objectIndex, int $id): ThingType
	{
		$this->objectIndexInFile = $objectIndex;
		$this->objectAddress = $this->getObjectAddress($objectIndex);
		$this->fileHandle->jump($this->objectAddress);

		$thing = $this->readObject($objectIndex);

		$this->objectId = $id;
		$this->object = $thing->toArray();

		return $thing;
	}

	private function getObjectIndex(int $id, ThingCategory $category): int
	{
		return match ($category) {
			ThingCategory::Item       => $id - 100,
			ThingCategory::Looktype   => $this->filePhysicalItems + $id,
			ThingCategory::Effect     => $this->filePhysicalItems + $this->fileLooktypes + $id,
			ThingCategory::Projectile => $this->filePhysicalItems + $this->fileLooktypes + $this->fileEffects + $id,
		};
	}

	private function getIdByIndex(int $objectIndex, ThingCategory $category): int
	{
		return $objectIndex - $this->getObjectIndex(0, $category);
	}

	/**
	 * @param ThingCategory|int $category a ThingCategory or one of the TYPE_* constants
	 */
	public function get(ThingCategory|int $category, int $id): ThingType
	{
		$category = is_int($category) ? ThingCategory::from($category) : $category;

		[$min, $max, $label] = match ($category) {
			ThingCategory::Item       => [100, $this->fileItems, 'Item'],
			ThingCategory::Looktype   => [1, $this->fileLooktypes, 'Looktype'],
			ThingCategory::Effect     => [1, $this->fileEffects, 'Effect'],
			ThingCategory::Projectile => [1, $this->fileProjectiles, 'Projectile'],
		};

		if ($id < $min || $id > $max) {
			throw new \OutOfRangeException("{$label} ID out of file boundaries!");
		}

		return $this->getObject($this->getObjectIndex($id, $category), $id);
	}

	public function getItem(int $itemId): ThingType
	{
		return $this->get(ThingCategory::Item, $itemId);
	}

	public function getLooktype(int $looktypeId): ThingType
	{
		return $this->get(ThingCategory::Looktype, $looktypeId);
	}

	public function getEffect(int $effectId): ThingType
	{
		return $this->get(ThingCategory::Effect, $effectId);
	}

	public function getProjectile(int $projectileId): ThingType
	{
		return $this->get(ThingCategory::Projectile, $projectileId);
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
		print 'Data file name: ' . basename($this->client->dataFilePath) . '<br>';
		print 'Client version: ' . $this->version . '<br>';
		print 'File Signature: ' . $this->fileSignature . '<br>';
		print 'Items: ' . $this->fileItems . '<br>';
		print 'Looktypes: ' . $this->fileLooktypes . '<br>';
		print 'Effects: ' . $this->fileEffects . '<br>';
		print 'Projectiles: ' . $this->fileProjectiles . '<br>';
	}
}
