<?php

declare(strict_types=1);

namespace Stanko\Pdf;

use Exception;
use Stanko\Pdf\Exception\CannotOpenImageFileException;
use Stanko\Pdf\Exception\CompressionException;
use Stanko\Pdf\Exception\ContentBufferException;
use Stanko\Pdf\Exception\FileStreamException;
use Stanko\Pdf\Exception\IncorrectPngFileException;
use Stanko\Pdf\Exception\InterlacingNotSupportedException;
use Stanko\Pdf\Exception\MemoryStreamException;
use Stanko\Pdf\Exception\UnknownColorTypeException;
use Stanko\Pdf\Exception\UnknownCompressionMethodException;
use Stanko\Pdf\Exception\UnknownFilterMethodException;
use Stanko\Pdf\Exception\UnpackException;
use Stanko\Pdf\Exception\UnsupportedImageTypeException;

final readonly class ImageParser
{
    public function __construct(
    ) {
    }

    /** @return array<mixed> */
    public function parseImage(string $file, string $type): array
    {
        if ($type === 'jpg' || $type == 'jpeg') {
            return $this->parseJpg($file);
        }
        if ($type === 'png') {
            return $this->parsePng($file);
        }
        if ($type === 'gif') {
            return $this->parseGif($file);
        }

        throw new UnsupportedImageTypeException();
    }

    /** @return array<mixed> */
    private function parseJpg(string $file): array
    {
        $a = getimagesize($file);
        if (!$a) {
            $this->Error('Missing or incorrect image file: ' . $file);
        }
        if ($a[2] != 2) {
            $this->Error('Not a JPEG file: ' . $file);
        }
        if (!isset($a['channels']) || $a['channels'] == 3) {
            $colspace = 'DeviceRGB';
        } elseif ($a['channels'] == 4) {
            $colspace = 'DeviceCMYK';
        } else {
            $colspace = 'DeviceGray';
        }
        $bpc = $a['bits'] ?? 8;
        $data = file_get_contents($file);

        if ($data === false) {
            throw new FileStreamException('file_get_contents() returned false');
        }

        return [
            'w' => $a[0],
            'h' => $a[1],
            'cs' => $colspace,
            'bpc' => $bpc,
            'f' => 'DCTDecode',
            'data' => $data,
        ];
    }

    /** @return array<mixed> */
    private function parsePng(string $file): array
    {
        $f = fopen($file, 'rb');
        if (!$f) {
            $this->Error('Can\'t open image file: ' . $file);
        }
        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    /**
     * @param resource $f
     *
     * @return array<mixed>
     */
    private function _parsepngstream($f, string $file): array
    {
        if ($this->_readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->Error('Not a PNG file: ' . $file);
        }

        $this->_readstream($f, 4);

        $fileTypeByte = $this->_readstream($f, 4);
        if ($fileTypeByte != 'IHDR') {
            throw new IncorrectPngFileException($file);
        }

        $w = $this->_readint($f);
        $h = $this->_readint($f);
        $bpc = ord($this->_readstream($f, 1));
        if ($bpc > 8) {
            $this->Error('16-bit depth not supported: ' . $file);
        }
        $ct = ord($this->_readstream($f, 1));
        if ($ct == 0 || $ct == 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct == 2 || $ct == 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct == 3) {
            $colspace = 'Indexed';
        } else {
            throw new UnknownColorTypeException();
        }

        $compressionByte = ord($this->_readstream($f, 1));
        if ($compressionByte != 0) {
            throw new UnknownCompressionMethodException($file);
        }

        $filterByte = ord($this->_readstream($f, 1));
        if ($filterByte != 0) {
            throw new UnknownFilterMethodException($file);
        }

        $interlacingByte = ord($this->_readstream($f, 1));
        if ($interlacingByte != 0) {
            throw new InterlacingNotSupportedException($file);
        }

        $this->_readstream($f, 4);
        $dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

        $pal = '';
        $trns = '';
        $data = '';
        do {
            $n = $this->_readint($f);
            $type = $this->_readstream($f, 4);
            if ($type == 'PLTE') {
                $pal = $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type == 'tRNS') {
                $t = $this->_readstream($f, $n);
                if ($ct == 0) {
                    $trns = [ord(substr($t, 1, 1))];
                } elseif ($ct == 2) {
                    $trns = [ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1))];
                } else {
                    $pos = strpos($t, chr(0));
                    if ($pos !== false) {
                        $trns = [$pos];
                    }
                }
                $this->_readstream($f, 4);
            } elseif ($type == 'IDAT') {
                $data .= $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                $this->_readstream($f, $n + 4);
            }
        } while ($n);

        if ($colspace == 'Indexed' && $pal === '') {
            $this->Error('Missing palette in ' . $file);
        }
        $info = [
            'w' => $w,
            'h' => $h,
            'cs' => $colspace,
            'bpc' => $bpc,
            'f' => 'FlateDecode',
            'dp' => $dp,
            'pal' => $pal,
            'trns' => $trns,
        ];
        if ($ct >= 4) {
            if (!function_exists('gzuncompress')) {
                $this->Error('Zlib not available, can\'t handle alpha channel: ' . $file);
            }
            $data = gzuncompress($data);
            if ($data === false) {
                throw new CompressionException('gzuncompress() returned false');
            }
            $color = '';
            $alpha = '';
            if ($ct == 4) {
                $len = 2 * $w;
                for ($i = 0; $i < $h; ++$i) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.)./s', '$1', $line);
                    $alpha .= preg_replace('/.(.)/s', '$1', $line);
                }
            } else {
                $len = 4 * $w;
                for ($i = 0; $i < $h; ++$i) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.{3})./s', '$1', $line);
                    $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
        }
        $info['data'] = $data;

        return $info;
    }

    /**
     * @param resource $f
     */
    private function _readstream($f, int $n): string
    {
        $res = '';
        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                throw new FileStreamException('fread() returned false');
            }
            $n -= strlen($s);
            $res .= $s;
        }
        if ($n > 0) {
            throw new FileStreamException('Unexpected end of stream');
        }

        return $res;
    }

    /**
     * @param resource $f
     */
    private function _readint($f): int
    {
        $a = unpack('Ni', $this->_readstream($f, 4));

        if ($a === false) {
            throw new UnpackException('unpack() returned false');
        }

        if (is_int($a['i']) === false) {
            throw new UnpackException('unpack() returned non-integer value');
        }

        return $a['i'];
    }

    /** @return array<mixed> */
    private function parseGif(string $file): array
    {
        if (!function_exists('imagepng')) {
            $this->Error('GD extension is required for GIF support');
        }
        if (!function_exists('imagecreatefromgif')) {
            $this->Error('GD has no GIF read support');
        }
        $im = imagecreatefromgif($file);
        if ($im === false) {
            throw new CannotOpenImageFileException($file);
        }
        imageinterlace($im, false);
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        if ($data === false) {
            throw new ContentBufferException('ob_get_clean() returned false');
        }
        imagedestroy($im);
        $f = fopen('php://temp', 'rb+');
        if ($f === false) {
            throw new MemoryStreamException('fopen() returned false');
        }
        fwrite($f, $data);
        rewind($f);
        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    private function Error(string $msg): never
    {
        throw new Exception('tFPDF error: ' . $msg);
    }
}
