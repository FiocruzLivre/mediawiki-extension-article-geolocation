<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use DomainException;
use InvalidArgumentException;

/**
 * Registros deliberadamente mantidos numa página MediaWiki protegida por editinterface.
 * Formato atual: ID|latitude|longitude|revisão. Os registros legados com três
 * campos são reconhecidos para migração reversível, sem aprovar novos textos.
 */
final class ApprovalRegistry {
    /** @return array<int,string> ID do artigo => impressão da localização aprovada */
    public function parse(string $text): array {
        $approved = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) as $index => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (!in_array(count($parts), [3, 4], true) ||
                !preg_match('/^[1-9][0-9]{0,9}$/D', $parts[0]) ||
                (count($parts) === 4 && !preg_match('/^[1-9][0-9]{0,9}$/D', $parts[3]))) {
                throw new DomainException('Registro de aprovação inválido, linha ' . ($index + 1));
            }
            $id = (int)$parts[0];
            if (array_key_exists($id, $approved)) {
                throw new DomainException('Aprovação duplicada para artigo ' . $id);
            }
            try {
                $point = Point::fromDecimals($parts[1], $parts[2]);
            } catch (InvalidArgumentException $e) {
                throw new DomainException('Coordenadas inválidas na linha ' . ($index + 1), 0, $e);
            }
            $approved[$id] = Article::fingerprintFor(
                $id, $point, isset($parts[3]) ? (int)$parts[3] : null
            );
            if (count($approved) > 10000) {
                throw new DomainException('Limite de aprovações excedido');
            }
        }
        ksort($approved, SORT_NUMERIC);
        return $approved;
    }
}
