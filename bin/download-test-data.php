<?php

/*
 * Downloads the .dat and .spr files the tests run against into tests/data/<version>/.
 *
 *     php bin/download-test-data.php               all versions listed in tests/client-versions.php
 *     php bin/download-test-data.php 860 1098      only these versions
 *     php bin/download-test-data.php --force       download again even if the files are there
 *
 * Also available as `composer test-data`.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use TibiaReader\ClientVersion;
use TibiaReader\FileReader;

const DATA_DIR = __DIR__ . '/../tests/data';

if (!class_exists(ZipArchive::class)) {
	fwrite(STDERR, "The zip extension is required to unpack the client archives.\n");
	exit(1);
}

$sources = require __DIR__ . '/../tests/client-versions.php';

$args = array_slice($argv, 1);
$force = in_array('--force', $args, true);
$requested = array_map('intval', array_diff($args, ['--force']));

foreach ($requested as $version) {
	if (!array_key_exists($version, $sources)) {
		fwrite(STDERR, "Version {$version} is not listed in tests/client-versions.php.\n");
		exit(1);
	}
}

$failed = 0;

foreach ($requested ?: array_keys($sources) as $version) {
	$label = (string) new ClientVersion($version);
	$targetDir = DATA_DIR . "/{$version}";

	if (!$force && is_file("{$targetDir}/Tibia.dat") && is_file("{$targetDir}/Tibia.spr")) {
		echo "{$label}: already downloaded\n";
		continue;
	}

	$url = $sources[$version];
	if ($url === null) {
		echo "{$label}: no known download, put Tibia.dat and Tibia.spr into tests/data/{$version}/ to test it\n";
		continue;
	}

	echo "{$label}: downloading {$url}\n";

	try {
		$archive = download($url);
		try {
			extractClientFiles($archive, $targetDir);
		} finally {
			unlink($archive);
		}
	} catch (RuntimeException $e) {
		fwrite(STDERR, "{$label}: {$e->getMessage()}\n");
		$failed++;
		continue;
	}

	$signature = (new FileReader("{$targetDir}/Tibia.dat"))->readInt32();
	$detected = ClientVersion::fromDatSignature($signature);

	if ($detected?->version === $version) {
		echo "{$label}: done\n";
	} else {
		echo "{$label}: done, but its .dat signature {$signature} is detected as " . ($detected ?? 'an unknown version') . "\n";
	}
}

exit($failed > 0 ? 1 : 0);

/**
 * Downloads $url to a temporary file and returns its path.
 */
function download(string $url): string
{
	$path = tempnam(sys_get_temp_dir(), 'tibia-reader-');
	$file = fopen($path, 'wb');

	$curl = curl_init($url);
	curl_setopt_array($curl, [
		CURLOPT_FILE           => $file,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_FAILONERROR    => true,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_USERAGENT      => 'tibia-reader test data downloader',
	]);
	$ok = curl_exec($curl);
	$error = curl_error($curl);
	curl_close($curl);
	fclose($file);

	if (!$ok) {
		unlink($path);
		throw new RuntimeException("download failed: {$error}");
	}

	return $path;
}

/**
 * Writes the .dat and .spr file found in the archive to $targetDir as Tibia.dat and Tibia.spr.
 */
function extractClientFiles(string $archivePath, string $targetDir): void
{
	$zip = new ZipArchive();
	if ($zip->open($archivePath) !== true) {
		throw new RuntimeException('the download is not a zip archive');
	}

	try {
		foreach (['dat', 'spr'] as $extension) {
			$entry = findEntry($zip, $extension);
			if ($entry === null) {
				throw new RuntimeException("the archive has no .{$extension} file");
			}

			if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
				throw new RuntimeException("unable to create {$targetDir}");
			}

			// Copied by name rather than extracted, so paths inside the archive can't point outside $targetDir
			if (!copy("zip://{$archivePath}#{$entry}", "{$targetDir}/Tibia.{$extension}")) {
				throw new RuntimeException("unable to extract {$entry}");
			}
		}
	} finally {
		$zip->close();
	}
}

/**
 * Name of the archive entry with the given extension, preferring Tibia.<extension> when there are several.
 */
function findEntry(ZipArchive $zip, string $extension): ?string
{
	$found = null;

	for ($i = 0; $i < $zip->numFiles; $i++) {
		$name = $zip->getNameIndex($i);

		// Skip the resource fork copies macOS adds to archives (__MACOSX/._Tibia.dat)
		if (str_starts_with(basename($name), '._')) {
			continue;
		}
		if (strcasecmp(pathinfo($name, PATHINFO_EXTENSION), $extension) !== 0) {
			continue;
		}
		if (strcasecmp(basename($name), "Tibia.{$extension}") === 0) {
			return $name;
		}
		$found ??= $name;
	}

	return $found;
}
