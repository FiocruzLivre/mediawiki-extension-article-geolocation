# ArticleGeolocation

**Mapas colaborativos com localização aprovada por pessoas, não apenas por algoritmos.**

ArticleGeolocation transforma coordenadas presentes em artigos MediaWiki em um feed GeoJSON moderado, adequado a mapas de iniciativas, territórios e organizações. O editor informa um local; a equipe editorial decide se ele deve aparecer no mapa público.

- **Governança:** a inclusão depende de aprovação editorial independente.
- **Correção:** mudanças ou remoções de localização invalidam a aprovação anterior.
- **Integração:** usa o GeoData e os recursos existentes do MediaWiki, sem criar banco ou sistema de publicações paralelo.
- **Interoperabilidade:** fornece GeoJSON para páginas, mapas e aplicações externas.

## Integração com formulários

O formulário é disponibilizado pelo **Page Forms do site**, não por esta extensão. O [MapaMovSaúde](https://github.com/FiocruzLivre/mapa-mov-saude) oferece localização opcional por mapa Leaflet. A extensão aceita o campo \`localizacao\` (latitude, longitude) e também os antigos campos separados para compatibilidade.

## Começar

Requer MediaWiki 1.46+, PHP 8.3+ e a extensão GeoData. Instale este repositório como `extensions/ArticleGeolocation` e adicione ao `LocalSettings.php`:

`wfLoadExtension( 'ArticleGeolocation' );`

Um editor pode solicitar a publicação usando a predefinição `Geolocalização do artigo` da wiki; aprovação exige o direito `editinterface` para editar `MediaWiki:ArticleGeolocation-aprovacoes`. O feed aprovado é disponibilizado em `Special:ArticleGeolocationFeed`. **O GeoData possui API própria: não registre localização confidencial esperando protegê-la apenas pela aprovação deste feed.**

## Comunidade e suporte

Problemas, melhorias e contribuições podem ser registrados em [issues](https://github.com/FiocruzLivre/mediawiki-extension-article-geolocation/issues) e enviados por pull request. Para implantação ou suporte profissional, consulte os mantenedores sobre a disponibilidade de atendimento; esse serviço é independente do uso gratuito do software livre.

Diretrizes para contribuição e testes estão em [AGENTS.md](AGENTS.md).
