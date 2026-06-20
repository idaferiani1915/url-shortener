<?php

namespace App\Libraries;

class Base62Encoder
{
    private static $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function encode(int $num): string
    {
        $base = 62;
        $r = $num % $base;
        $res = self::$chars[$r];
        $q = (int) ($num / $base);
        while ($q > 0) {
            $r = $q % $base;
            $q = (int) ($q / $base);
            $res = self::$chars[$r] . $res;
        }
        return $res;
    }

    /**
     * Generate a collision-safe short code.
     * Combines millisecond timestamp and a random offset to form a large integer,
     * then encodes it in Base62.
     */
    public static function generate(): string
    {
        // Get timestamp in milliseconds
        $time = (int) (microtime(true) * 1000);
        
        // Random 3-digit number (100 to 999) to prevent collision
        $rand = random_int(100, 999);
        
        // Combine them: e.g. 1718873000000 * 1000 + 999 = 1718873000000999
        $combined = ($time * 1000) + $rand;
        
        return self::encode($combined);
    }
}
