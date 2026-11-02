<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use InvalidArgumentException;

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
        // Só aceitar predefinição literal no nível principal do artigo.
        $prefix = substr($text, 0, $start);
        preg_match_all('/\{\{|\}\}/', $prefix, $braces);
        $depth = 0;
        foreach ($braces[0] as $brace) {
            $depth += $brace === '{{' ? 1 : -1;
            if ($depth < 0) {
                return new TemplateFields(invalid: true);
            }
        }
        if ($depth !== 0) {
            return new TemplateFields(invalid: true);
        }
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
            if (!in_array($key, ['latitude', 'longitude', 'localizacao', 'precisao',
                'tipo_local', 'exibir_no_mapa'], true) || array_key_exists($key, $fields)) {
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
        $hasMap = isset($fields['localizacao']) && $fields['localizacao'] !== '';
        $hasLat = isset($fields['latitude']) && $fields['latitude'] !== '';
        $hasLon = isset($fields['longitude']) && $fields['longitude'] !== '';
        if (($hasMap && ($hasLat || $hasLon)) || ($hasLat !== $hasLon)) {
            return new TemplateFields(invalid: true);
        }
        if ($hasMap || $hasLat) {
            $coordinates = $hasMap
                ? array_map('trim', explode(',', $fields['localizacao']))
                : [$fields['latitude'], $fields['longitude']];
            if (count($coordinates) !== 2) {
                return new TemplateFields(invalid: true);
            }
            try {
                $point = Point::fromDecimals($coordinates[0], $coordinates[1]);
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
