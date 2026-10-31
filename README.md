# ArticleGeolocation

Extensão MediaWiki para publicar um GeoJSON de pontos geográficos **aprovados editorialmente**, usando a extensão GeoData como fonte de coordenadas. A edição dos artigos permanece a cargo de Page Forms e dos editores nativos do MediaWiki.

## Instalação

Requer MediaWiki 1.46+, PHP 8.3+ e GeoData instalado. Coloque o repositório em `extensions/ArticleGeolocation` e acrescente `wfLoadExtension( 'ArticleGeolocation' );` à configuração da wiki. Teste primeiro em uma instalação descartável.

A edição de localização de cada artigo usa a predefinição `Geolocalização do artigo`, com `latitude`, `longitude`, `precisao`, `tipo_local` e `exibir_no_mapa=sim`. A predefinição só pode publicar coordenadas da própria página por `#coordinates`. **Solicitação por autor não é aprovação editorial.**

## Aprovação editorial

Um administrador autorizado a editar a interface (`editinterface`) cria a página `MediaWiki:ArticleGeolocation-aprovacoes`. Cada linha tem exatamente:

```text
# id|latitude|longitude|precisao|tipo_local
123|-3.68274|-40.3512|municipio|atuacao
```

É um exemplo fictício. Antes de inserir uma linha, o revisor deve conferir o ID do artigo, o local e os campos efetivamente publicados. Editar essa página é uma decisão editorial independente, com histórico de revisões nativo. Editores comuns de artigos não devem receber `editinterface`. A remoção da linha revoga a aprovação. Um artigo excluído, redirecionado, não público ou com coordenadas alteradas deixa de ser elegível automaticamente.

O feed fica em `Special:ArticleGeolocationFeed`, retornando `application/geo+json`. Consultas são feitas **sem cache** à base primária para não manter uma localização retirada por uma janela de atualização. O sistema não cria tabelas, filas, cópias de coordenadas ou tarefas assíncronas. Uma falha ao ler o registro resulta em HTTP 503 sem conteúdo sensível. Meça o tempo do endpoint antes de habilitá-lo para milhares de artigos.

**Atenção:** GeoData já fornece coordenadas na API própria, independentemente da aprovação deste feed. Nunca registre endereços privados ou localizações sensíveis esperando que o mecanismo de aprovação os torne secretos.

## Testes

Os testes usam PHPUnit por meio do MediaWiki. Execute as suítes `tests/phpunit/unit` e `tests/phpunit/integration` em uma instância de teste com banco descartável e GeoData. Não execute testes que criam páginas no banco de produção.

A configuração, os formulários e o conteúdo específicos do MapaMovSaúde ficam no repositório `FiocruzLivre/mapa-mov-saude`.
