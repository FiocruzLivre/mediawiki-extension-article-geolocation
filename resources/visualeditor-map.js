/* Embedded, optional location picker in VisualEditor's own transclusion dialog.
 * Uses the existing transclusion model: never saves the article independently.
 * Only shown for the exact Geolocalização do artigo template.
 */
( function () {
    'use strict';

    function isLocationTemplate( part ) {
        if ( !( part instanceof ve.dm.MWTemplateModel ) ) {
            return false;
        }
        const name = String( part.getTemplateDataQueryTitle() || '' )
            .normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase();
        return /(?:^|:|\/| )geolocalizacao[ _]+do[ _]+artigo$/.test( name );
    }

    function parseCoordinates( value ) {
        const match = /^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/.exec( value );
        if ( !match ) {
            return null;
        }
        const lat = Number( match[ 1 ] );
        const lon = Number( match[ 2 ] );
        return Number.isFinite( lat ) && Number.isFinite( lon ) &&
            lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180 ? [ lat, lon ] : null;
    }

    function attach( page, template, readOnly, dialog ) {
        if ( page.$element.find( '.articlegeo-ve-picker' ).length ) {
            return;
        }

        let location = template.getParameter( 'localizacao' );
        const latLegacy = template.getParameter( 'latitude' );
        const lonLegacy = template.getParameter( 'longitude' );
        const initial = location ? parseCoordinates( location.getValue() ) :
            ( latLegacy && lonLegacy ?
                parseCoordinates( latLegacy.getValue() + ', ' + lonLegacy.getValue() ) : null );
        if ( !initial ) {
            // Do not fabricate coordinates for an invalid or missing point.
            return;
        }

        const $panel = $( '<section>' ).addClass( 'articlegeo-ve-picker' );
        $( '<h3>' ).text( 'Localização da história' ).appendTo( $panel );
        const description = readOnly ?
            'Esta história está em modo somente leitura no VisualEditor. Confira o ponto abaixo ou abra o editor de localização autorizado.' :
            'Clique no mapa ou arraste o marcador. Confirme a predefinição e salve a história para registrar a mudança.';
        $( '<p>' ).text( description ).appendTo( $panel );
        if ( readOnly && Number.isInteger( mw.config.get( 'wgArticleId' ) ) ) {
            const url = mw.util.getUrl( 'Special:ArticleGeolocationEdit/' +
                mw.config.get( 'wgArticleId' ) );
            $( '<a>' ).addClass( 'articlegeo-ve-location-link' )
                .attr( { href: url, target: '_blank', rel: 'noopener' } )
                .text( 'Editar localização no mapa em outra aba' )
                .appendTo( $panel );
        }
        const $map = $( '<div>' ).addClass( 'articlegeo-ve-map' )
            .attr( 'aria-label', 'Mapa para reposicionar a localização da história' );
        const $coordinates = $( '<output>' ).addClass( 'articlegeo-ve-coordinates' )
            .text( initial.map( ( value ) => value.toFixed( 6 ) ).join( ', ' ) );
        const $visibility = $( '<label>' ).addClass( 'articlegeo-ve-visibility' );
        let visibility = template.getParameter( 'exibir_no_mapa' );
        const $checkbox = $( '<input>' ).attr( 'type', 'checkbox' )
            .prop( 'checked', !visibility || visibility.getValue().trim().toLowerCase() !== 'não' &&
                visibility.getValue().trim().toLowerCase() !== 'nao' );
        $visibility.append( $checkbox, document.createTextNode( ' Solicitar exibição no mapa após aprovação editorial' ) );
        const $advanced = $( '<button>' ).attr( 'type', 'button' )
            .addClass( 'articlegeo-ve-advanced-toggle' )
            .text( 'Mostrar parâmetros avançados' );
        $panel.append( $map, $coordinates, $visibility, $advanced );
        dialog.$element.addClass( 'articlegeo-ve-dialog' );
        $advanced.on( 'click', () => {
            const expanded = dialog.$element.toggleClass( 'articlegeo-ve-advanced' )
                .hasClass( 'articlegeo-ve-advanced' );
            $advanced.text( expanded ? 'Ocultar parâmetros avançados' : 'Mostrar parâmetros avançados' );
        } );
        if ( readOnly ) {
            $checkbox.prop( 'disabled', true );
        }
        // Present the map before generic template documentation and avoid redundant instructions.
        page.$element.addClass( 'articlegeo-ve-map-page' ).prepend( $panel );
        page.infoFieldset.$element.hide();

        function syncField( parameter, value ) {
            parameter.setValue( value );
            // Parameter widgets are not guaranteed to observe changes made elsewhere.
            for ( const item of dialog.bookletLayout.getPagesOrdered() ) {
                if ( item instanceof ve.ui.MWParameterPage && item.parameter === parameter &&
                    item.valueInput.getValue() !== value ) {
                    item.valueInput.setValue( value );
                }
            }
        }

        function setValue( lat, lon ) {
            const value = lat.toFixed( 8 ) + ', ' + lon.toFixed( 8 );
            if ( location ) {
                syncField( location, value );
            } else if ( latLegacy && lonLegacy ) {
                syncField( latLegacy, lat.toFixed( 8 ) );
                syncField( lonLegacy, lon.toFixed( 8 ) );
            } else {
                location = new ve.dm.MWParameterModel( template, 'localizacao', value );
                template.addParameter( location );
            }
            $coordinates.text( lat.toFixed( 6 ) + ', ' + lon.toFixed( 6 ) );
        }

        $checkbox.on( 'change', () => {
            if ( !visibility ) {
                visibility = new ve.dm.MWParameterModel(
                    template, 'exibir_no_mapa', $checkbox.prop( 'checked' ) ? 'sim' : 'não'
                );
                template.addParameter( visibility );
            } else {
                syncField( visibility, $checkbox.prop( 'checked' ) ? 'sim' : 'não' );
            }
        } );

        mw.loader.using( 'ext.pageforms.leaflet' ).then( () => {
            if ( !$panel[ 0 ].isConnected ) {
                return;
            }
            const node = $map[ 0 ];
            const observer = new IntersectionObserver( ( entries ) => {
                if ( !entries.some( ( entry ) => entry.isIntersecting ) ) {
                    return;
                }
                observer.disconnect();
                const map = L.map( node ).setView( initial, 10 );
                L.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                } ).addTo( map );
                const icon = L.divIcon( {
                    className: 'articlegeo-ve-pin',
                    iconSize: [ 32, 42 ],
                    iconAnchor: [ 16, 39 ]
                } );
                const marker = L.marker( initial, { icon: icon, draggable: !readOnly } ).addTo( map );
                if ( !readOnly ) {
                    map.on( 'click', ( event ) => {
                        marker.setLatLng( event.latlng );
                        setValue( event.latlng.lat, event.latlng.lng );
                    } );
                    marker.on( 'dragend', () => {
                        const point = marker.getLatLng();
                        setValue( point.lat, point.lng );
                    } );
                }
                const resizeObserver = new ResizeObserver( () => map.invalidateSize() );
                resizeObserver.observe( node );
                const lifecycle = new MutationObserver( () => {
                    if ( !node.isConnected ) {
                        lifecycle.disconnect();
                        resizeObserver.disconnect();
                        map.remove();
                    }
                } );
                lifecycle.observe( document.body, { childList: true, subtree: true } );
                map.invalidateSize();
            } );
            observer.observe( $panel[ 0 ] );
        }, () => {
            $map.text( 'O mapa não está disponível agora. Os dados originais foram preservados.' );
        } );
    }

    function install() {
        if ( !window.ve || !ve.ui || !ve.ui.MWTransclusionDialog ||
            !ve.ui.MWTemplatePage ) {
            return;
        }
        const prototype = ve.ui.MWTransclusionDialog.prototype;
        if ( prototype.articleGeoMapInstalled ) {
            return;
        }
        prototype.articleGeoMapInstalled = true;
        const original = prototype.getPageFromPart;
        prototype.getPageFromPart = function ( part ) {
            const page = original.apply( this, arguments );
            if ( page && page instanceof ve.ui.MWTemplatePage &&
                isLocationTemplate( part ) ) {
                attach( page, part, this.isReadOnly(), this );
            }
            return page;
        };
    }

    mw.hook( 've.activationComplete' ).add( install );
}() );
