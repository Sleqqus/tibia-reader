<?php
	require('../TibiaReader.php');

	TibiaReader\CONFIG::setSpriteFilePath("resources/1084Tibia.spr");
	
	$spriteId = intval($_GET['id']);
	
	$sprite = new TibiaReader\Sprite();
	$sprite->printSprite($spriteId, false);
?>