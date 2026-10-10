<?php

/*
 * Client files covered by the tests, with the archive bin/download-test-data.php fetches each from.
 *
 * Integer keys are client versions: every version whose .dat signature is listed in
 * src/ClientVersion.php, keyed by the version its signature is detected as. Versions set to null have
 * no known download; put their files into tests/data/<version>/ by hand to have them tested.
 *
 * String keys are files converted from the asset format of 12.x and newer clients back to Tibia.dat
 * and Tibia.spr, as OTClient-based clients use them. They carry no signature of their own, or reuse an
 * older one, and are read in the 11.00 format.
 *
 * The archives come from Gesior's OTS downloads mirror, which keeps the .dat and .spr of most
 * clients from 7.10 on.
 */

$otsMe = 'https://downloads.ots.me/data/tibia-clients/dat_and_spr/';

return [
	710  => $otsMe . '710.zip',
	713  => $otsMe . '713.zip',
	721  => $otsMe . '721.zip',
	727  => $otsMe . '727.zip', // 7.23 - 7.27 share these files
	730  => $otsMe . '730.zip',
	741  => $otsMe . '741.zip', // 7.40 and 7.41 share these files
	750  => $otsMe . '750.zip',
	755  => null,
	772  => $otsMe . '772.zip', // 7.60, 7.70 and 7.72 share these files
	781  => $otsMe . '781.zip', // 7.80 and 7.81 share these files
	790  => $otsMe . '790.zip',
	792  => $otsMe . '792.zip',
	800  => $otsMe . '800.zip',
	810  => null,
	811  => $otsMe . '811.zip',
	820  => $otsMe . '820.zip',
	821  => $otsMe . '821.zip',
	822  => $otsMe . '822.zip',
	831  => $otsMe . '831.zip', // 8.30 and 8.31 share these files
	840  => $otsMe . '840.zip',
	841  => $otsMe . '841.zip',
	842  => $otsMe . '842.zip',
	850  => $otsMe . '850.zip',
	852  => $otsMe . '852.zip',
	853  => $otsMe . '853.zip',
	854  => $otsMe . '854.zip',
	857  => $otsMe . '857.zip', // 8.55 - 8.57 share these files
	860  => $otsMe . '860.zip',
	861  => $otsMe . '861.zip',
	862  => $otsMe . '862.zip',
	870  => $otsMe . '870.zip',
	871  => $otsMe . '871.zip',
	872  => null,
	900  => $otsMe . '900.zip',
	910  => $otsMe . '910.zip',
	931  => $otsMe . '931.zip', // 9.20 and 9.31 share these files
	943  => $otsMe . '943.zip', // 9.40 - 9.43 share these files
	944  => $otsMe . '944.zip',
	946  => $otsMe . '946.zip',
	950  => null,
	952  => $otsMe . '952.zip',
	953  => null,
	954  => $otsMe . '954.zip',
	960  => $otsMe . '960.zip',
	961  => null,
	963  => null,
	971  => $otsMe . '971.zip', // 9.70 and 9.71 share these files
	980  => $otsMe . '980.zip',
	981  => $otsMe . '981.zip',
	982  => null,
	983  => null,
	985  => null,
	986  => null,
	1010 => $otsMe . '1010.zip',
	1020 => $otsMe . '1020.zip',
	1021 => null,
	1030 => null,
	1031 => null,
	1032 => null,
	1034 => null,
	1035 => $otsMe . '1035.zip',
	1036 => $otsMe . '1036.zip',
	1037 => $otsMe . '1037.zip',
	1038 => $otsMe . '1038.zip',
	1039 => null,
	1040 => null,
	1041 => $otsMe . '1041.zip',
	1050 => $otsMe . '1050.zip',
	1051 => null,
	1052 => null,
	1053 => null,
	1054 => null,
	1055 => $otsMe . '1055.zip',
	1056 => null,
	1057 => $otsMe . '1057.zip',
	1059 => $otsMe . '1059.zip',
	1061 => $otsMe . '1061.zip',
	1063 => $otsMe . '1063.zip',
	1064 => $otsMe . '1064.zip',
	1070 => $otsMe . '1070.zip',
	1071 => $otsMe . '1071.zip',
	1072 => null,
	1073 => $otsMe . '1073.zip',
	1075 => $otsMe . '1075.zip',
	1076 => $otsMe . '1076.zip',
	1077 => $otsMe . '1077.zip',
	1078 => $otsMe . '1078.zip',
	1079 => $otsMe . '1079.zip',
	1080 => $otsMe . '1080.zip',
	1081 => $otsMe . '1081.zip',
	1084 => $otsMe . '1082.zip', // filed as 10.82, which shares its signature with 10.84
	1090 => $otsMe . '1090.zip',
	1091 => $otsMe . '1091.zip',
	1092 => $otsMe . '1092.zip',
	1093 => $otsMe . '1093.zip',
	1094 => $otsMe . '1094.zip',
	1095 => $otsMe . '1095.zip',
	1096 => $otsMe . '1096.zip',
	1098 => $otsMe . '1098.zip', // 10.97 and 10.98 share these files
	1099 => $otsMe . '1099.zip',
	1100 => $otsMe . '1000.zip', // filed as 1000.zip; its signature, item count and date come after 10.99

	'converted-1287'  => $otsMe . '1287.zip',
	'converted-1310'  => $otsMe . '1310.zip',
	'converted-1340'  => $otsMe . '1340.zip',
	'converted-1411'  => $otsMe . '1411.zip',
	'converted-1501'  => $otsMe . '1501.zip',
	'converted-15.10' => $otsMe . '15.10.2daede.zip',
];
