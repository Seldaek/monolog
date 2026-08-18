<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler\Fixtures;

/**
 * Minimal stream wrapper that proxies to a real directory on disk, mimicking
 * how Drupal's private:// wrapper works: it supports normal stream and
 * directory operations, but is not resolvable by glob(), which is the root
 * cause of https://github.com/Seldaek/monolog/issues/1784.
 */
class StreamWrapperPassthrough
{
    private const SCHEME = 'monolog-test';

    private static string $root = '';

    /** @var resource|null */
    private $handle;

    /** @var resource|null */
    private $dirHandle;

    public static function register(string $root): void
    {
        self::$root = rtrim($root, '/');

        if (\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }

        stream_wrapper_register(self::SCHEME, self::class);
    }

    public static function unregister(): void
    {
        if (\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
    }

    private static function realPath(string $path): string
    {
        $relative = substr($path, \strlen(self::SCHEME) + 3);

        return self::$root.'/'.ltrim($relative, '/');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $handle = fopen(self::realPath($path), $mode);
        if (false === $handle) {
            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function stream_write(string $data): int
    {
        return (int) fwrite($this->handle, $data);
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_flush(): bool
    {
        return fflush($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    /**
     * @return array<mixed>|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    /**
     * @return array<mixed>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $real = self::realPath($path);

        if ($flags & \STREAM_URL_STAT_QUIET) {
            return @stat($real);
        }

        return stat($real);
    }

    public function unlink(string $path): bool
    {
        return unlink(self::realPath($path));
    }

    public function rmdir(string $path, int $options): bool
    {
        return rmdir(self::realPath($path));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return mkdir(self::realPath($path), $mode, (bool) ($options & \STREAM_MKDIR_RECURSIVE));
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $handle = opendir(self::realPath($path));
        if (false === $handle) {
            return false;
        }

        $this->dirHandle = $handle;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->dirHandle);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->dirHandle);

        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->dirHandle);

        return true;
    }
}
