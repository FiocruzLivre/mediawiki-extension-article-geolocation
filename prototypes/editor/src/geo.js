/* MapaMovSaude location picker. No network calls or location requests on initialization. */
(function (root) {
  'use strict';
  const PRECISION = ['municipio', 'aproximada', 'exata'];
  const TYPES = ['sede', 'atuacao', 'evento', 'referencia'];
  const finite = (value, min, max) => value !== '' && value !== null && value !== undefined &&
    Number.isFinite(Number(value)) && Number(value) >= min && Number(value) <= max;
  function normalized(source) {
    if (!source || !finite(source.latitude, -90, 90) || !finite(source.longitude, -180, 180)) return null;
    if (!PRECISION.includes(source.precisao) || !TYPES.includes(source.tipo_local)) return null;
    return {
      latitude: Number(source.latitude), longitude: Number(source.longitude),
      precisao: source.precisao, tipo_local: source.tipo_local,
      exibir_no_mapa: source.exibir_no_mapa === true
    };
  }
  function init(host, config = {}) {
    if (host.dataset.geoInitialized) return;
    host.dataset.geoInitialized = '1';
    const get = (name) => host.querySelector('[data-geo="' + name + '"]');
    const enable = get('enable'), panel = get('panel'), status = get('status');
    const confirm = get('confirm'), cancel = get('cancel');
    const lat = get('latitude'), lon = get('longitude');
    const precision = get('precisao'), type = get('tipo_local'), publish = get('exibir_no_mapa');
    const consent = get('consent'), query = get('search'), results = get('results');
    const useGps = get('gps'), mapNode = get('map'), mapHint = get('map-hint');
    const snapshot = get('snapshot');
    const initial = normalized(config.initial);
    let committed = initial, draft = initial ? { ...initial } : null, map = null, marker = null;
    let requestId = 0, activeGps = 0;
    const say = (message) => { status.textContent = message; };
    function clearResults() { results.replaceChildren(); }
    function updateDraft(latitude, longitude, message) {
      if (!finite(latitude, -90, 90) || !finite(longitude, -180, 180)) {
        say('Coordenadas inválidas. Verifique latitude e longitude.'); return false;
      }
      draft = {
        latitude: Number(latitude), longitude: Number(longitude),
        precisao: precision.value, tipo_local: type.value,
        exibir_no_mapa: publish.checked
      };
      lat.value = draft.latitude; lon.value = draft.longitude;
      consent.checked = false;
      if (marker) marker.setLatLng([draft.latitude, draft.longitude]);
      if (map) map.setView([draft.latitude, draft.longitude], Math.max(map.getZoom(), 11));
      say(message || 'Local selecionado. Revise e confirme antes de publicar.');
      return true;
    }
    function setFields(point) {
      lat.value = point ? point.latitude : ''; lon.value = point ? point.longitude : '';
      precision.value = point ? point.precisao : 'aproximada';
      type.value = point ? point.tipo_local : 'atuacao';
      publish.checked = point ? point.exibir_no_mapa : false;
      consent.checked = false;
    }
    function notify() {
      const detail = committed ? { ...committed } : null;
      snapshot.textContent = committed
        ? 'Local confirmado: ' + committed.latitude + ', ' + committed.longitude +
          '; precisão ' + committed.precisao + '; exibição ' + (committed.exibir_no_mapa ? 'sim' : 'não')
        : 'Nenhuma localização confirmada.';
      host.dispatchEvent(new CustomEvent('geo:confirmed', { bubbles: true, detail }));
      if (typeof config.onConfirm === 'function') config.onConfirm(detail);
    }
    function ensureMap() {
      if (map || !root.L || !mapNode) {
        if (!root.L) mapHint.textContent = 'Mapa visual indisponível. Busque um local ou digite coordenadas.';
        return;
      }
      map = root.L.map(mapNode, { attributionControl: true }).setView(
        draft ? [draft.latitude, draft.longitude] : [-14.2, -51.9], draft ? 11 : 4
      );
      const tiles = config.tileUrl || null;
      // Never contact a third-party tile server without an explicitly configured first-party URL.
      if (tiles && /^\/(?!\/)/.test(tiles)) {
        root.L.tileLayer(tiles, { maxZoom: 18, attribution: config.attribution || 'Mapa' }).addTo(map);
      } else {
        mapNode.classList.add('geo-no-tiles');
        mapHint.textContent = 'Mapa sem camada de ruas. A busca e as coordenadas continuam disponíveis.';
      }
      marker = root.L.marker(draft ? [draft.latitude, draft.longitude] : [-14.2, -51.9], {
        draggable: true, keyboard: true, autoPan: true
      }).addTo(map);
      marker.on('dragend', () => {
        const p = marker.getLatLng();
        updateDraft(p.lat, p.lng, 'Marcador movido. Confirme a nova localização.');
      });
      map.on('click', (event) => updateDraft(event.latlng.lat, event.latlng.lng));
      setTimeout(() => map.invalidateSize(), 0);
    }
    function open() {
      panel.hidden = false;
      draft = committed ? { ...committed } : null;
      setFields(draft);
      say('Escolha o local do assunto do artigo. Nada será publicado sem confirmação.');
      ensureMap();
      if (draft && marker) marker.setLatLng([draft.latitude, draft.longitude]);
    }
    function close() {
      requestId++; activeGps = 0;
      draft = committed ? { ...committed } : null;
      setFields(draft);
      clearResults(); panel.hidden = true; enable.checked = !!committed;
      say('Alterações descartadas.');
    }
    enable.checked = !!committed;
    panel.hidden = true;
    setFields(committed);
    notify();
    enable.addEventListener('change', () => {
      if (enable.checked) open();
      else if (committed) {
        // A previously confirmed location is never silently removed.
        enable.checked = true;
        say('Para retirar a localização existente, é necessário o fluxo editorial apropriado.');
      } else close();
    });
    cancel.addEventListener('click', close);
    for (const control of [lat, lon]) control.addEventListener('change', () => {
      updateDraft(lat.value, lon.value, 'Coordenadas ajustadas. Confirme a localização.');
    });
    const locations = (config.locations || []).filter(p =>
      p && typeof p.label === 'string' && finite(p.latitude, -90, 90) && finite(p.longitude, -180, 180)
    );
    function search() {
      clearResults();
      const term = query.value.trim().toLocaleLowerCase('pt-BR');
      if (term.length < 2) { say('Digite pelo menos dois caracteres.'); return; }
      const matches = locations.filter(p => p.label.toLocaleLowerCase('pt-BR').includes(term)).slice(0, 8);
      for (const place of matches) {
        const item = document.createElement('li'), button = document.createElement('button');
        button.type = 'button'; button.textContent = place.label;
        button.addEventListener('click', () => {
          updateDraft(place.latitude, place.longitude, 'Local encontrado: ' + place.label + '. Revise o ponto.');
          clearResults();
        });
        item.appendChild(button); results.appendChild(item);
      }
      say(matches.length ? matches.length + ' locais encontrados.' : 'Nenhum local encontrado. Use coordenadas.');
    }
    get('search-button').addEventListener('click', search);
    query.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); search(); } });
    function gpsError(error) {
      const messages = {
        1: 'Permissão de localização negada. Use a busca ou as coordenadas.',
        2: 'Posição indisponível. Use a busca ou as coordenadas.',
        3: 'Tempo esgotado ao obter a posição. Tente novamente ou use a busca.'
      };
      say(messages[error && error.code] || 'Não foi possível obter a posição do dispositivo.');
    }
    useGps.addEventListener('click', () => {
      if (!root.isSecureContext) { say('Localização do dispositivo exige conexão HTTPS.'); return; }
      if (!navigator.geolocation || typeof navigator.geolocation.getCurrentPosition !== 'function') {
        say('Este navegador não oferece geolocalização.'); return;
      }
      const id = ++requestId; activeGps = id;
      say('Aguardando permissão do dispositivo. A posição ainda não será publicada.');
      navigator.geolocation.getCurrentPosition(position => {
        if (activeGps !== id || panel.hidden) return;
        activeGps = 0;
        const p = position.coords;
        if (!finite(p.latitude, -90, 90) || !finite(p.longitude, -180, 180)) {
          say('O dispositivo retornou coordenadas inválidas.'); return;
        }
        updateDraft(p.latitude, p.longitude,
          p.accuracy > 1000
            ? 'GPS impreciso (margem de ' + Math.round(p.accuracy) + ' m). Revise o ponto antes de confirmar.'
            : 'Posição encontrada (margem de ' + Math.round(p.accuracy) + ' m). Revise antes de confirmar.'
        );
      }, error => {
        if (activeGps !== id || panel.hidden) return;
        activeGps = 0; gpsError(error);
      }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 0 });
    });
    confirm.addEventListener('click', () => {
      const candidate = normalized({
        latitude: lat.value, longitude: lon.value, precisao: precision.value,
        tipo_local: type.value, exibir_no_mapa: publish.checked
      });
      if (!candidate) { say('Informe coordenadas válidas e as opções de localização.'); return; }
      if (!consent.checked) { say('Confirme explicitamente a publicação da localização.'); consent.focus(); return; }
      committed = candidate; draft = { ...candidate };
      requestId++; activeGps = 0; panel.hidden = true; enable.checked = true;
      notify(); say('Localização confirmada. O artigo ainda precisa ser salvo pelo formulário.');
    });
    host.geoPicker = { getValue: () => committed ? { ...committed } : null, open, cancel: close };
  }
  root.MapaGeoPicker = { init, normalized };
})(typeof window === 'undefined' ? globalThis : window);
