<?php
	require('../TibiaReader.php');

	TibiaReader\CONFIG::setSpriteFilePath("resources/1084tibia.spr");
	
	$spriteId = intval($_GET['id']);
	
	$sprite = new TibiaReader\Sprite();
	$sprite->printImage($spriteId, false);
?>