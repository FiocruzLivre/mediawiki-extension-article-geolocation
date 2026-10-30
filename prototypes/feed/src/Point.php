<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use InvalidArgumentException;

final readonly class Point {
    public function __construct(public float $latitude, public float $longitude) {
        if (!is_finite($latitude) || !is_finite($longitude)
            || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Invalid geographic coordinates');
        }
    }

    /** This boundary rejects strings and arrays rather than silently coercing them. */
    public static function fromNumbers(mixed $latitude, mixed $longitude): self {
        if ((!is_int($latitude) && !is_float($latitude))
            || (!is_int($longitude) && !is_float($longitude))) {
            throw new InvalidArgumentException('Coordinates must be numbers');
        }
        return new self((float)$latitude, (float)$longitude);
    }

    /**
     * For the DECIMAL columns in geo_tags and literal template parameters only.
     * No exponent, comma, whitespace, NaN or Infinity is accepted.
     */
    public static function fromDecimals(mixed $latitude, mixed $longitude): self {
        return new self(self::decimal($latitude), self::decimal($longitude));
    }

    private static function decimal(mixed $value): float {
        if (is_int($value) || is_float($value)) {
            if (!is_finite((float)$value)) {
                throw new InvalidArgumentException('Non-finite coordinate');
            }
            return (float)$value;
        }
        if (!is_string($value)
            || !preg_match('/^[+-]?(?:\d{1,3})(?:\.\d{1,12})?$/D', $value)) {
            throw new InvalidArgumentException('Invalid decimal coordinate');
        }
        return (float)$value;
    }

    /** Longitude MUST be first in GeoJSON. */
    public function coordinates(): array {
        return [$this->longitude, $this->latitude];
    }

    public function canonical(): array {
        return [
            number_format($this->latitude, 8, '.', ''),
            number_format($this->longitude, 8, '.', ''),
        ];
    }

    public function equals(self $other): bool {
        return $this->canonical() === $other->canonical();
    }
}
