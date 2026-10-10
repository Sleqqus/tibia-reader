<?php
	/*
	 * Client files shared by the examples. They are read from tests/data/<version>/,
	 * filled by `composer test-data`. Pick another downloaded version with the
	 * TIBIA_VERSION environment variable, e.g. `TIBIA_VERSION=860 php -S localhost:8000`.
	 */

	require __DIR__ . '/../TibiaReader.php';

	$clientVersion = (int) (getenv('TIBIA_VERSION') ?: 1075);
	$clientDir = __DIR__ . "/../tests/data/{$clientVersion}";

	if (!is_file("{$clientDir}/Tibia.dat") || !is_file("{$clientDir}/Tibia.spr")) {
		http_response_code(500);
		exit("No client files in tests/data/{$clientVersion}/. Run `php bin/download-test-data.php {$clientVersion}` first.");
	}

	TibiaReader\Config::setDataFilePath("{$clientDir}/Tibia.dat");
	TibiaReader\Config::setSpriteFilePath("{$clientDir}/Tibia.spr");
	TibiaReader\Config::setDataCaching(true);
