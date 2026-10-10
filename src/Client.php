<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * One set of client files (.dat and/or .spr) and the readers for them.
 *
 * Create one per client version you work with. The readers are shared, so outfits and their
 * mounts reuse the same open files, offset map and decoded sprites.
 *
 *     $client = new Client('1084Tibia.dat', '1084Tibia.spr', dataCaching: true);
 *     $client->dat()->getItem(2400);
 *     $client->looktype(128)->getFrame();
 */
final class Client
{
	public readonly ?int $clientVersion;
	public readonly string $dataCacheDir;

	private ?ClientVersion $version = null;
	private bool $versionResolved = false;

	private ?DatObject $dat = null;
	private ?Sprite $sprites = null;

	/**
	 * @param int|null $clientVersion version number such as 1084 for 10.84. Detected from the .dat file
	 *        signature when omitted; set it for custom clients whose signature isn't recognized.
	 * @param bool $dataCaching store a map of object byte offsets in $cacheDir, for O(1) lookups on later requests
	 * @param string|null $cacheDir where the data file map is stored; defaults to cache/data/ in the library folder
	 */
	public function __construct(
		public readonly ?string $dataFilePath = null,
		public readonly ?string $spriteFilePath = null,
		?int $clientVersion = null,
		public readonly bool $dataCaching = false,
		?string $cacheDir = null,
	) {
		if ($dataFilePath !== null && !is_file($dataFilePath)) {
			throw new \InvalidArgumentException('Specified data file does not exist.');
		}
		if ($spriteFilePath !== null && !is_file($spriteFilePath)) {
			throw new \InvalidArgumentException('Specified sprite file does not exist.');
		}

		$this->clientVersion = $clientVersion === null ? null : (new ClientVersion($clientVersion))->version;
		$this->dataCacheDir = rtrim($cacheDir ?? dirname(__DIR__) . '/cache/data', '/\\') . DIRECTORY_SEPARATOR;
	}

	/**
	 * The client version: the one passed in, or the one detected from the .dat file.
	 * Null when there is neither.
	 *
	 * @throws \RuntimeException when the .dat file signature isn't recognized
	 */
	public function version(): ?ClientVersion
	{
		$version = $this->versionIfKnown();

		if ($version === null && $this->dataFilePath !== null) {
			throw new \RuntimeException(
				'Specified data file is not supported! If it belongs to a custom client, set its version explicitly '
				. '(Config::setClientVersion() or the Client constructor).'
			);
		}

		return $version;
	}

	/**
	 * Like version(), but returns null instead of throwing for an unrecognized .dat file.
	 */
	public function versionIfKnown(): ?ClientVersion
	{
		if (!$this->versionResolved) {
			if ($this->clientVersion !== null) {
				$this->version = new ClientVersion($this->clientVersion);
			} elseif ($this->dataFilePath !== null) {
				$signature = (new FileReader($this->dataFilePath))->readInt32();
				$this->version = ClientVersion::fromDatSignature($signature);
			}
			$this->versionResolved = true;
		}

		return $this->version;
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
		if (!is_dir($this->dataCacheDir) && !mkdir($this->dataCacheDir, 0775, true) && !is_dir($this->dataCacheDir)) {
			throw new \RuntimeException("Unable to create cache directory: {$this->dataCacheDir}");
		}

		return $this->dataCacheDir;
	}
}
