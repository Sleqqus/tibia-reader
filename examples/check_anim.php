<?php
	require __DIR__ . '/config.php';

	$obj = new TibiaReader\DatObject();

	print '<br>' . $obj->fileLooktypes;

	for ($looktype = 1; $looktype <= $obj->fileLooktypes; $looktype++) {
		$thing = $obj->getLooktype($looktype);

		if (!isset($thing->frameGroups[TibiaReader\FrameGroup::MOVING])) {
			print '<br>' . $thing->id;

			if ($thing->frameGroup()->phases === 1) {
				print '<--------------------------------- WTF';
			}
		}
	}
