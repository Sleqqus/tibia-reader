<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * An object read from the .dat file: an item, looktype, effect or projectile.
 */
final class ThingType
{
	/**
	 * @param array<string, array<string, int|string>> $flags flag name => parameters (empty for flags without any)
	 * @param array<int, FrameGroup> $frameGroups keyed by group type (FrameGroup::IDLE, FrameGroup::MOVING).
	 *        Objects other than looktypes, and anything before 10.57, have a single group keyed 0.
	 */
	public function __construct(
		public readonly ThingCategory $category,
		public readonly int $id,
		public readonly array $flags,
		public readonly array $frameGroups,
	) {
	}

	public function hasFlag(string $name): bool
	{
		return isset($this->flags[$name]);
	}

	/**
	 * @return array<string, int|string>|null the flag's parameters, or null when the object doesn't have it
	 */
	public function flag(string $name): ?array
	{
		return $this->flags[$name] ?? null;
	}

	/**
	 * Returns the requested frame group, or the first one when the object doesn't have it.
	 */
	public function frameGroup(int $type = FrameGroup::IDLE): FrameGroup
	{
		return $this->frameGroups[$type] ?? $this->frameGroups[array_key_first($this->frameGroups)];
	}

	public function toArray(): array
	{
		return [
			'category'    => $this->category->label(),
			'id'          => $this->id,
			'flags'       => $this->flags,
			'frameGroups' => array_map(static fn (FrameGroup $group) => $group->toArray(), $this->frameGroups),
		];
	}
}
