/**
 * FileSmith Editor: settings page tabs, "select all" boxes and live preview.
 */
( function ( window, document ) {
	'use strict';

	var wrap = document.querySelector( '.fsed-settings' );

	if ( ! wrap ) {
		return;
	}

	var tabs = wrap.querySelectorAll( '[data-fsed-tab]' );
	var panels = wrap.querySelectorAll( '[data-fsed-panel]' );
	var STORAGE_KEY = 'fsedSettingsTab';

	/* ---------- Tabs ---------- */

	function readStored() {
		try {
			return window.sessionStorage.getItem( STORAGE_KEY ) || '';
		} catch ( e ) {
			return '';
		}
	}

	function store( name ) {
		try {
			window.sessionStorage.setItem( STORAGE_KEY, name );
		} catch ( e ) {
			// Storage can be blocked. The tab is simply not remembered.
		}
	}

	function hasPanel( name ) {
		return Array.prototype.some.call( panels, function ( panel ) {
			return panel.getAttribute( 'data-fsed-panel' ) === name;
		} );
	}

	function showTab( name ) {
		Array.prototype.forEach.call( tabs, function ( tab ) {
			var active = tab.getAttribute( 'data-fsed-tab' ) === name;
			tab.classList.toggle( 'nav-tab-active', active );
			tab.setAttribute( 'aria-current', active ? 'page' : 'false' );
		} );

		Array.prototype.forEach.call( panels, function ( panel ) {
			panel.hidden = panel.getAttribute( 'data-fsed-panel' ) !== name;
		} );

		// The About tab has nothing to save.
		var saveBar = wrap.querySelector( '.fsed-savebar' );
		if ( saveBar ) {
			saveBar.hidden = 'about' === name;
		}

		store( name );
	}

	if ( tabs.length && panels.length ) {
		wrap.classList.add( 'fsed-js' );

		Array.prototype.forEach.call( tabs, function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var name = tab.getAttribute( 'data-fsed-tab' );
				showTab( name );
				if ( window.history && window.history.replaceState ) {
					window.history.replaceState( null, '', '#fsed-panel-' + name );
				}
			} );
		} );

		var fromHash = window.location.hash.replace( '#fsed-panel-', '' );
		var initial = hasPanel( fromHash ) ? fromHash : readStored();

		showTab( hasPanel( initial ) ? initial : tabs[ 0 ].getAttribute( 'data-fsed-tab' ) );
	}

	/* ---------- Select all in a table row ---------- */

	function rowCells( row ) {
		return row.querySelectorAll( 'input[data-fsed-cell]' );
	}

	function syncRowAll( row ) {
		var all = row.querySelector( 'input[data-fsed-row-all]' );
		var cells = rowCells( row );
		var checked = 0;

		if ( ! all ) {
			return;
		}

		Array.prototype.forEach.call( cells, function ( cell ) {
			if ( cell.checked ) {
				checked++;
			}
		} );

		all.checked = cells.length > 0 && checked === cells.length;
		all.indeterminate = checked > 0 && checked < cells.length;
	}

	Array.prototype.forEach.call( wrap.querySelectorAll( '.fsed-matrix tbody tr' ), function ( row ) {
		var all = row.querySelector( 'input[data-fsed-row-all]' );

		syncRowAll( row );

		if ( all ) {
			all.addEventListener( 'change', function () {
				Array.prototype.forEach.call( rowCells( row ), function ( cell ) {
					cell.checked = all.checked;
				} );
				all.indeterminate = false;
			} );
		}

		Array.prototype.forEach.call( rowCells( row ), function ( cell ) {
			cell.addEventListener( 'change', function () {
				syncRowAll( row );
			} );
		} );
	} );

	/* ---------- Live preview ---------- */

	var preview = document.getElementById( 'fsed-preview' );
	var size = document.getElementById( 'fsed-font-size' );
	var theme = document.getElementById( 'fsed-editor-theme' );

	function updatePreview() {
		if ( ! preview || ! size || ! theme ) {
			return;
		}

		var px = parseInt( size.value, 10 );

		if ( px >= 10 && px <= 24 ) {
			preview.style.fontSize = px + 'px';
		}
		preview.classList.toggle( 'is-dark', 'dark' === theme.value );

		var tab = document.getElementById( 'fsed-tab-size' );
		var wrapBox = document.getElementById( 'fsed-word-wrap' );

		if ( tab ) {
			preview.style.tabSize = tab.value;
		}
		if ( wrapBox ) {
			preview.style.whiteSpace = wrapBox.checked ? 'pre-wrap' : 'pre';
		}
	}

	if ( size && theme ) {
		size.addEventListener( 'input', updatePreview );
		theme.addEventListener( 'change', updatePreview );
		[ 'fsed-tab-size', 'fsed-word-wrap' ].forEach( function ( id ) {
			var field = document.getElementById( id );
			if ( field ) {
				field.addEventListener( 'change', updatePreview );
			}
		} );
		updatePreview();
	}
}( window, document ) );
