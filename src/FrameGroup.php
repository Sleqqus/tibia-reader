<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * One set of sprites of an object: its size, patterns, animation phases and sprite ids.
 *
 * Sprite ids are ordered by width, height, layer, pattern x, pattern y, pattern z and phase,
 * fastest-changing first. For looktypes the patterns are direction (x), addon (y) and mount (z).
 */
final class FrameGroup
{
	public const IDLE = 0;
	public const MOVING = 1;

	/**
	 * @param list<int> $spriteIds
	 */
	public function __construct(
		public readonly int $width,
		public readonly int $height,
		public readonly ?int $exactSize,
		public readonly int $layers,
		public readonly int $patternX,
		public readonly int $patternY,
		public readonly int $patternZ,
		public readonly int $phases,
		public readonly ?Animation $animation,
		public readonly array $spriteIds,
	) {
	}

	public function spriteIndex(int $w, int $h, int $layer, int $x, int $y, int $z, int $phase): int
	{
		return (((((($phase * $this->patternZ + $z) * $this->patternY + $y) * $this->patternX + $x) * $this->layers + $layer)
			* $this->height + $h) * $this->width) + $w;
	}

	public function toArray(): array
	{
		return [
			'width'     => $this->width,
			'height'    => $this->height,
			'exactSize' => $this->exactSize,
			'layers'    => $this->layers,
			'patternX'  => $this->patternX,
			'patternY'  => $this->patternY,
			'patternZ'  => $this->patternZ,
			'phases'    => $this->phases,
			'animation' => $this->animation?->toArray(),
			'spriteIds' => $this->spriteIds,
		];
	}
}
