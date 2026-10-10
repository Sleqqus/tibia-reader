<?php
	require __DIR__ . '/config.php';

	$outfit = new TibiaReader\Looktype(128);
	$outfit->setColors(100, 20, 40, 31);
	$outfit->setAddons(TibiaReader\Looktype::ADDON_FIRST);

	// Mounts came with 8.70; older files have no looktype 368
	if (TibiaReader\Config::client()->dat()->fileLooktypes >= 368) {
		$outfit->setMount(368)->setColors(123, 123, 123, 123);
	}

	// Single frame instead of the full map:
	// $image = $outfit->setDirection(TibiaReader\Looktype::DIRECTION_SOUTH)->setStep(1)->getFrame();

	$image = $outfit->getFrameMap();

	header('Content-Type: image/png');
	imagepng($image);
