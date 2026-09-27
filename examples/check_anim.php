<?php
	require '../TibiaReader.php';

	TibiaReader\Config::setDataFilePath('resources/1084Tibia.dat');
	TibiaReader\Config::setDataCaching(true);

	$obj = new TibiaReader\DatObject();

	print '<br>' . $obj->fileLooktypes;

	for ($looktype = 1; $looktype <= $obj->fileLooktypes; $looktype++) {
		$obj->getLooktype($looktype);

		if (!isset($obj->object['frameGroups'][1])) {
			print '<br>' . $obj->objectId;

			if ($obj->object['frameGroups'][0]['animations'] === 1) {
				print '<--------------------------------- WTF';
			}
		}
	}
