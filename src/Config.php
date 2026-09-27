<?php

declare(strict_types=1);

namespace TibiaReader;

class Config
{
	public static ?string $dataFilePath = null;
	public static ?string $spriteFilePath = null;
	public static ?string $mainCacheDir = null;

	public static bool $dataFileMapping = false;
	public static ?string $dataFileCacheDir = null;

	public static bool $looktypeCaching = false;
	public static ?string $looktypeCacheDir = null;
	public static int|false $looktypeMinImgSize = 64;

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
