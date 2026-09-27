<?php
	require('../TibiaReader.php');
	TibiaReader\CONFIG::setDataFilePath("resources/1084Tibia.dat");
	TibiaReader\CONFIG::setDataCaching(true);

	$obj = new TibiaReader\Object();
	print "<br>" . $obj->fileLooktypes;


	for ($looktype=1; $looktype <= $obj->fileLooktypes; $looktype++) {
		$obj->getLooktype($looktype);

		// if ($obj->object["frameGroups"][0]["animations"] > 1) {
		// 	print "<br>" . $obj->objectId;
		//
		// 	if (isset($obj->object["frameGroups"][1])) {
		// 		print "<--------------------------------- WTF";
		// 	}
		// }

		if (!isset($obj->object["frameGroups"][1])) {
			print "<br>" . $obj->objectId;

			if ($obj->object["frameGroups"][0]["animations"] === 1) {
				print "<--------------------------------- WTF";
			}
		}

	}
?>
