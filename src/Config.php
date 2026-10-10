<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * Process-wide default settings, used by `new DatObject()`, `new Sprite()` and `new Looktype($id)`
 * when no Client is passed. For more than one client version, create Client instances instead.
 */
class Config
{
	public static ?string $dataFilePath = null;
	public static ?string $spriteFilePath = null;
	public static ?int $clientVersion = null;
	public static ?string $mainCacheDir = null;

	public static bool $dataFileMapping = false;
	public static ?string $dataFileCacheDir = null;

	public static bool $looktypeCaching = false;
	public static ?string $looktypeCacheDir = null;
	public static int|false $looktypeMinImgSize = 64;

	private static ?Client $client = null;
	private static ?array $clientSettings = null;

	public static function setSpriteFilePath(string $path): void
	{
		if (!is_file($path)) {
			throw new \InvalidArgumentException('Specified sprite file does not exist.');
		}

		self::$spriteFilePath = $path;
	}

	public static function setDataFilePath(string $path): void
	{
		if (!is_file($path)) {
			throw new \InvalidArgumentException('Specified data file does not exist.');
		}

		self::$dataFilePath = $path;
	}

	/**
	 * Sets the client version (e.g. 1084 for 10.84) instead of detecting it from the .dat file signature.
	 * Needed for custom clients whose signature isn't recognized. Null restores detection.
	 */
	public static function setClientVersion(?int $version): void
	{
		self::$clientVersion = $version === null ? null : (new ClientVersion($version))->version;
	}

	public static function setDataCaching(bool $mapping): void
	{
		self::$dataFileMapping = $mapping;
	}

	public static function setLooktypeCaching(bool $looktypeCaching): void
	{
		self::$looktypeCaching = $looktypeCaching;
	}

	public static function setMinLooktypeImgSize(int|false $size): void
	{
		$allowedSizes = [false, 32, 64, 96, 128];
		if (!in_array($size, $allowedSizes, true)) {
			throw new \InvalidArgumentException('Not allowed image size specified!');
		}

		self::$looktypeMinImgSize = $size;
	}

	/**
	 * The Client built from the settings above. It is rebuilt whenever a setting changes,
	 * so changing a file path never leaves readers open on the old file.
	 */
	public static function client(): Client
	{
		$settings = [
			self::$dataFilePath,
			self::$spriteFilePath,
			self::$clientVersion,
			self::$dataFileMapping,
			self::$dataFileCacheDir ?? self::mainCacheDir() . 'data' . DIRECTORY_SEPARATOR,
		];

		if (self::$client === null || self::$clientSettings !== $settings) {
			self::$client = new Client(...array_combine(['dataFilePath', 'spriteFilePath', 'clientVersion', 'dataCaching', 'cacheDir'], $settings));
			self::$clientSettings = $settings;
		}

		return self::$client;
	}

	public static function dataCacheDir(): string
	{
		self::$dataFileCacheDir ??= self::mainCacheDir() . 'data' . DIRECTORY_SEPARATOR;
		self::ensureDir(self::$dataFileCacheDir);

		return self::$dataFileCacheDir;
	}

	public static function looktypeCacheDir(): string
	{
		self::$looktypeCacheDir ??= self::mainCacheDir() . 'looktype' . DIRECTORY_SEPARATOR;
		self::ensureDir(self::$looktypeCacheDir);

		return self::$looktypeCacheDir;
	}

	private static function mainCacheDir(): string
	{
		self::$mainCacheDir ??= dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;

		return self::$mainCacheDir;
	}

	private static function ensureDir(string $path): void
	{
		if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
			throw new \RuntimeException("Unable to create cache directory: {$path}");
		}
	}
}
