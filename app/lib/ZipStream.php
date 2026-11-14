<?php
declare(strict_types=1);

/**
 * Streams a ZIP straight to the browser without building it on disk first.
 * Files are stored as they are (no compression: photos are already compressed, and the
 * bytes inside the ZIP stay identical to the originals). Supports archives over 4 GB (Zip64).
 */
final class ZipStream
{
    private int $offset = 0;
    private array $entries = [];
    /** @var resource */
    private $out;

    /** @param resource|null $out where to write; defaults to the browser */
    public function __construct(?string $downloadName = null, $out = null)
    {
        $this->out = $out ?? fopen('php://output', 'wb');
        if ($downloadName !== null) {
            while (ob_get_level()) ob_end_clean();
            @set_time_limit(0);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Cache-Control: no-store');
            header('X-Accel-Buffering: no');
        }
    }

    public function addFile(string $name, string $path, ?int $crc32 = null, ?int $size = null): bool
    {
        if (!is_file($path) || ($fh = fopen($path, 'rb')) === false) return false;
        $real = (int) filesize($path);
        if ($crc32 === null || $size !== $real) $crc32 = (int) hexdec((string) hash_file('crc32b', $path));
        $this->header($name, $crc32, $real);
        while (!feof($fh)) {
            $chunk = fread($fh, 1048576);
            if ($chunk === false) break;
            fwrite($this->out, $chunk);
        }
        fclose($fh);
        $this->offset += $real;
        return true;
    }

    public function addString(string $name, string $data): void
    {
        $this->header($name, crc32($data), strlen($data));
        fwrite($this->out, $data);
        $this->offset += strlen($data);
    }

    private function header(string $name, int $crc, int $size): void
    {
        [$time, $date] = self::dosTime(time());
        $this->entries[] = [$name, $crc, $size, $this->offset, $time, $date];
        $h = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $size, $size, strlen($name), 0) . $name;
        fwrite($this->out, $h);
        $this->offset += strlen($h);
    }

    public function finish(): void
    {
        $cdStart = $this->offset;
        $cd = '';
        foreach ($this->entries as [$name, $crc, $size, $offset, $time, $date]) {
            $extra = '';
            $off32 = $offset;
            if ($offset >= 0xFFFFFFFF) {
                $extra = pack('vvP', 0x0001, 8, $offset);
                $off32 = 0xFFFFFFFF;
            }
            $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 0x032D, $extra === '' ? 20 : 45, 0x0800, 0, $time, $date, $crc, $size, $size,
                strlen($name), strlen($extra), 0, 0, 0, 0x81A40000, $off32) . $name . $extra;
            if (strlen($cd) > 1048576) {
                fwrite($this->out, $cd);
                $this->offset += strlen($cd);
                $cd = '';
            }
        }
        fwrite($this->out, $cd);
        $this->offset += strlen($cd);
        $cdSize = $this->offset - $cdStart;
        $count = count($this->entries);
        if ($count > 0xFFFF || $cdStart >= 0xFFFFFFFF || $cdSize >= 0xFFFFFFFF) {
            fwrite($this->out, pack('VPvvVVPPPP', 0x06064b50, 44, 0x032D, 45, 0, 0, $count, $count, $cdSize, $cdStart));
            fwrite($this->out, pack('VVPV', 0x07064b50, 0, $this->offset, 1));
        }
        fwrite($this->out, pack('VvvvvVVv', 0x06054b50, 0, 0, min($count, 0xFFFF), min($count, 0xFFFF), min($cdSize, 0xFFFFFFFF), min($cdStart, 0xFFFFFFFF), 0));
        fflush($this->out);
    }

    private static function dosTime(int $ts): array
    {
        $d = getdate($ts);
        return [
            ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1),
            (max(0, $d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
        ];
    }
}
