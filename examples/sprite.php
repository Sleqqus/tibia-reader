<?php
	require '../TibiaReader.php';

	TibiaReader\Config::setSpriteFilePath('resources/1084Tibia.spr');

	$spriteId = (int) ($_GET['id'] ?? 1);

	$sprite = new TibiaReader\Sprite();
	$sprite->printSprite($spriteId, false);
