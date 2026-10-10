<?php

declare(strict_types=1);

namespace TibiaReader\Tests;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use TibiaReader\Client;
use TibiaReader\ThingCategory;

/**
 * The data file map that dataCaching stores: where it goes, that it matches the file, and what
 * happens when it can't be read or written.
 */
final class DataCacheTest extends TestCase
{
	private string $cacheDir;

	protected function setUp(): void
	{
		$this->cacheDir = sys_get_temp_dir() . '/tibia-reader-test-' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void
	{
		foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
			unlink($file);
		}
		if (is_dir($this->cacheDir)) {
			rmdir($this->cacheDir);
		} elseif (is_file($this->cacheDir)) {
			unlink($this->cacheDir);
		}
	}

	public function testDefaultCacheDirIsInTheTempDirNotTheLibrary(): void
	{
		$client = new Client();

		$this->assertTrue($client->usesDefaultCacheDir);
		$this->assertStringStartsWith(Client::defaultCacheDir(), $client->dataCacheDir);
		$this->assertStringStartsWith(rtrim(sys_get_temp_dir(), '/\\'), $client->dataCacheDir);
		$this->assertStringNotContainsString(dirname(__DIR__), $client->dataCacheDir);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testCachedMapMatchesTheFile(int|string $key): void
	{
		$uncached = ClientFiles::client($key, sprites: false)->dat();
		$built = $this->cachingClient($key)->dat();
		$loaded = $this->cachingClient($key)->dat();

		$this->assertCount(1, $this->mapFiles());
		$last = $uncached->objectsTotal;
		$this->assertSame($uncached->getObjectAddress($last), $loaded->getObjectAddress($last));
		$this->assertSame(
			$uncached->get(ThingCategory::Looktype, $uncached->fileLooktypes)->toArray(),
			$loaded->get(ThingCategory::Looktype, $loaded->fileLooktypes)->toArray()
		);
		$this->assertSame($built->getObjectAddress($last), $loaded->getObjectAddress($last));
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testTruncatedMapIsRebuilt(int|string $key): void
	{
		$this->cachingClient($key)->dat();
		[$mapFile] = $this->mapFiles();
		$fullMap = file_get_contents($mapFile);
		file_put_contents($mapFile, substr($fullMap, 0, intdiv(strlen($fullMap), 2)));

		$dat = $this->cachingClient($key)->dat();

		$this->assertSame($fullMap, file_get_contents($mapFile));
		$this->assertSame(
			ClientFiles::client($key, sprites: false)->dat()->getObjectAddress($dat->objectsTotal),
			$dat->getObjectAddress($dat->objectsTotal)
		);
	}

	#[DataProviderExternal(ClientFiles::class, 'versions')]
	public function testCacheDirThatCantBeCreatedThrowsWhenSetExplicitly(int|string $key): void
	{
		ClientFiles::require($key, sprites: false);
		// A file where the directory should be
		touch($this->cacheDir);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Unable to create cache directory');

		(new Client(ClientFiles::datPath($key), clientVersion: ClientFiles::version($key), dataCaching: true, cacheDir: $this->cacheDir . '/data'))->dat();
	}

	private function cachingClient(int|string $key): Client
	{
		ClientFiles::require($key, sprites: false);

		return new Client(ClientFiles::datPath($key), clientVersion: ClientFiles::version($key), dataCaching: true, cacheDir: $this->cacheDir);
	}

	/**
	 * @return list<string>
	 */
	private function mapFiles(): array
	{
		return glob($this->cacheDir . '/*') ?: [];
	}
}
