<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use RuntimeException;

/** Update only the coordinates of one literal geolocation template. Never rewrite the article body. */
final class LocationWikitextEditor {
    public function replace(string $wikitext, Point $expected, Point $replacement): string {
        $reader = new TemplateFieldsReader();
        $original = $reader->parse($wikitext);
        if ($original->invalid || !$original->requested || $original->point === null ||
            !$original->point->equals($expected)) {
            throw new RuntimeException('A localização foi modificada. Reabra a história antes de salvar.');
        }
        if ($expected->equals($replacement)) {
            return $wikitext;
        }
        // Refuse complex or nested invocations rather than risk changing unrelated prose.
        $pattern = '/\{\{\s*Geolocaliza[cç][aã]o[ _]+do[ _]+artigo(?=[|}])[^{}]*\}\}/iu';
        if (preg_match_all($pattern, $wikitext, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('Esta localização precisa de revisão técnica antes da edição visual.');
        }
        [$originalTemplate, $offset] = $matches[0][0];
        [$lat, $lon] = $replacement->canonical();
        $template = $originalTemplate;
        if (preg_match('/\|\s*localizacao\s*=/iu', $template)) {
            $template = preg_replace('/(\|\s*localizacao\s*=)\s*[^|}]+/iu', '$1' . $lat . ', ' . $lon, $template, 1);
        } elseif (preg_match('/\|\s*latitude\s*=/iu', $template) &&
            preg_match('/\|\s*longitude\s*=/iu', $template)) {
            $template = preg_replace('/(\|\s*latitude\s*=)\s*[^|}]+/iu', '$1' . $lat, $template, 1);
            $template = preg_replace('/(\|\s*longitude\s*=)\s*[^|}]+/iu', '$1' . $lon, $template, 1);
        } else {
            throw new RuntimeException('Não foi possível identificar os campos da localização.');
        }
        $updated = substr_replace($wikitext, $template, $offset, strlen($originalTemplate));
        $parsed = $reader->parse($updated);
        if ($parsed->invalid || !$parsed->requested || !$parsed->point?->equals($replacement) ||
            $parsed->precision !== $original->precision ||
            $parsed->locationType !== $original->locationType) {
            throw new RuntimeException('A validação da localização falhou. Nenhuma edição foi salva.');
        }
        return $updated;
    }
}
