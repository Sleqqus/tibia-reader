<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * Animation timing stored by 10.50+ clients for frame groups with more than one phase.
 */
final class Animation
{
	/**
	 * @param list<array{min: int, max: int}> $durations duration of each phase in milliseconds
	 */
	public function __construct(
		public readonly int $type,
		public readonly int $loopCount,
		public readonly int $startPhase,
		public readonly array $durations,
	) {
	}

	public function isAsynchronous(): bool
	{
		return $this->type === 0;
	}

	public function toArray(): array
	{
		return [
			'type'       => $this->type,
			'loopCount'  => $this->loopCount,
			'startPhase' => $this->startPhase,
			'durations'  => $this->durations,
		];
	}
}
