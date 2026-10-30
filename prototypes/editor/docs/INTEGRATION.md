# Componente de geolocalização — integração pendente

Este trabalho é isolado e NÃO modifica os manifestos compartilhados, formulários, produção, GeoJSON ou schema.

## Page Forms
A classe PFGeoLocationInput estende PFFormInput; o hook PageForms::FormPrinterSetup registra o input type `mapa-geolocalizacao` via `registerInputType`.
`extension.fragment.json` é **fragmento de referência**, não um extension.json instalável. Seu empacotamento precisa adequar paths de classes e módulos ResourceLoader.

O campo JSON é transporte provisório; não é persistência validada. A frente responsável pelo armazenamento deve validar rigorosamente latitude, longitude, precisao, tipo_local, exibir_no_mapa e política editorial no servidor. A confirmação explícita é uma ação, não um nome novo de coluna.

## Privacidade
Sem geolocalização automática, IP, terceiros, analytics ou armazenamento de rascunhos. GPS somente por gesto explícito; resposta descartada ao cancelar. Não assumir que GPS do autor seja local do assunto. O botão de desligar não apaga silenciosamente registros existentes.

## Dependências
- Interface: AGPL-3.0-or-later, mesma licença do repositório.
- Page Forms: GPL-2.0-or-later; a versão antiga disponibilizada no host carrega Leaflet 1.1.0 via unpkg, não reutilizado.
- Leaflet 1.9.4: BSD-2-Clause; pode ser hospedado localmente com avisos de licença preservados. Ainda não incorporado.
- Playwright: Apache-2.0, desenvolvimento.

A demonstração é **sem tiles/Leaflet** e usa fixture estática, portanto a busca e edição de coordenadas são operacionais, mas mapa clicável e arraste ainda exigem Leaflet empacotado. A integração com tile service próprio precisa conferir a compatibilidade XYZ e atribuição de dados antes da ativação.

## Executar
`cd geo-initiative/editor && npm install && npx playwright install chromium firefox && npm test`.
O `playwright.config.cjs` sobe servidor HTTP somente em 127.0.0.1. Em produção exigir HTTPS.
