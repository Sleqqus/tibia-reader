<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * A Tibia client version (e.g. 1098 for 10.98) and the file format features that come with it.
 *
 * Every client that ships a Tibia.dat and Tibia.spr pair is supported, from 7.10 to LATEST. Later
 * clients store their objects as protobuf "appearances" and their sprites in sprite sheets, which
 * is a different format altogether.
 */
final class ClientVersion
{
	public const MIN = 710;
	public const MAX = 1100;

	/** The newest client version with known files, used when nothing else says which version a file is. */
	public const LATEST = 1100;

	// .dat signatures by client version. Up to 10.70 they are build timestamps; 10.71+ uses short
	// revision numbers. Some versions shipped more than one build, and some reused the files of the
	// version before (7.70 and 7.72 the 7.60 files, 10.98 those of 10.97). A signature is detected as the
	// newest version listed with it, which reads the files the same way as the others.
	private const DAT_SIGNATURES = [
		710  => [1040141098],
		713  => [1070922641],
		721  => [1071595718],
		723  => [1078860820],
		724  => [1078860820],
		726  => [1078860820],
		727  => [1078860820],
		730  => [1092248115],
		740  => [1103061404],
		741  => [1103061404],
		750  => [1123555699],
		755  => [1132145551],
		760  => [1134385715],
		770  => [1134385715],
		772  => [1134385715],
		780  => [1154369347],
		781  => [1154369347],
		790  => [1165854030],
		792  => [1168014195],
		800  => [1182783462],
		810  => [1197291335],
		811  => [1207307831],
		820  => [1214842282],
		821  => [1215089195],
		822  => [1218019489],
		830  => [1222254518],
		831  => [1222254518],
		840  => [1228759162],
		841  => [1236782105],
		842  => [1237464009],
		850  => [1246348779, 1246544092, 1256813714],
		852  => [1246544092],
		853  => [1256813714],
		854  => [1260268714, 1259161257, 1260959902],
		855  => [1268318035],
		856  => [1268318035],
		857  => [1268318035],
		860  => [1277736737, 1277983123],
		861  => [1282034876],
		862  => [1284977744],
		870  => [1291723461],
		871  => [1296144286],
		872  => [1303190042],
		900  => [1304076811],
		910  => [1309858559],
		920  => [1317043208],
		931  => [1317043208],
		940  => [1323769317],
		941  => [1323769317],
		942  => [1323769317],
		943  => [1323769317],
		944  => [1326378939, 1326469480, 1326891223, 1328624079],
		946  => [1332425759],
		950  => [1333114795],
		952  => [1334148972],
		953  => [1335956050],
		954  => [1339397483],
		960  => [1341813964],
		961  => [1344434077],
		963  => [1346156851],
		970  => [1349690512],
		971  => [1349690512],
		980  => [1355220596],
		981  => [1355924918],
		982  => [1361882377],
		983  => [1363180391],
		985  => [1365514779],
		986  => [1366354180],
		1010 => [1373894851],
		1020 => [1379332393],
		1021 => [1382699112],
		1030 => [1386582070],
		1031 => [1387189633],
		1032 => [1389940905],
		1034 => [1390889653],
		1035 => [1392376876],
		1036 => [1393925246],
		1037 => [1394518062],
		1038 => [1395900825],
		1039 => [1398427821],
		1040 => [1400477773],
		1041 => [1401114702],
		1050 => [1404454414],
		1051 => [1405668375],
		1052 => [1407752381],
		1053 => [1408948078],
		1054 => [1410153031],
		1055 => [1410500391],
		1056 => [1411466160],
		1057 => [1411724793],
		1059 => [1412696139],
		1061 => [1414060487],
		1063 => [1416319018],
		1064 => [1417610686],
		1070 => [1417788311],
		1071 => [13135],
		1072 => [14121],
		1073 => [14157],
		1075 => [14197],
		1076 => [14303],
		1077 => [14558],
		1078 => [14796],
		1079 => [14961],
		1080 => [15130],
		1081 => [15220],
		1082 => [15389],
		1084 => [15389],
		1090 => [16166],
		1091 => [16257],
		1092 => [16518],
		1093 => [16703],
		1094 => [16869],
		1095 => [16883],
		1096 => [16963],
		1097 => [17059],
		1098 => [17059],
		1099 => [17223],
		1100 => [17594],
	];

	/**
	 * The client versions whose file format differs from the version before, newest first.
	 * Detection by file structure tries the newest version of each format in this order.
	 */
	private const FORMAT_CHANGES = [
		1057, // looktypes have separate idle and moving frame groups
		1050, // animated objects store their phase durations
		1000, // the flag list makes room for "no move animation" (first in 10.10)
		960,  // 32-bit sprite ids and sprite count
		860,  // "chargeable" flag gone; cloth and market flags
		780,  // "chargeable" flag
		755,  // ground border flag, a third pattern dimension, displacement with an offset
		740,  // hangable and hook flags
		710,
	];

	// First version of each .dat flag list, newest first; see Flags::flagNames()
	private const FLAG_LAYOUTS = [1000, 860, 780, 755, 740, 710];

	/** @var array<int, int>|null .dat signature => version */
	private static ?array $versionsBySignature = null;

	private readonly bool $extendedSprites;
	private readonly bool $frameDurations;

