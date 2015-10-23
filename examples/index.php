<?php
	require('../TibiaReader.php');

	// TibiaReader\CONFIG::setDataFilePath("resources/760tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/980tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1050tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1057tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1070tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1072tibia.dat");
	// TibiaReader\CONFIG::setDataFilePath("resources/1075tibia.dat");
	TibiaReader\CONFIG::setDataFilePath("resources/1084tibia.dat");

	TibiaReader\CONFIG::setSpriteFilePath("resources/1084tibia.spr");

	TibiaReader\CONFIG::setDataFileMapping(true);
?>

	<h2>TibiaReader demos</h2>

	<a href="object_explorer.php">Object Explorer</a> | <a href="sprite_browser.php">Sprite Browser</a> | <a href="sprite.php?id=1">Show single sprite</a> <br/>
	<br/>

<?php
	$object = new TibiaReader\Object();
	$object->printFileInformation();
?>
<br>
<?php
	$object = new TibiaReader\Sprite();
	$object->printFileInformation();
?>