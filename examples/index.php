<?php
	require '../TibiaReader.php';

	TibiaReader\Config::setDataFilePath('resources/1082Tibia.dat');
	TibiaReader\Config::setSpriteFilePath('resources/1082Tibia.spr');
	// TibiaReader\Config::setDataFilePath('resources/760Tibia.dat');
	// TibiaReader\Config::setSpriteFilePath('resources/760Tibia.spr');

	TibiaReader\Config::setDataCaching(true);


	$outfit = new TibiaReader\Looktype(733);
	$outfit->setColors(100, 20, 40, 31);
	$outfit->setAddons(TibiaReader\Looktype::ADDON_FIRST);
	$outfit->setMount(726)->setColors(123, 123, 123, 123);

	// Single frame instead of the full map:
	// $image = $outfit->setDirection(TibiaReader\Looktype::DIRECTION_SOUTH)->setStep(1)->getFrame();

	$image = $outfit->getFrameMap();

	header('Content-type: image/png');
	imagepng($image);