	/**
	 * @param bool|null $extendedSprites 32-bit sprite ids and sprite count; on from 9.60. Set it for custom
	 *        clients that use them before that, such as 8.60 with more than 65535 sprites.
	 * @param bool|null $frameDurations per-phase animation durations in the .dat; on from 10.50. Set it for
	 *        custom clients that store them before that.
	 */
	public function __construct(
		public readonly int $version,
		?bool $extendedSprites = null,
		?bool $frameDurations = null,
	) {
		if ($version < self::MIN || $version > self::MAX) {
			throw new \InvalidArgumentException(
				"Client version {$version} is not supported. Use a number between " . self::MIN . ' and ' . self::MAX
				. ', e.g. ' . self::LATEST . ' for ' . self::label(self::LATEST) . '.'
			);
		}

		$this->extendedSprites = $extendedSprites ?? $version >= 960;
		$this->frameDurations = $frameDurations ?? $version >= 1050;
	}

	public static function latest(): self
	{
		return new self(self::LATEST);
	}

	public static function fromDatSignature(int $signature): ?self
	{
		if (self::$versionsBySignature === null) {
			self::$versionsBySignature = [];
			// Versions are listed oldest first, so the newest one sharing a signature is kept
			foreach (self::DAT_SIGNATURES as $version => $signatures) {
				foreach ($signatures as $datSignature) {
					self::$versionsBySignature[$datSignature] = $version;
				}
			}
		}

		return isset(self::$versionsBySignature[$signature]) ? new self(self::$versionsBySignature[$signature]) : null;
	}

	/**
	 * One version per file format, newest first: the newest known version before each format change.
	 * Then the variants custom clients use: the older formats with extended sprites or frame durations.
	 *
	 * @return list<self>
	 */
	public static function formatCandidates(): array
	{
		$candidates = [];
		$knownVersions = array_reverse(array_keys(self::DAT_SIGNATURES));
		$before = self::MAX + 1;

		foreach (self::FORMAT_CHANGES as $firstVersion) {
			foreach ($knownVersions as $version) {
				if ($version < $before && $version >= $firstVersion) {
					$candidates[] = new self($version);
					break;
				}
			}
			$before = $firstVersion;
		}

		// Custom clients: the older formats with 32-bit sprite ids, frame durations or both
		foreach ($candidates as $candidate) {
			foreach ([[true, false], [false, true], [true, true]] as [$extendedSprites, $frameDurations]) {
				$variant = new self(
					$candidate->version,
					$extendedSprites || $candidate->hasExtendedSprites(),
					$frameDurations || $candidate->hasEnhancedAnimations(),
				);
				$known = array_filter($candidates, static fn (self $other) => $other->hasSameFormatAs($variant));
				if ($known === []) {
					$candidates[] = $variant;
				}
			}
		}

		return $candidates;
	}

	/**
	 * Whether files of both versions are laid out the same way, so either can read them.
	 */
	public function hasSameFormatAs(self $other): bool
	{
		return $this->format() === $other->format();
	}

	private function format(): array
	{
		return [
			$this->flagLayout(),
			$this->hasExtendedSprites(),
			$this->hasEnhancedAnimations(),
			$this->hasFrameGroups(),
		];
	}

	/**
	 * The first version that stores object flags the way this one does.
	 */
	public function flagLayout(): int
	{
		foreach (self::FLAG_LAYOUTS as $firstVersion) {
			if ($this->version >= $firstVersion) {
				return $firstVersion;
			}
		}

		return self::MIN;
	}

	/**
	 * Guesses from a .spr signature alone whether the file uses 32-bit sprite counts (9.60+). The Sprite
	 * reader tells it from the file's layout and only falls back to this.
	 */
	public static function sprSignatureIsExtended(int $signature): bool
	{
		return $signature >= 1341814009;
	}

	// 9.60+, or set for custom clients: 32-bit sprite ids in the .dat and a 32-bit sprite count in the .spr
	public function hasExtendedSprites(): bool
	{
		return $this->extendedSprites;
	}

	// 10.50+, or set for custom clients: animation type, loop count, start phase and per-phase durations
	public function hasEnhancedAnimations(): bool
	{
		return $this->frameDurations;
	}

	// 10.57+: looktypes store separate idle and moving frame groups
	public function hasFrameGroups(): bool
	{
		return $this->version >= 1057;
	}

	// 7.55+: objects have a third pattern dimension (z), used for mounts by looktypes from 8.70 on
	public function hasPatternZ(): bool
	{
		return $this->version >= 755;
	}

	// 7.55+: the displacement flag stores its x and y offset; before, objects are always shifted by 8 pixels
	public function hasDisplacementOffset(): bool
	{
		return $this->version >= 755;
	}

	public function __toString(): string
	{
		$label = self::label($this->version);

		if ($this->extendedSprites !== $this->version >= 960 || $this->frameDurations !== $this->version >= 1050) {
			$features = array_keys(array_filter([
				'extended sprites' => $this->extendedSprites && $this->version < 960,
				'frame durations'  => $this->frameDurations && $this->version < 1050,
			]));
			$label .= ' (' . ($features === [] ? 'custom' : implode(', ', $features)) . ')';
		}

		return $label;
	}

	private static function label(int $version): string
	{
		return intdiv($version, 100) . '.' . str_pad((string) ($version % 100), 2, '0', STR_PAD_LEFT);
	}
}
