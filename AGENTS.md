# Instruções de contribuição

- Escreva código, nomes de testes e documentação em português quando apropriado; preserve os identificadores técnicos do MediaWiki.
- Mantenha a extensão pequena: fonte primária de coordenadas é GeoData, direitos são do MediaWiki e aprovação editorial é independente.
- Não publique localizações apenas porque o artigo possui coordenadas. Exclusão, alteração, redirecionamento ou revisão oculta devem retirar o ponto do feed.
- Testes unitários em `tests/phpunit/unit/` verificam classes sem banco. Integração em `tests/phpunit/integration/` usa MediaWiki e GeoData reais. O contrato HTTP é testado separadamente.
- Use PHP e os padrões de extensão MediaWiki; não acrescente scripts Python ou tabelas/filas sem necessidade comprovada.
- O formulário pertence ao repositório MapaMovSaúde. Não apresente HTML demonstrativo como interface funcional desta extensão.
- Documente somente instalação, uso e decisões duráveis; descreva detalhes transitórios no PR, não no README.
- Mudanças visuais no formulário devem atualizar a captura automatizada no repositório MapaMovSaúde.
