<?php
	require __DIR__ . '/config.php';

	$outfit = new TibiaReader\Looktype(128);
	$outfit->setColors(100, 20, 40, 31);
	$outfit->setAddons(TibiaReader\Looktype::ADDON_FIRST);
	$outfit->setMount(368)->setColors(123, 123, 123, 123);

	// Single frame instead of the full map:
	// $image = $outfit->setDirection(TibiaReader\Looktype::DIRECTION_SOUTH)->setStep(1)->getFrame();

	$image = $outfit->getFrameMap();

	header('Content-type: image/png');
	imagepng($image);
