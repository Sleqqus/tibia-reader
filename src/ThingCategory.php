<?php

declare(strict_types=1);

namespace TibiaReader;

/**
 * The four object categories of a .dat file, in file order.
 */
enum ThingCategory: int
{
	case Item = 0;
	case Looktype = 1;
	case Effect = 2;
	case Projectile = 3;

	public function label(): string
	{
		return strtolower($this->name);
	}
}
