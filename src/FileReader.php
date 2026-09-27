<?php

declare(strict_types=1);

namespace TibiaReader;

class FileReader
{
	/** @var resource */
	private $fileHandle;

	public function __construct(string $file)
	{
		$handle = fopen($file, 'rb');
		if ($handle === false) {
			throw new \RuntimeException("Unable to open file: {$file}");
		}

		$this->fileHandle = $handle;
	}

	public function __destruct()
	{
		if (is_resource($this->fileHandle)) {
			fclose($this->fileHandle);
		}
	}

	public function jump(int $newAddress): static
	{
		fseek($this->fileHandle, $newAddress);
		return $this;
	}

	public function forward(int $bytes): static
	{
		fseek($this->fileHandle, $bytes, SEEK_CUR);
		return $this;
	}

	public function backward(int $bytes): static
	{
		fseek($this->fileHandle, -$bytes, SEEK_CUR);
		return $this;
	}

	public function getPos(): int
	{
		return ftell($this->fileHandle);
	}

	public function readByte(): int
	{
		return ord($this->read(1));
	}

	public function readInt16(): int
	{
		return unpack('v', $this->read(2))[1];
	}

	public function readInt32(): int
	{
		return unpack('V', $this->read(4))[1];
	}

	public function readBytes(int $bytes): int
	{
		return match ($bytes) {
			2 => $this->readInt16(),
			4 => $this->readInt32(),
			default => $this->readByte(),
		};
	}

	public function readText(int $bytes): string
	{
		return $this->read($bytes);
	}

	private function read(int $bytes): string
	{
		if ($bytes === 0) {
			return '';
		}

		$data = fread($this->fileHandle, $bytes);
		if ($data === false || strlen($data) !== $bytes) {
			throw new \RuntimeException('Unexpected end of file at byte ' . $this->getPos());
		}

		return $data;
	}
}
