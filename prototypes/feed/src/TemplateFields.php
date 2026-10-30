<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use InvalidArgumentException;

final readonly class TemplateFields {
    public function __construct(
        public bool $requested = false,
        public ?Point $point = null,
        public string $precision = '',
        public string $locationType = '',
        public bool $invalid = false,
    ) {}
}

/**
 * Strict reader for a single literal direct invocation of
 * {{Geolocalização do artigo|...}}. Does not evaluate arbitrary wikitext.
 * Transcluded or dynamically computed parameters are not accepted as evidence.
 */
final class TemplateFieldsReader {
    public function parse(string $wikitext): TemplateFields {
        $text = preg_replace('~<!--.*?-->~s', '', $wikitext);
        $text = preg_replace('~<(nowiki|pre|source|syntaxhighlight)\b[^>]*>.*?</\1\s*>~isu', '', $text);
        if (!preg_match_all('/\{\{\s*Geolocaliza[cç][aã]o[ _]+do[ _]+artigo\s*(?=[|}])/iu', $text, $hits, PREG_OFFSET_CAPTURE)
            || count($hits[0]) !== 1) {
            return new TemplateFields();
        }
        $start = $hits[0][0][1];
        $parts = $this->splitInvocation($text, $start);
        if ($parts === null) {
            return new TemplateFields(invalid: true);
        }
        $fields = [];
        foreach (array_slice($parts, 1) as $part) {
            $equal = strpos($part, '=');
            if ($equal === false) {
                return new TemplateFields(invalid: true);
            }
            $key = mb_strtolower(trim(substr($part, 0, $equal)));
            $value = trim(substr($part, $equal + 1));
            if (array_key_exists($key, $fields)) {
                return new TemplateFields(invalid: true);
            }
            $fields[$key] = $value;
        }
        $flag = mb_strtolower($fields['exibir_no_mapa'] ?? '');
        $requested = in_array($flag, ['sim', 'true', 'yes', '1'], true);
        if ($flag !== '' && !$requested && !in_array($flag, ['não', 'nao', 'no', 'false', '0'], true)) {
            return new TemplateFields(invalid: true);
        }
        $precision = $fields['precisao'] ?? '';
        $type = $fields['tipo_local'] ?? '';
        if (strlen($precision) > 100 || strlen($type) > 100
            || str_contains($precision, '{{') || str_contains($type, '{{')) {
            return new TemplateFields(invalid: true);
        }
        $point = null;
        $hasLat = array_key_exists('latitude', $fields) && $fields['latitude'] !== '';
        $hasLon = array_key_exists('longitude', $fields) && $fields['longitude'] !== '';
        if ($hasLat !== $hasLon) {
            return new TemplateFields(invalid: true);
        }
        if ($hasLat) {
            try {
                $point = Point::fromDecimals($fields['latitude'], $fields['longitude']);
            } catch (InvalidArgumentException) {
                return new TemplateFields(invalid: true);
            }
        }
        return new TemplateFields($requested, $point, $precision, $type);
    }

    /** Split top-level pipes only, respecting nested {{...}} and [[...]]. */
    private function splitInvocation(string $text, int $start): ?array {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $link = 0;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
            $two = substr($text, $i, 2);
            if ($two === '{{') {
                $depth++;
                if ($depth > 1) {
                    $buffer .= $two;
                }
                $i++;
            } elseif ($two === '}}') {
                $depth--;
                if ($depth < 0) {
                    return null;
                }
                if ($depth === 0) {
                    $parts[] = $buffer;
                    return $parts;
                }
                $buffer .= $two;
                $i++;
            } elseif ($two === '[[') {
                $link++;
                $buffer .= $two;
                $i++;
            } elseif ($two === ']]') {
                $link = max(0, $link - 1);
                $buffer .= $two;
                $i++;
            } elseif ($text[$i] === '|' && $depth === 1 && $link === 0) {
                $parts[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $text[$i];
            }
        }
        return null;
    }
}
