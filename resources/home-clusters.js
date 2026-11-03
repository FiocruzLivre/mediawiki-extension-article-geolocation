/* global L */
( function () {
    'use strict';

    const handled = new WeakSet();

    function articleLink( marker ) {
        const title = marker.feature && marker.feature.properties && marker.feature.properties.title;
        if ( typeof title !== 'string' ) {
            return null;
        }
        const content = document.createElement( 'template' );
        content.innerHTML = title;
        const link = content.content.querySelector( 'a[href]' );
        if ( !link ) {
            return null;
        }
        let url;
        try {
            url = new URL( link.getAttribute( 'href' ), window.location.href );
        } catch ( error ) {
            return null;
        }
        if ( url.origin !== window.location.origin || !/^https?:$/.test( url.protocol ) ) {
            return null;
        }
        const label = link.textContent.trim();
        return label ? { label: label, href: url.href } : null;
    }

    function addAccessibleList( container, markers ) {
        const articles = markers.map( articleLink ).filter( Boolean );
        if ( articles.length === 0 || container.querySelector( '.articlegeo-map-list' ) ) {
            return;
        }
        articles.sort( ( a, b ) => a.label.localeCompare( b.label, 'pt-BR' ) );

        const details = document.createElement( 'details' );
        details.className = 'articlegeo-map-list';
        const summary = document.createElement( 'summary' );
        summary.textContent = 'Consultar as ' + articles.length + ' histórias em lista';
        const list = document.createElement( 'ul' );
        for ( const article of articles ) {
            const item = document.createElement( 'li' );
            const link = document.createElement( 'a' );
            link.href = article.href;
            link.textContent = article.label;
            item.appendChild( link );
            list.appendChild( item );
        }
        details.append( summary, list );
        container.appendChild( details );
    }

    function clusterMap( map ) {
        const mapElement = map.getContainer();
        const container = mapElement.closest( '#mapa-interativo' );
        if ( !container || handled.has( map ) || typeof L.markerClusterGroup !== 'function' ) {
            return;
        }
        handled.add( map );
        map.doWhenReady( () => {
            const markers = [];
            const sources = [];
            for ( const layer of map.dataLayers || [] ) {
                layer.eachLayer( ( child ) => {
                    if ( child instanceof L.Marker && child.feature &&
                        child.feature.geometry && child.feature.geometry.type === 'Point' ) {
                        markers.push( child );
                        sources.push( [ layer, child ] );
                    }
                } );
            }
            if ( markers.length < 2 ) {
                return;
            }
            const cluster = L.markerClusterGroup( {
                maxClusterRadius: 55,
                showCoverageOnHover: false,
                zoomToBoundsOnClick: true,
                spiderfyOnMaxZoom: true,
                animate: !window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
            } );
            // Move existing Kartographer markers, preserving their popups and article links.
            for ( const [ layer, marker ] of sources ) {
                layer.removeLayer( marker );
            }
            cluster.addLayers( markers );
            map.addLayer( cluster );
            const help = document.createElement( 'p' );
            help.className = 'articlegeo-map-help';
            help.textContent = 'Clique nos círculos numerados para aproximar o mapa. ' +
                'Se houver histórias no mesmo ponto, clique novamente para separá-las.';
            container.insertBefore( help, container.firstChild );
            addAccessibleList( container, markers );
        } );
    }

    mw.hook( 'wikipage.maps' ).add( ( maps, isFullScreen ) => {
        if ( !isFullScreen ) {
            maps.forEach( clusterMap );
        }
    } );
}() );
