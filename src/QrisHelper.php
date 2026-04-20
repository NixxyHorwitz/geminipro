<?php
declare(strict_types=1);

namespace App;

/**
 * QRIS Helper — parse and modify EMV QRIS strings.
 *
 * QRIS (Quick Response Code Indonesian Standard) follows EMV QR Code spec.
 * Dynamic QRIS amount is encoded in TLV (Tag-Length-Value) format.
 *
 * Key TLV IDs:
 *   00 = Payload Format Indicator
 *   01 = Point of Initiation Method (11=static, 12=dynamic)
 *   26-45 = Merchant Account Info
 *   52 = Merchant Category Code
 *   53 = Transaction Currency
 *   54 = Transaction Amount
 *   58 = Country Code
 *   59 = Merchant Name
 *   60 = Merchant City
 *   63 = CRC (4 hex chars, CRC-16/CCITT-FALSE)
 */
class QrisHelper
{
    /**
     * Parse raw QRIS string into TLV array
     */
    public static function parse(string $qris): array
    {
        $result = [];
        $pos    = 0;
        $len    = strlen($qris);

        while ($pos < $len) {
            if ($pos + 4 > $len) break;
            $tag    = substr($qris, $pos, 2);
            $length = (int) substr($qris, $pos + 2, 2);
            $value  = substr($qris, $pos + 4, $length);
            $result[$tag] = $value;
            $pos += 4 + $length;
        }

        return $result;
    }

    /**
     * Build QRIS string from TLV array (without CRC)
     */
    public static function build(array $tlv): string
    {
        $out = '';
        foreach ($tlv as $tag => $value) {
            if ($tag === '63') continue; // skip CRC, recalculate
            $len  = strlen($value);
            $out .= $tag . str_pad((string) $len, 2, '0', STR_PAD_LEFT) . $value;
        }
        return $out;
    }

    /**
     * CRC-16/CCITT-FALSE
     */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Convert static QRIS to dynamic with specific amount
     */
    public static function setAmount(string $rawQris, int $amount): string
    {
        // Strip CRC if present
        $data = rtrim($rawQris, "\n\r ");

        // Parse existing TLV
        $tlv = self::parse($data);

        // Make it dynamic (12 = dynamic)
        $tlv['01'] = '12';

        // Set amount (tag 54)
        if ($amount > 0) {
            $tlv['54'] = (string) $amount;
        } else {
            unset($tlv['54']);
        }

        // Rebuild without CRC
        $rebuilt = self::build($tlv);

        // Append CRC tag + length placeholder (4 chars)
        $withCrcTag = $rebuilt . '6304';

        // Calculate CRC
        $crc = self::crc16($withCrcTag);

        return $withCrcTag . $crc;
    }

    /**
     * Extract merchant name from QRIS
     */
    public static function getMerchantName(string $rawQris): string
    {
        $tlv = self::parse($rawQris);
        return $tlv['59'] ?? 'Unknown Merchant';
    }

    /**
     * Validate QRIS CRC
     */
    public static function validate(string $qris): bool
    {
        if (strlen($qris) < 6) return false;
        $data      = substr($qris, 0, -4);
        $givenCrc  = strtoupper(substr($qris, -4));
        $calcCrc   = self::crc16($data);
        return $givenCrc === $calcCrc;
    }

    /**
     * Try to decode QR from image using zbar (if available) or return null
     * For production, use a proper QR library or API
     */
    public static function decodeFromImage(string $imagePath): ?string
    {
        // Try zbarimg first (Linux/Windows with zbar installed)
        $escaped = escapeshellarg($imagePath);
        $output  = shell_exec("zbarimg --raw -q {$escaped} 2>/dev/null");
        if ($output !== null && trim($output) !== '') {
            return trim($output);
        }

        // Try zbar on Windows path
        $output = shell_exec("zbarimg.exe --raw -q {$escaped} 2>nul");
        if ($output !== null && trim($output) !== '') {
            return trim($output);
        }

        return null;
    }

    /**
     * Generate QR code PNG image from string using Google Charts API
     * or a simple PHP QR library fallback
     */
    public static function generateQrImage(string $data, int $size = 300): string
    {
        // Use QR server API (no library needed)
        $encoded = urlencode($data);
        $url     = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}&format=png&ecc=M";

        $ctx = stream_context_create([
            'http' => ['timeout' => 10],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $img = @file_get_contents($url, false, $ctx);
        if ($img === false) return '';

        return 'data:image/png;base64,' . base64_encode($img);
    }

    /**
     * Generate QR code and save to file, return path
     */
    public static function generateQrFile(string $data, string $savePath, int $size = 300): bool
    {
        $encoded = urlencode($data);
        $url     = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}&format=png&ecc=M";

        $ctx = stream_context_create([
            'http' => ['timeout' => 10],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $img = @file_get_contents($url, false, $ctx);
        if ($img === false) return false;

        return (bool) file_put_contents($savePath, $img);
    }
}
