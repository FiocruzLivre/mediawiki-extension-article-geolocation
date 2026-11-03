# ArticleGeolocation

**Mapas colaborativos com localização aprovada por pessoas, não apenas por algoritmos.**

ArticleGeolocation transforma coordenadas presentes em artigos MediaWiki em um feed GeoJSON moderado, adequado a mapas de iniciativas, territórios e organizações. O editor informa um local; a equipe editorial decide se ele deve aparecer no mapa público.

- **Governança:** a inclusão depende de aprovação editorial independente.
- **Correção:** mudanças ou remoções de localização invalidam a aprovação anterior.
- **Integração:** usa o GeoData e os recursos existentes do MediaWiki, sem criar banco ou sistema de publicações paralelo.
- **Interoperabilidade:** fornece GeoJSON para páginas, mapas e aplicações externas.

## Integração com formulários

O formulário é disponibilizado pelo **Page Forms do site**, não por esta extensão. O [MapaMovSaúde](https://github.com/FiocruzLivre/mapa-mov-saude) oferece localização opcional por mapa Leaflet. A extensão aceita o campo **localizacao** (latitude, longitude) e também os antigos campos separados para compatibilidade.

## Começar

Requer a extensão GeoData e versões de MediaWiki e PHP compatíveis com os requisitos declarados em `extension.json` e nos testes de integração do projeto. Instale este repositório como `extensions/ArticleGeolocation` e adicione ao `LocalSettings.php`:

`wfLoadExtension( 'ArticleGeolocation' );`

Um editor pode solicitar a publicação usando a predefinição `Geolocalização do artigo` da wiki; a lista de aprovações utiliza linhas `ID|latitude|longitude` na página de controle `MediaWiki:ArticleGeolocation-aprovacoes`. A página `Especial:ArticleGeolocationReview` permite que administradores com o direito específico `articlegeolocation-review` consultem as histórias geolocalizadas, abram a história, editem a localização no formulário Page Forms e aprovem ou revoguem o ponto. A extensão concede esse direito ao grupo `sysop` por padrão, sem exigir `editinterface`. A aprovação é ligada às coordenadas atuais e expira se forem alteradas. Não é necessário informar o tipo ou a precisão da localização.

### Fila e avisos

A fila permite aprovar várias localizações em uma única revisão do MediaWiki: marque itens individualmente, use **Shift** para selecionar um intervalo, selecione todas da página ou todas as pendentes (inclusive de outras páginas). O formulário mostra uma etapa de **confirmação com a quantidade exata** antes de qualquer gravação. A versão do registro e as coordenadas selecionadas são revalidadas no servidor, e, se houver conflito, nenhuma aprovação parcial é salva. Itens já aprovados não são regravados.

A página `Especial:ArticleGeolocationReview` mostra contadores e filtros de **Pendentes**, **Aprovadas** e **Todas**, além da revisão individual com mapa e ações de aprovação e revogação. O acesso exige apenas `articlegeolocation-review`, concedido ao grupo `sysop` por padrão.

Quando a extensão Echo estiver instalada e ativada, a criação ou alteração de uma localização solicitada gera um aviso interno para moderadores. Edições comuns da mesma história não geram novos avisos. Cada moderador pode ativar o **e-mail** nas preferências de notificações do MediaWiki; essa opção começa desligada para evitar mensagens excessivas. A fila funciona mesmo sem Echo. O envio real de e-mail depende de um servidor SMTP configurado no MediaWiki.

**Antes de produção:** validar visualmente o fluxo administrativo e registrar a captura no PR sem versioná-la. A permissão específica `articlegeolocation-review` evita distribuir desnecessariamente o direito amplo `editinterface`. O feed aprovado é disponibilizado em `Special:ArticleGeolocationFeed`. **O GeoData possui API própria: não registre localização confidencial esperando protegê-la apenas pela aprovação deste feed.**

### Edição visual da localização

Quem possui a permissão `articlegeolocation-review` pode abrir **Editar localização** na aba da história ou na revisão individual. `Especial:ArticleGeolocationEdit/<ID>` oferece mapa Leaflet clicável, marcador arrastável e pesquisa explícita por local (OpenStreetMap/Nominatim), sem modificar o VisualEditor usado para escrever histórias. A edição altera exclusivamente as coordenadas da predefinição existente, preservando o texto e demais parâmetros; revisão concorrente impede a gravação. Uma localização modificada perde a aprovação editorial anterior até ser novamente conferida. A tela exige Page Forms para disponibilizar os recursos Leaflet, mas **não requer VEForAll nem ativa o formulário público de criação de histórias**. Essa operação é reservada aos moderadores autorizados mesmo quando a edição geral de artigos está suspensa; usuários comuns não recebem a ação.

### Prévia e mapa público

Use `Especial:ArticleGeolocationPreview` para conferir **todos** os pontos solicitados, incluindo os ainda pendentes. Essa página exige a permissão `articlegeolocation-review` e nunca publica os pontos pendentes no feed aberto. A fila em `Especial:ArticleGeolocationReview` é paginada (50 histórias por página), sem limite fixo de 500 candidatos.

Para exibir o mapa de **somente pontos aprovados**, adicione `<articlegeolocationmap />` ao wikitexto de qualquer página. O componente utiliza Kartographer e não permite cache do resultado, para que revogações e mudanças de localização sejam refletidas na próxima visualização. Sem aprovações, exibe uma mensagem em vez de um mapa vazio.

A substituição da home requer apenas trocar `{{Predefinição:Mapa_Brasil}}` pelo componente `<articlegeolocationmap />` no mesmo contêiner `#mapa-interativo`. Antes dessa alteração, valide a renderização em uma página temporária, salve a revisão anterior e compare pontos, links e comportamento responsivo. Após validar a nova home e o procedimento de reversão, a página temporária pode ser excluída. Não remova a predefinição legada durante a transição.

## Migração dos marcadores históricos

A importação utiliza um inventário JSON auditado **mantido fora do repositório**. Execute inicialmente, em homologação, `php maintenance/run.php /caminho/ArticleGeolocation/maintenance/importLegacyPins.php --manifest=/caminho/seguro/classification.json`. Sem `--apply`, a operação é somente leitura e devolve JSON com checksum SHA-256, páginas elegíveis, conflitos, registros ignorados e resultados por artigo. As coordenadas históricas são normalizadas para oito casas decimais.

Após revisar o relatório, conferir backup e instalar a predefinição, a aplicação explícita requer `--apply --confirm-sha256=HASH_DO_RELATORIO --editor=USUARIO`. O script recusa revisões alteradas, coordenadas já existentes e artigos não elegíveis; execuções repetidas não duplicam dados. As localizações importadas ficam **pendentes**, nunca aprovadas automaticamente, e a importação em lote não envia notificações individuais. Nenhum arquivo de inventário nem relatório contendo dados reais deve ser versionado. A produção não deve receber a migração antes da revisão editorial e do plano de rollback.

## Comunidade e suporte

Problemas, melhorias e contribuições podem ser registrados em [issues](https://github.com/FiocruzLivre/mediawiki-extension-article-geolocation/issues) e enviados por pull request. Para implantação ou suporte profissional, consulte os mantenedores sobre a disponibilidade de atendimento; esse serviço é independente do uso gratuito do software livre.

Diretrizes para contribuição e testes estão em [AGENTS.md](AGENTS.md).
