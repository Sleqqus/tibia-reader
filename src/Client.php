<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * One set of client files (.dat and/or .spr) and the readers for them.
 *
 * Create one per client version you work with. The readers are shared, so outfits and their
 * mounts reuse the same open files, offset map and decoded sprites.
 *
 *     $client = new Client('1098Tibia.dat', '1098Tibia.spr', dataCaching: true);
 *     $client->dat()->getItem(2400);
 *     $client->looktype(128)->getFrame();
 */
final class Client
{
	// Bumped when the format of the cached data file map changes, so old maps are never read
	private const CACHE_FORMAT = 'v1';

	/** The version passed in, if any. Detected versions are returned by version(). */
	public readonly ?int $clientVersion;
	public readonly string $dataCacheDir;

	/** True when $dataCacheDir wasn't passed in, so a cache that can't be written is skipped instead of throwing. */
	public readonly bool $usesDefaultCacheDir;

	private ?ClientVersion $version = null;
	private bool $versionResolved = false;
	private bool $versionGuessed = false;

	/** @var array<string, ClientVersion|null> versions detected from the structure of a .dat file, by file key */
	private static array $detectedVersions = [];

	private ?DatObject $dat = null;
	private ?Sprite $sprites = null;

	/**
	 * @param int|ClientVersion|null $clientVersion version number such as 1098 for 10.98. Detected from the
	 *        .dat file when omitted. Pass a ClientVersion for custom clients that use features earlier than
	 *        the official ones, e.g. new ClientVersion(860, extendedSprites: true).
	 * @param bool $dataCaching store a map of object byte offsets in $cacheDir, for O(1) lookups on later requests
	 * @param string|null $cacheDir where the data file map is stored; defaults to a tibia-reader folder in the
	 *        system temp directory (see defaultCacheDir()). Set it to keep the map somewhere that outlives reboots.
	 */
	public function __construct(
		public readonly ?string $dataFilePath = null,
		public readonly ?string $spriteFilePath = null,
		int|ClientVersion|null $clientVersion = null,
		public readonly bool $dataCaching = false,
		?string $cacheDir = null,
	) {
		if ($dataFilePath !== null && !is_file($dataFilePath)) {
			throw new \InvalidArgumentException('Specified data file does not exist.');
		}
		if ($spriteFilePath !== null && !is_file($spriteFilePath)) {
			throw new \InvalidArgumentException('Specified sprite file does not exist.');
		}

		if (is_int($clientVersion)) {
			$clientVersion = new ClientVersion($clientVersion);
		}
		$this->version = $clientVersion;
		$this->versionResolved = $clientVersion !== null;
		$this->clientVersion = $clientVersion?->version;
		$this->usesDefaultCacheDir = $cacheDir === null;
		$this->dataCacheDir = rtrim($cacheDir ?? self::defaultCacheDir() . 'data', '/\\') . DIRECTORY_SEPARATOR;
	}

	/**
	 * The cache root used when no cache directory is set: tibia-reader/<format>/ in the system temp
	 * directory. The data file map goes in its data/ folder. It is outside the library folder, which
	 * Composer replaces on every update and which is often read-only in production. The temp directory
	 * may be emptied at any time, which only costs one rebuild of the map.
	 */
	public static function defaultCacheDir(): string
	{
		return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'tibia-reader'
			. DIRECTORY_SEPARATOR . self::CACHE_FORMAT . DIRECTORY_SEPARATOR;
	}

	/**
	 * The client version: the one passed in, or the one detected from the .dat file.
	 * Null when there is neither.
	 *
	 * @throws \RuntimeException when the .dat file is in none of the known formats
	 */
	public function version(): ?ClientVersion
	{
		$version = $this->versionIfKnown();

		if ($version === null && $this->dataFilePath !== null) {
			throw new \RuntimeException(
				'Specified data file is not supported: its signature is unknown and it matches none of the known file formats. '
				. 'If it belongs to a custom client, set its version explicitly (Config::setClientVersion() or the Client constructor).'
			);
		}

		return $version;
	}

	/**
	 * Like version(), but returns null instead of throwing for an unrecognized .dat file.
	 *
	 * A .dat file is recognized by its signature. Files with an unknown signature, such as builds
	 * missing from the list or custom clients, are read as each known format in turn, newest first,
	 * and get the newest version whose format reads the whole file.
	 */
	public function versionIfKnown(): ?ClientVersion
	{
		if (!$this->versionResolved) {
			if ($this->dataFilePath !== null) {
				$signature = (new FileReader($this->dataFilePath))->readInt32();
				$this->version = ClientVersion::fromDatSignature($signature);
				if ($this->version === null) {
					$this->version = $this->detectVersionFromStructure();
					$this->versionGuessed = $this->version !== null;
				}
			}
			$this->versionResolved = true;
		}

		return $this->version;
	}

	/**
	 * Whether the version was detected from the layout of the .dat file rather than set or recognized by
	 * its signature. Such a version has the right file format, but may be newer than the actual client.
	 */
	public function versionIsGuessed(): bool
	{
		$this->versionIfKnown();

		return $this->versionGuessed;
	}

	private function detectVersionFromStructure(): ?ClientVersion
	{
		$path = realpath($this->dataFilePath);
		$key = $path . '|' . filesize($path) . '|' . filemtime($path);

		if (!array_key_exists($key, self::$detectedVersions)) {
			self::$detectedVersions[$key] = null;

			foreach (ClientVersion::formatCandidates() as $candidate) {
				if ((new self($path, clientVersion: $candidate))->dat()->readsWholeFile()) {
					self::$detectedVersions[$key] = $candidate;
					break;
				}
			}
		}

		return self::$detectedVersions[$key];
	}

	public function dat(): DatObject
	{
		return $this->dat ??= new DatObject($this);
	}

	public function sprites(): Sprite
	{
		return $this->sprites ??= new Sprite($this);
	}

	public function looktype(int $looktypeId): Looktype
	{
		return new Looktype($looktypeId, $this);
	}

	public function ensureDataCacheDir(): string
	{
		if (!is_dir($this->dataCacheDir) && !@mkdir($this->dataCacheDir, 0775, true) && !is_dir($this->dataCacheDir)) {
			throw new \RuntimeException("Unable to create cache directory: {$this->dataCacheDir}");
		}

		return $this->dataCacheDir;
	}
}
