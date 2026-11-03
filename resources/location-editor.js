( function () {
    'use strict';

    const mapElement = document.getElementById( 'articlegeo-location-map' );
    if ( !mapElement || typeof L === 'undefined' ) {
        return;
    }

    const latitude = document.getElementById( 'articlegeo-latitude' );
    const longitude = document.getElementById( 'articlegeo-longitude' );
    const search = document.getElementById( 'articlegeo-place-search' );
    const find = document.getElementById( 'articlegeo-find-place' );
    const status = document.getElementById( 'articlegeo-search-status' );
    const results = document.getElementById( 'articlegeo-search-results' );
    const initialLat = Number( mapElement.dataset.lat );
    const initialLon = Number( mapElement.dataset.lon );
    if ( !Number.isFinite( initialLat ) || !Number.isFinite( initialLon ) ) {
        return;
    }

    const map = L.map( mapElement ).setView( [ initialLat, initialLon ], 11 );
    L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
    } ).addTo( map );
    const icon = L.divIcon( {
        className: 'articlegeo-location-pin',
        iconSize: [ 32, 42 ],
        iconAnchor: [ 16, 39 ]
    } );
    const marker = L.marker( [ initialLat, initialLon ], {
        draggable: true,
        icon: icon
    } ).addTo( map );

    function update( lat, lon, moveMap ) {
        if ( !Number.isFinite( lat ) || !Number.isFinite( lon ) ||
            lat < -90 || lat > 90 || lon < -180 || lon > 180 ) {
            return;
        }
        latitude.value = lat.toFixed( 8 );
        longitude.value = lon.toFixed( 8 );
        marker.setLatLng( [ lat, lon ] );
        if ( moveMap ) {
            map.setView( [ lat, lon ], Math.max( map.getZoom(), 12 ) );
        }
    }

    map.on( 'click', ( event ) => {
        update( event.latlng.lat, event.latlng.lng, false );
    } );
    marker.on( 'dragend', () => {
        const point = marker.getLatLng();
        update( point.lat, point.lng, false );
    } );
    for ( const input of [ latitude, longitude ] ) {
        input.addEventListener( 'change', () => {
            update( Number( latitude.value ), Number( longitude.value ), true );
        } );
    }

    let lastSearch = 0;
    async function searchPlace() {
        const query = search.value.trim();
        if ( query.length < 3 ) {
            status.textContent = 'Digite pelo menos três caracteres.';
            return;
        }
        // Explicit search only; no autocomplete or unbounded requests to Nominatim.
        if ( Date.now() - lastSearch < 1000 ) {
            return;
        }
        lastSearch = Date.now();
        find.disabled = true;
        status.textContent = 'Pesquisando…';
        results.replaceChildren();
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' +
                encodeURIComponent( query );
            const response = await fetch( url, { headers: { Accept: 'application/json' } } );
            if ( !response.ok ) {
                throw new Error( 'Falha de pesquisa' );
            }
            const places = await response.json();
            status.textContent = places.length ? 'Selecione um dos resultados:' :
                'Nenhum resultado. Experimente incluir o município ou estado.';
            for ( const place of places ) {
                const lat = Number( place.lat );
                const lon = Number( place.lon );
                if ( !Number.isFinite( lat ) || !Number.isFinite( lon ) ) {
                    continue;
                }
                const option = document.createElement( 'button' );
                option.type = 'button';
                option.className = 'articlegeo-location-result';
                option.textContent = place.display_name;
                option.addEventListener( 'click', () => {
                    update( lat, lon, true );
                    results.replaceChildren();
                    status.textContent = 'Ponto selecionado. Confira o marcador antes de salvar.';
                } );
                results.append( option );
            }
        } catch ( error ) {
            status.textContent = 'Não foi possível pesquisar agora. Você ainda pode marcar no mapa.';
        } finally {
            find.disabled = false;
        }
    }
    find.addEventListener( 'click', searchPlace );
    search.addEventListener( 'keydown', ( event ) => {
        if ( event.key === 'Enter' ) {
            event.preventDefault();
            searchPlace();
        }
    } );
    // Leaflet needs a size refresh after a MediaWiki skin finishes laying out the page.
    requestAnimationFrame( () => map.invalidateSize() );
}() );
