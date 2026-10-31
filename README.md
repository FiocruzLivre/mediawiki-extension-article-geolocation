# ArticleGeolocation

Extensão MediaWiki para associar localizações opcionais a artigos e produzir dados GeoJSON para mapas públicos, com integração prevista com Page Forms, GeoData e Kartographer.

## Desenvolvimento

O código funcional ainda está em revisão e **não deve ser instalado em produção**. Os testes PHP devem usar PHPUnit integrado ao MediaWiki em `tests/phpunit/unit/` e `tests/phpunit/integration/`. Os testes de navegador verificam a experiência de escolha de localização.

A configuração e os formulários específicos do Mapa MovSaúde ficam em [mapa-mov-saude](https://github.com/FiocruzLivre/mapa-mov-saude); esta extensão não deve conter a configuração de nenhuma das duas wikis.
