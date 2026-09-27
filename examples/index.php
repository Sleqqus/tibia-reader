<?php
	require('../TibiaReader.php');

	// TibiaReader\CONFIG::setDataFilePath("resources/760Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/980Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1050Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1057Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1070Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1072Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1075Tibia.dat");

	// TibiaReader\CONFIG::setDataFilePath("resources/760Tibia.dat");
	// TibiaReader\CONFIG::setSpriteFilePath("resources/760Tibia.spr");

	TibiaReader\CONFIG::setDataFilePath("resources/1082Tibia.dat");
	TibiaReader\CONFIG::setSpriteFilePath("resources/1082Tibia.spr");

	// TibiaReader\CONFIG::setDataFilePath("resources/1050Tibia.dat");
	// TibiaReader\CONFIG::setSpriteFilePath("resources/1050Tibia.spr");

	TibiaReader\CONFIG::setDataCaching(true);
	TibiaReader\CONFIG::setMinLooktypeImgSize(false);




	//<h2>TibiaReader demos</h2>
	//<a href="object_explorer.php">Object Explorer</a> | <a href="sprite_browser.php">Sprite Browser</a> | <a href="sprite.php?id=1">Show single sprite</a> <br/>
	//<br/>


	// $object = new TibiaReader\Object();
	// $object->printFileInformation();
	// print "<br>";
	// $object = new TibiaReader\Sprite();
	// $object->printFileInformation();


	$outfit = new TibiaReader\Looktype(733);
	// $outfit = new TibiaReader\Looktype(17);
	// $outfit->setLooktype(128);

	// $outfit->setColors(78, 69, 58, 76);
	$outfit->setColors(100, 20, 40, 31);

	$outfit->setAddons(1);
	$outfit->setMount(726)->setColors(123, 123, 123, 123);
	// $outfit->setMount(true);


	// $image = $outfit->setDirection(2)->setStep(1)->getFrame();

	$image = $outfit->getFrameMap();
	$image = $outfit->getFrameMap();



	header("Content-type: image/png");
	Imagepng($image);



	// $frame = $outfit->setDirection(0)->setFrame(1)->getFrame();



?>
