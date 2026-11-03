const assert = require( 'node:assert/strict' );
const fs = require( 'node:fs' );
const vm = require( 'node:vm' );
const { test } = require( 'node:test' );

class Element {
    constructor( tag = 'div' ) {
        this.tag = tag;
        this.children = [];
        this.className = '';
        this.href = '';
        this.textContent = '';
        this.firstChild = null;
    }
    appendChild( child ) { this.children.push( child ); this.firstChild = this.children[ 0 ]; }
    append( ...items ) { items.forEach( ( item ) => this.appendChild( item ) ); }
    insertBefore( item ) { this.children.unshift( item ); this.firstChild = item; }
    querySelector( selector ) {
        return this.children.find( ( child ) => selector === '.articlegeo-map-list' &&
            child.className === 'articlegeo-map-list' ) || null;
    }
}

function runWithMarkers( count, isHome = true ) {
    let handler;
    let cluster;
    const container = new Element();
    const markers = Array.from( { length: count }, ( _, i ) => new Marker( i ) );
    const source = {
        eachLayer: ( visit ) => markers.slice().forEach( visit ),
        removeLayer: ( marker ) => { source.removed.push( marker ); },
        removed: []
    };
    const mapElement = {
        closest: ( selector ) => selector === '#mapa-interativo' && isHome ? container : null
    };
    const map = {
        getContainer: () => mapElement,
        dataLayers: [ source ],
        doWhenReady: ( callback ) => callback(),
        addLayer: ( layer ) => { cluster = layer; }
    };
    const context = {
        WeakSet,
        URL,
        window: { location: { href: 'https://mapa.example/wiki', origin: 'https://mapa.example' },
            matchMedia: () => ( { matches: false } ) },
        document: {
            createElement: ( tag ) => tag === 'template' ? new Template() : new Element( tag )
        },
        L: { Marker, markerClusterGroup: ( options ) => ( {
            options,
            addLayers( items ) { this.markers = items; }
        } ) },
        mw: { hook: () => ( { add: ( callback ) => { handler = callback; } } ) }
    };
    vm.runInNewContext( fs.readFileSync( 'resources/home-clusters.js', 'utf8' ), context );
    handler( [ map ], false );
    handler( [ map ], false );
    return { markers, source, cluster, container };
}

class Marker {
    constructor( id ) {
        this.feature = { geometry: { type: 'Point' }, properties: {
            title: '<a href="/index.php/Historia_' + id + '">História ' + id + '</a>'
        } };
        this.popup = { id };
    }
}
class Template {
    constructor() { this.content = { querySelector: () => this.anchor }; }
    set innerHTML( html ) {
        const m = /href="([^"]+)"[^>]*>([^<]+)</.exec( html );
        this.anchor = m && { getAttribute: () => m[ 1 ], textContent: m[ 2 ] };
    }
}

test( 'clusters the original markers without losing popups or duplicating the list', () => {
    const { markers, source, cluster, container } = runWithMarkers( 4 );
    assert.equal( source.removed.length, 4 );
    assert.equal( cluster.markers.length, markers.length );
    cluster.markers.forEach( ( marker, i ) => assert.equal( marker, markers[ i ] ) );
    assert.equal( cluster.markers[ 0 ].popup, markers[ 0 ].popup );
    assert.equal( cluster.options.zoomToBoundsOnClick, true );
    assert.equal( cluster.options.spiderfyOnMaxZoom, true );
    assert.equal( container.children.length, 2 );
    assert.equal( container.children[ 1 ].children[ 1 ].children.length, 4 );
} );

test( 'does not touch unrelated maps or single markers', () => {
    assert.equal( runWithMarkers( 3, false ).cluster, undefined );
    assert.equal( runWithMarkers( 1 ).cluster, undefined );
} );
