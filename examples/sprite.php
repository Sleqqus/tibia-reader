<?php
	require __DIR__ . '/config.php';

	$sprite = new TibiaReader\Sprite();

	// Sprite 0 is the empty sprite objects use for blank tiles
	$spriteId = queryInt('id', 1, 0, $sprite->spritesCount);

	header('Cache-Control: max-age=3600');
	$sprite->printSprite($spriteId, false);
