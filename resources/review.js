( function () {
    'use strict';
    const form = document.getElementById( 'articlegeo-bulk-form' );
    if ( !form ) {
        return;
    }
    const items = Array.from( form.querySelectorAll( '.articlegeo-bulk-item' ) );
    const selectPage = document.getElementById( 'articlegeo-select-page' );
    const selectAll = document.getElementById( 'articlegeo-all-pending' );
    const counter = document.getElementById( 'articlegeo-selected-counter' );
    const submit = document.getElementById( 'articlegeo-review-bulk' );
    let lastIndex = null;

    function refresh() {
        const checked = items.filter( ( item ) => item.checked ).length;
        if ( selectPage ) {
            selectPage.checked = items.length > 0 && checked === items.length;
            selectPage.indeterminate = checked > 0 && checked < items.length;
        }
        const bulk = !!( selectAll && selectAll.checked );
        for ( const item of items ) {
            item.disabled = bulk;
        }
        counter.textContent = bulk ? 'Todas as pendentes selecionadas' :
            checked + ' selecionadas nesta página';
        submit.disabled = !bulk && checked === 0;
    }

    items.forEach( ( item, index ) => {
        item.addEventListener( 'click', ( event ) => {
            if ( event.shiftKey && lastIndex !== null ) {
                const first = Math.min( lastIndex, index );
                const last = Math.max( lastIndex, index );
                for ( let i = first; i <= last; i++ ) {
                    items[ i ].checked = item.checked;
                }
            }
            lastIndex = index;
            refresh();
        } );
    } );
    if ( selectPage ) {
        selectPage.addEventListener( 'change', () => {
            for ( const item of items ) {
                item.checked = selectPage.checked;
            }
            refresh();
        } );
    }
    if ( selectAll ) {
        selectAll.addEventListener( 'change', refresh );
    }
    refresh();
}() );
