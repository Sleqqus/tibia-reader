<?php
	/*
	 * Lists the looktypes that have no walking animation of their own, i.e. no FrameGroup::MOVING.
	 * Frame groups exist since 10.57. Reads every looktype in the file, so it runs from the
	 * command line only:
	 *
	 *     php examples/check_anim.php
	 */

	require __DIR__ . '/config.php';

	if (PHP_SAPI !== 'cli') {
		exampleError(404, 'Run this example from the command line: php examples/check_anim.php');
	}

	$dat = new TibiaReader\DatObject();

	if (!$dat->version()->hasFrameGroups()) {
		exampleError(1, "Client {$dat->version()} has no frame groups; they were added in 10.57.");
	}

	print "Client {$dat->version()}, {$dat->fileLooktypes} looktypes.\n";
	print "Looktypes without a moving frame group:\n";

	for ($looktype = 1; $looktype <= $dat->fileLooktypes; $looktype++) {
		$thing = $dat->getLooktype($looktype);

		if (!isset($thing->frameGroups[TibiaReader\FrameGroup::MOVING])) {
			// A single idle phase means the looktype isn't animated at all
			print $thing->id . ($thing->frameGroup()->phases === 1 ? ' (not animated)' : '') . "\n";
		}
	}
