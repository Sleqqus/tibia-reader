<?php
	/*
	 * Shared setup for the examples. In a clone of the repository they read the client files in
	 * tests/data/<version>/, filled by `composer test-data`. Run them with PHP's built-in server
	 * from this folder:
	 *
	 *     php -S localhost:8000                     # http://localhost:8000/object_explorer.php
	 *     TIBIA_VERSION=860 php -S localhost:8000   # another downloaded version
	 *
	 * The Composer package has no tests/ folder, so there point TIBIA_CLIENT_DIR at any folder
	 * holding Tibia.dat and Tibia.spr:
	 *
	 *     TIBIA_CLIENT_DIR=/path/to/client php -S localhost:8000 -t vendor/sleqq/tibia-reader/examples
	 *
	 * The examples ship inside vendor/ when the library is installed with Composer. They
	 * refuse to run under any other web server, so a web root that exposes vendor/ doesn't
	 * expose them too.
	 */

	declare(strict_types=1);

	if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
		http_response_code(404);
		exit;
	}

	/**
	 * Ends the request with a plain-text error. Library messages can contain file paths,
	 * so uncaught exceptions are logged in full and only summarized in the response.
	 */
	function exampleError(int $status, string $message): never
	{
		if (PHP_SAPI === 'cli') {
			fwrite(STDERR, $message . "\n");
			exit(1);
		}

		if (!headers_sent()) {
			http_response_code($status);
			header('Content-Type: text/plain; charset=utf-8');
		}
		exit($message);
	}

	/**
	 * Reads a whole-number query parameter. Returns $default when it's missing, and stops with
	 * 400 Bad Request when it isn't a whole number in [$min, $max].
	 */
	function queryInt(string $name, int $default, int $min, int $max): int
	{
		if (!isset($_GET[$name]) || $_GET[$name] === '') {
			return $default;
		}

		$value = is_string($_GET[$name])
			? filter_var($_GET[$name], FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]])
			: false;

		if ($value === false) {
			exampleError(400, "Parameter '{$name}' must be a whole number from {$min} to {$max}.");
		}

		return $value;
	}

	set_exception_handler(static function (Throwable $e): void {
		error_log((string) $e);
		exampleError(500, 'Error: ' . get_class($e) . '. See the server log for details.');
	});

	require __DIR__ . '/../TibiaReader.php';

	if (getenv('TIBIA_CLIENT_DIR')) {
		$clientDir = rtrim(getenv('TIBIA_CLIENT_DIR'), '/\\');
		$missingFilesHelp = 'No Tibia.dat and Tibia.spr in the TIBIA_CLIENT_DIR folder.';
	} else {
		$clientVersion = (int) (getenv('TIBIA_VERSION') ?: 1075);
		$clientDir = __DIR__ . "/../tests/data/{$clientVersion}";
		$missingFilesHelp = "No client files in tests/data/{$clientVersion}/. Run `php bin/download-test-data.php {$clientVersion}` first,"
			. ' or set TIBIA_CLIENT_DIR to a folder holding Tibia.dat and Tibia.spr.';
	}

	if (!is_file("{$clientDir}/Tibia.dat") || !is_file("{$clientDir}/Tibia.spr")) {
		exampleError(500, $missingFilesHelp);
	}

	TibiaReader\Config::setDataFilePath("{$clientDir}/Tibia.dat");
	TibiaReader\Config::setSpriteFilePath("{$clientDir}/Tibia.spr");
	TibiaReader\Config::setDataCaching(true);
