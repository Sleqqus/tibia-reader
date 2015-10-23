<?php
	require('../TibiaReader.php');

	// TibiaReader\CONFIG::setDataFilePath("resources/760Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/980Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1050Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1057Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1070Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1072Tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1075Tibia.dat");
	TibiaReader\CONFIG::setDataFilePath("resources/1084Tibia.dat");

	TibiaReader\CONFIG::setSpriteFilePath("resources/1084Tibia.spr");

	TibiaReader\CONFIG::setDataFileMapping(true);

?>

	<!-- <h2>TibiaReader demos</h2>
	<a href="object_explorer.php">Object Explorer</a> | <a href="sprite_browser.php">Sprite Browser</a> | <a href="sprite.php?id=1">Show single sprite</a> <br/>
	<br/> -->
<?php
	// $object = new TibiaReader\Object();
	// $object->printFileInformation();
	// print "<br>";
	// $object = new TibiaReader\Sprite();
	// $object->printFileInformation();
	
	
	$outfit = new TibiaReader\Looktype();
	$outfit->setLooktype(128);
	$outfit->setColors(78, 69, 58, 76);
	$outfit->setAddons(0);
	$outfit->setMount(12)->setColors(123, 123, 123, 123);
	
	
	
	
?>