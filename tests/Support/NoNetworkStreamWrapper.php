<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Suite-wide guard that makes it impossible for a test to reach the outside world
 * through PHP's stream layer.
 *
 * This exists because PdfService hardcodes a remote logo
 * (<img src="https://daike.eu/c/assets/logo.jpg">) and enables dompdf's
 * isRemoteEnabled. dompdf fetches remote assets with file_get_contents() whenever
 * allow_url_fopen is on (Dompdf\Helpers::getFileContent), so replacing the http/https
 * stream wrappers intercepts that fetch without touching any production code.
 *
 * Image requests are served a tiny local placeholder so PDF rendering stays
 * representative. Anything else throws, so an accidental outbound call fails loudly
 * instead of silently succeeding.
 */
final class NoNetworkStreamWrapper
{
    /** @var resource|null Set by PHP; must exist for a stream wrapper class. */
    public $context;

    /** @var list<string> Every URL any test attempted to open. */
    public static array $attempts = [];

    private string $data = '';
    private int $position = 0;

    public static function install(): void
    {
        self::$attempts = [];

        foreach (['http', 'https'] as $protocol) {
            if (in_array($protocol, stream_get_wrappers(), true)) {
                stream_wrapper_unregister($protocol);
            }
            stream_wrapper_register($protocol, self::class);
        }
    }

    public static function reset(): void
    {
        self::$attempts = [];
    }

    /** A 1x1 JPEG, so dompdf gets a decodable image without any network access. */
    public static function placeholderJpeg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
            . 'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAAB'
            . 'AAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==',
            true
        ) ?: '';
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$attempts[] = $path;

        $urlPath = (string) (parse_url($path, PHP_URL_PATH) ?? '');

        if (preg_match('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', $urlPath) === 1) {
            $this->data = self::placeholderJpeg();
            $this->position = 0;

            return true;
        }

        throw new \RuntimeException(sprintf(
            'Blocked an outbound network request during the test suite: %s. '
            . 'Tests must not reach the outside world.',
            $path
        ));
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->data, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_write(string $data): int
    {
        return 0;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $target = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->data) + $offset,
            default => -1,
        };

        if ($target < 0 || $target > strlen($this->data)) {
            return false;
        }

        $this->position = $target;

        return true;
    }

    public function stream_stat(): array
    {
        return ['size' => strlen($this->data)];
    }

    public function stream_close(): void
    {
        $this->data = '';
        $this->position = 0;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function url_stat(string $path, int $flags): array
    {
        return ['size' => strlen(self::placeholderJpeg())];
    }
}
