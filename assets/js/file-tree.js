/**
 * FileSmith Editor: file tree renderer.
 *
 * Builds the tree with DOM methods and textContent only, never innerHTML.
 */
( function ( window, document ) {
	'use strict';

	/**
	 * Create an element.
	 *
	 * @param {string} tag       Tag name.
	 * @param {string} className Class names.
	 * @param {string} text      Text content.
	 * @return {HTMLElement} Element.
	 */
	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	/**
	 * Create the small icon shown before a name. Drawn with CSS only.
	 *
	 * @param {string} kind Either 'folder' or 'file'.
	 * @param {string} name File name, used for the file type color.
	 * @return {HTMLElement} Icon element.
	 */
	function icon( kind, name ) {
		var className = 'fsed-icon fsed-icon-' + kind;

		if ( 'file' === kind && name && -1 !== name.indexOf( '.' ) ) {
			className += ' fsed-ext-' + name.split( '.' ).pop().toLowerCase().replace( /[^a-z0-9]/g, '' );
		}

		var node = el( 'span', className );
		node.setAttribute( 'aria-hidden', 'true' );
		return node;
	}

	/**
	 * Build a list for a set of nodes.
	 *
	 * @param {Array}    nodes    Tree nodes.
	 * @param {Function} onSelect Called with a file path when an editable file is clicked.
	 * @param {Function} onNode   Optional. Called with the node when any file or folder is clicked.
	 * @return {HTMLElement} List element.
	 */
	function buildList( nodes, onSelect, onNode ) {
		var list = el( 'ul', 'fsed-tree-list' );

		nodes.forEach( function ( node ) {
			var item = el( 'li', 'fsed-tree-item fsed-tree-' + node.type );

			if ( 'dir' === node.type ) {
				var toggle = el( 'button', 'fsed-tree-toggle' );
				var children = buildList( node.children || [], onSelect, onNode );

				toggle.appendChild( icon( 'folder' ) );
				toggle.appendChild( el( 'span', 'fsed-tree-name', node.name ) );
				toggle.type = 'button';
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.setAttribute( 'data-path', node.path );
				children.hidden = true;

				toggle.addEventListener( 'click', function () {
					var open = children.hidden;
					children.hidden = ! open;
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
					if ( onNode ) {
						onNode( node );
					}
				} );

				item.appendChild( toggle );
				item.appendChild( children );
			} else {
				var openable = node.editable || node.image;
				var button = el( 'button', openable ? 'fsed-tree-file' : 'fsed-tree-file fsed-tree-disabled' );

				button.appendChild( icon( 'file', node.name ) );
				button.appendChild( el( 'span', 'fsed-tree-name', node.name ) );
				button.type = 'button';
				button.setAttribute( 'data-path', node.path );
				button.addEventListener( 'click', function () {
					if ( onNode ) {
						onNode( node );
					}
					if ( openable ) {
						onSelect( node.path );
					}
				} );

				item.appendChild( button );
			}

			list.appendChild( item );
		} );

		return list;
	}

	/**
	 * Render the tree.
	 *
	 * @param {HTMLElement} container Container element.
	 * @param {Array}       nodes     Tree nodes.
	 * @param {Function}    onSelect  Editable file click callback.
	 * @param {string}      emptyText Text shown when there are no files.
	 * @param {Function}    onNode    Optional. Any file or folder click callback.
	 */
	function render( container, nodes, onSelect, emptyText, onNode ) {
		container.textContent = '';

		if ( ! nodes || ! nodes.length ) {
			container.appendChild( el( 'p', 'fsed-empty', emptyText ) );
			return;
		}

		container.appendChild( buildList( nodes, onSelect, onNode ) );
	}

	/**
	 * Open every folder above a node.
	 *
	 * @param {HTMLElement} node      Start node.
	 * @param {HTMLElement} container Tree container.
	 */
	function expandParents( node, container ) {
		var parent = node.parentNode;

		while ( parent && parent !== container ) {
			if ( 'UL' === parent.tagName && parent.hidden ) {
				parent.hidden = false;
				if ( parent.previousSibling && parent.previousSibling.setAttribute ) {
					parent.previousSibling.setAttribute( 'aria-expanded', 'true' );
				}
			}
			parent = parent.parentNode;
		}
	}

	/**
	 * Highlight the open file.
	 *
	 * @param {HTMLElement} container Tree container.
	 * @param {string}      path      File path.
	 */
	function setActive( container, path ) {
		var files = container.querySelectorAll( '.fsed-tree-file' );

		Array.prototype.forEach.call( files, function ( file ) {
			var active = file.getAttribute( 'data-path' ) === path;
			file.classList.toggle( 'is-active', active );
			if ( active ) {
				expandParents( file, container );
			}
		} );
	}

	/**
	 * Highlight the selected file or folder. An empty path clears the selection.
	 *
	 * @param {HTMLElement} container Tree container.
	 * @param {string}      path      File or folder path.
	 */
	function select( container, path ) {
		var nodes = container.querySelectorAll( '[data-path]' );

		Array.prototype.forEach.call( nodes, function ( node ) {
			var selected = '' !== path && node.getAttribute( 'data-path' ) === path;
			node.classList.toggle( 'is-selected', selected );
			if ( selected ) {
				expandParents( node, container );
			}
		} );
	}

	window.FSEDTree = {
		render: render,
		setActive: setActive,
		select: select
	};
}( window, document ) );
