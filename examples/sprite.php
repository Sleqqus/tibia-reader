<?php
	require __DIR__ . '/config.php';

	$spriteId = (int) ($_GET['id'] ?? 1);

	$sprite = new TibiaReader\Sprite();
	$sprite->printSprite($spriteId, false);
