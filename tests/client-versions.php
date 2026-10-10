<?php

/*
 * Client versions covered by the tests: every version whose .dat signature is listed in
 * src/ClientVersion.php, with the archive bin/download-test-data.php fetches its files from.
 *
 * The archives come from Gesior's OTS downloads mirror, which keeps the .dat and .spr of most
 * clients from 7.10 to 15.x. Versions set to null have no known download; put their files
 * into tests/data/<version>/ by hand to have them tested.
 */

$otsMe = 'https://downloads.ots.me/data/tibia-clients/dat_and_spr/';

return [
	760  => $otsMe . '760.zip',
	810  => null,
	811  => $otsMe . '811.zip',
	822  => $otsMe . '822.zip',
	854  => $otsMe . '854.zip',
	910  => $otsMe . '910.zip',
	944  => $otsMe . '944.zip',
	960  => $otsMe . '960.zip',
	980  => $otsMe . '980.zip',
	985  => null,
	1050 => $otsMe . '1050.zip',
	1057 => $otsMe . '1057.zip',
	1070 => $otsMe . '1070.zip',
	1072 => null,
	1075 => $otsMe . '1075.zip',
	1084 => null,
];
