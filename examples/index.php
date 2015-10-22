<?php
	require('../TibiaReader.php');

	function br() { echo "\n<br>"; }

	// TibiaReader\CONFIG::setDataFilePath("resources/980tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1050tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1057tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1070tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1072tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1075tibia.dat");
	TibiaReader\CONFIG::setDataFilePath("resources/760tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1084tibia.dat");

	TibiaReader\CONFIG::setSpriteFilePath("resources/1084tibia.spr");
	

	$object = new TibiaReader\Object();
	$object->loadItem(16000);