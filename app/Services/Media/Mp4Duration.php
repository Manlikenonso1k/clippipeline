<?php

namespace App\Services\Media;

/**
 * Pure-PHP duration reader for MP4/MOV files (no ffmpeg on the host).
 * Walks top-level boxes to `moov`, then reads `mvhd` (timescale + duration),
 * supporting 64-bit box sizes and mvhd version 0/1. Returns null when unknown.
 */
class Mp4Duration
{
    public static function seconds(string $path): ?float
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return null;
        }

        try {
            $size = (int) (fstat($fh)['size'] ?? 0);
            $moov = self::findBox($fh, 0, $size, 'moov');
            if (! $moov) {
                return null;
            }
            $mvhd = self::findBox($fh, $moov[0], $moov[1], 'mvhd');
            if (! $mvhd) {
                return null;
            }

            fseek($fh, $mvhd[0]);
            $header = fread($fh, 4);
            if ($header === false || strlen($header) < 4) {
                return null;
            }
            $version = ord($header[0]);

            if ($version === 1) {
                $data = fread($fh, 28); // created(8) modified(8) timescale(4) duration(8)
                if ($data === false || strlen($data) < 28) {
                    return null;
                }
                $timescale = unpack('N', substr($data, 16, 4))[1];
                $duration = self::uint64(substr($data, 20, 8));
            } else {
                $data = fread($fh, 16); // created(4) modified(4) timescale(4) duration(4)
                if ($data === false || strlen($data) < 16) {
                    return null;
                }
                $timescale = unpack('N', substr($data, 8, 4))[1];
                $duration = unpack('N', substr($data, 12, 4))[1];
            }

            if ($timescale <= 0 || $duration <= 0 || $duration === 0xFFFFFFFF) {
                return null;
            }

            return round($duration / $timescale, 3);
        } finally {
            fclose($fh);
        }
    }

    /**
     * Find a child box of $type within [$start, $end). Returns [payloadStart, payloadEnd].
     *
     * @param  resource  $fh
     * @return array{0: int, 1: int}|null
     */
    private static function findBox($fh, int $start, int $end, string $type): ?array
    {
        $pos = $start;
        while ($pos + 8 <= $end) {
            fseek($fh, $pos);
            $head = fread($fh, 8);
            if ($head === false || strlen($head) < 8) {
                return null;
            }
            $boxSize = unpack('N', substr($head, 0, 4))[1];
            $boxType = substr($head, 4, 4);
            $headerLen = 8;

            if ($boxSize === 1) {
                $ext = fread($fh, 8);
                if ($ext === false || strlen($ext) < 8) {
                    return null;
                }
                $boxSize = self::uint64($ext);
                $headerLen = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $end - $pos; // box runs to the end
            }

            if ($boxSize < $headerLen) {
                return null;
            }
            if ($boxType === $type) {
                return [$pos + $headerLen, min($end, $pos + $boxSize)];
            }
            $pos += $boxSize;
        }

        return null;
    }

    private static function uint64(string $bytes): int
    {
        $parts = unpack('Nhi/Nlo', $bytes);

        return ($parts['hi'] << 32) | $parts['lo'];
    }

    /** Build a minimal MP4 (ftyp + moov/mvhd) — used by tests. */
    public static function fixture(float $seconds, int $timescale = 1000): string
    {
        $mvhd = pack('C', 0)."\0\0\0".pack('NNNN', 0, 0, $timescale, (int) round($seconds * $timescale)).str_repeat("\0", 80);
        $mvhdBox = pack('N', 8 + strlen($mvhd)).'mvhd'.$mvhd;
        $moov = pack('N', 8 + strlen($mvhdBox)).'moov'.$mvhdBox;
        $ftyp = pack('N', 16).'ftypisom'.pack('N', 512);

        return $ftyp.$moov.pack('N', 16).'mdat'.str_repeat("\0", 8);
    }
}
