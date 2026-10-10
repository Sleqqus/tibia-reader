<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * A Tibia client version (e.g. 1084 for 10.84) and the file format features that come with it.
 */
final class ClientVersion
{
	public const MIN = 755;
	public const MAX = 1099;

	// .dat signatures of the supported clients. Up to 10.70 they are build timestamps;
	// 10.72+ uses short revision numbers.
	private const DAT_SIGNATURES = [
		15389      => 1084,
		14197      => 1075,
		14121      => 1072,
		1417788311 => 1070,
		1411724793 => 1057,
		1404454414 => 1050,
		1365514779 => 985,
		1355220596 => 980,
		1341813964 => 960,
		1328624079 => 944,
		1309858559 => 910,
		1260268714 => 854,
		1218019489 => 822,
		1207307831 => 811,
		1197291335 => 810,
		1134385715 => 760,
	];

	// .spr signatures are build timestamps in every supported version; 9.60+ files are at or above this one
	private const SPR_SIGNATURE_EXTENDED = 1341814009;

	public function __construct(public readonly int $version)
	{
		if ($version < self::MIN || $version > self::MAX) {
			throw new \InvalidArgumentException(
				"Client version {$version} is not supported. Use a number between " . self::MIN . ' and ' . self::MAX . ', e.g. 1084 for 10.84.'
			);
		}
	}

	public static function fromDatSignature(int $signature): ?self
	{
		return isset(self::DAT_SIGNATURES[$signature]) ? new self(self::DAT_SIGNATURES[$signature]) : null;
	}

	/**
	 * Guesses from a .spr signature alone whether the file uses 32-bit sprite counts (9.60+).
	 */
	public static function sprSignatureIsExtended(int $signature): bool
	{
		return $signature >= self::SPR_SIGNATURE_EXTENDED;
	}

	// 9.60+: 32-bit sprite ids in the .dat and a 32-bit sprite count in the .spr
	public function hasExtendedSprites(): bool
	{
		return $this->version >= 960;
	}

	// 10.50+: animation type, loop count, start phase and per-phase durations
	public function hasEnhancedAnimations(): bool
	{
		return $this->version >= 1050;
	}

	// 10.57+: looktypes store separate idle and moving frame groups
	public function hasFrameGroups(): bool
	{
		return $this->version >= 1057;
	}

	public function __toString(): string
	{
		return intdiv($this->version, 100) . '.' . str_pad((string) ($this->version % 100), 2, '0', STR_PAD_LEFT);
	}
}
