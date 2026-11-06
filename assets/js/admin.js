/**
 * FileSmith Editor (free): admin page controller.
 */
( function ( window, document ) {
	'use strict';

	var config = window.fsedData;
	var Tree = window.FSEDTree;

	if ( ! config || ! Tree ) {
		return;
	}

	var typeSelect = document.getElementById( 'fsed-type' );
	var itemSelect = document.getElementById( 'fsed-item' );
	var treeBox = document.getElementById( 'fsed-tree' );
	var editorField = document.getElementById( 'fsed-editor' );
	var fileLabel = document.getElementById( 'fsed-current-file' );
	var saveButton = document.getElementById( 'fsed-save' );
	var backupsButton = document.getElementById( 'fsed-backups' );
	var modal = document.getElementById( 'fsed-modal' );
	var modalFile = document.getElementById( 'fsed-modal-file' );
	var modalStatus = document.getElementById( 'fsed-modal-status' );
	var backupList = document.getElementById( 'fsed-backup-list' );
	var backupPreview = document.getElementById( 'fsed-backup-preview' );
	var restoreButton = document.getElementById( 'fsed-restore' );
	var notice = document.getElementById( 'fsed-notice' );
	var noticeText = document.getElementById( 'fsed-notice-text' );
	var selectedLabel = document.getElementById( 'fsed-selected-path' );

	if ( ! typeSelect || ! itemSelect || ! treeBox || ! editorField || ! fileLabel || ! saveButton || ! notice || ! noticeText ) {
		return;
	}

	if ( ! backupsButton || ! modal || ! modalFile || ! modalStatus || ! backupList || ! backupPreview || ! restoreButton ) {
		return;
	}

	var MODES = {
		php: 'application/x-httpd-php',
		css: 'text/css',
		scss: 'text/x-scss',
		less: 'text/x-less',
		js: 'text/javascript',
		json: 'application/json',
		html: 'text/html',
		htm: 'text/html',
		xml: 'application/xml',
		svg: 'application/xml',
		md: 'text/x-markdown',
		yml: 'text/x-yaml',
		yaml: 'text/x-yaml'
	};

	var state = {
		type: config.current.type,
		slug: config.current.slug,
		path: '',
		original: '',
		writable: false,
		dirty: false,
		saving: false,
		loading: false
	};
	var treeToken = 0;
	var fileToken = 0;
	var noticeTimer = null;
	var backupToken = 0;
	var selectedBackup = '';
	var modalOpener = null;
	var treeData = [];
	var treeReady = false;
	var selected = null;
	var refreshButton = document.getElementById( 'fsed-refresh' );
	var imageBox = document.getElementById( 'fsed-image-preview' );
	var imageEl = document.getElementById( 'fsed-image' );
	var imageInfo = document.getElementById( 'fsed-image-info' );
	var refreshing = false;
	var sessionReady = false;
	var editingTitle = document.getElementById( 'fsed-editing' );
	var statusBox = document.getElementById( 'fsed-editor-status' );
	var statusText = document.getElementById( 'fsed-editor-status-text' );
	var treeLoading = false;
	var fileLoading = false;
	var sbType = document.getElementById( 'fsed-sb-type' );
	var sbPos = document.getElementById( 'fsed-sb-pos' );
	var sbState = document.getElementById( 'fsed-sb-state' );
	var sbAccess = document.getElementById( 'fsed-sb-access' );
	var tabBar = document.getElementById( 'fsed-filetabs' );
	var tabs = [];
	var activeTab = null;
	var MAX_TABS = 15;

	/* ---------- Editor wrapper (CodeMirror, or the plain textarea as fallback) ---------- */

	var cm = null;

	if ( window.wp && window.wp.codeEditor && config.editorSettings ) {
		cm = window.wp.codeEditor.initialize( editorField, config.editorSettings ).codemirror;
	}

	// Each open file keeps its own CodeMirror document, with its own undo history.
	var DocClass = cm ? cm.getDoc().constructor : null;

	function getValue() {
		return cm ? cm.getValue() : editorField.value;
	}

	function setValue( value ) {
		state.loading = true;
		if ( cm ) {
			cm.setValue( value );
			cm.clearHistory();
		} else {
			editorField.value = value;
		}
		state.loading = false;
	}

	function setReadOnly( readOnly ) {
		if ( cm ) {
			cm.setOption( 'readOnly', readOnly ? 'nocursor' : false );
		} else {
			editorField.readOnly = readOnly;
		}
	}

	function modeFor( path ) {
		return MODES[ path.split( '.' ).pop().toLowerCase() ] || 'text/plain';
	}

	function setMode( path ) {
		if ( cm ) {
			cm.setOption( 'mode', modeFor( path ) );
		}
	}

	/**
	 * Show an image in place of the editor, or hide the preview.
	 *
	 * @param {Object|null} image Image data from the read request, or null.
	 */
	function showImage( image ) {
		if ( ! imageBox || ! imageEl || ! imageInfo ) {
			return;
		}

		imageBox.hidden = ! image;

		if ( ! image ) {
			imageEl.removeAttribute( 'src' );
			imageInfo.textContent = '';
			return;
		}

		var kb = image.bytes / 1024;
		var parts = [];

		if ( image.width && image.height ) {
			parts.push( image.width + ' × ' + image.height + ' px' );
		}
		parts.push( kb >= 1024 ? ( kb / 1024 ).toFixed( 1 ) + ' MB' : Math.max( 1, Math.round( kb ) ) + ' KB' );
		parts.push( image.mime );

		imageEl.src = image.src;
		imageInfo.textContent = parts.join( ' · ' );
	}

	function focusEditor() {
		if ( cm ) {
			cm.focus();
		} else {
			editorField.focus();
		}
	}

	/* ---------- UI helpers ---------- */

	/**
	 * Check whether the current user may do an action. The server checks again.
	 *
	 * @param {string} action Action key, such as 'save' or 'backups'.
	 * @return {boolean} True when allowed.
	 */
	function can( action ) {
		return !! ( config.can && config.can[ action ] );
	}

	function showNotice( message, kind ) {
		window.clearTimeout( noticeTimer );
		noticeText.textContent = message;
		notice.className = 'notice fsed-notice notice-' + kind;
		notice.hidden = false;

		if ( 'success' === kind ) {
			noticeTimer = window.setTimeout( hideNotice, 4000 );
		}
	}

	function hideNotice() {
		window.clearTimeout( noticeTimer );
		notice.hidden = true;
	}

	function showError( error ) {
		showNotice( error && error.isFsed ? error.message : config.i18n.error, 'error' );
	}

	function parentOf( path ) {
		var index = path.lastIndexOf( '/' );
		return -1 === index ? '' : path.slice( 0, index );
	}

	function baseName( path ) {
		return path.slice( path.lastIndexOf( '/' ) + 1 );
	}

	function findNode( nodes, path ) {
		var found = null;

		nodes.some( function ( node ) {
			if ( node.path === path ) {
				found = node;
			} else if ( 'dir' === node.type && node.children ) {
				found = findNode( node.children, path );
			}
			return null !== found;
		} );

		return found;
	}

	/**
	 * Get the selected theme or plugin from the localized list.
	 *
	 * @return {Object|null} Item with slug, name and active, or null.
	 */
	function currentItem() {
		var items = config.items[ state.type ] || [];
		var found = null;

		items.some( function ( item ) {
			if ( item.slug === state.slug ) {
				found = item;
			}
			return null !== found;
		} );

		return found;
	}

	/**
	 * Get the folder name of the selected theme or plugin.
	 *
	 * @return {string} Theme folder, or plugin folder.
	 */
	function rootFolder() {
		return 'theme' === state.type ? state.slug : parentOf( state.slug );
	}

	/**
	 * Show what the editor is doing: loading the file list, loading a file,
	 * or waiting for a file to be selected. Hidden while a file is open.
	 */
	function updateStatus() {
		if ( ! statusBox || ! statusText ) {
			return;
		}

		var message = '';
		var busy = false;

		if ( fileLoading ) {
			message = config.i18n.loadingFile;
			busy = true;
		} else if ( treeLoading ) {
			message = config.i18n.loading;
			busy = true;
		} else if ( ! state.path ) {
			message = config.i18n.selectFile;
		}

		statusText.textContent = message;
		statusBox.hidden = '' === message;
		statusBox.classList.toggle( 'is-loading', busy );
		statusBox.querySelector( '.spinner' ).classList.toggle( 'is-active', busy );
	}

	/**
	 * Show the cursor line and column in the status bar.
	 */
	function updatePosition() {
		if ( ! sbPos ) {
			return;
		}

		if ( ! state.path || ( activeTab && activeTab.image ) ) {
			sbPos.textContent = '';
			return;
		}

		var line;
		var col;

		if ( cm ) {
			var cursor = cm.getCursor();
			line = cursor.line + 1;
			col = cursor.ch + 1;
		} else {
			var lines = editorField.value.slice( 0, editorField.selectionStart ).split( '\n' );
			line = lines.length;
			col = lines[ lines.length - 1 ].length + 1;
		}

		sbPos.textContent = config.i18n.lineCol.replace( '%1$d', String( line ) ).replace( '%2$d', String( col ) );
	}

	/**
	 * Show the file type and the save and access state under the editor.
	 */
	function updateStatusBar() {
		if ( ! sbType || ! sbPos || ! sbState || ! sbAccess ) {
			return;
		}

		if ( ! state.path ) {
			sbType.textContent = '';
			sbState.textContent = '';
			sbAccess.textContent = '';
			updatePosition();
			return;
		}

		sbType.textContent = state.path.split( '.' ).pop().toUpperCase();
		sbState.textContent = state.dirty ? config.i18n.unsaved : config.i18n.noChanges;
		sbState.className = 'fsed-badge ' + ( state.dirty ? 'is-warn' : 'is-ok' );
		sbAccess.textContent = state.writable ? config.i18n.editable : config.i18n.readOnly;
		sbAccess.className = 'fsed-badge ' + ( state.writable ? 'is-ok' : 'is-muted' );
		updatePosition();
	}

	function updateUI() {
		var item = currentItem();

		if ( editingTitle ) {
			editingTitle.hidden = ! item;
			editingTitle.textContent = item ? ( item.active ? config.i18n.titleActive : config.i18n.titleInactive ).replace( '%s', function () {
				return item.name;
			} ) : '';
		}

		fileLabel.textContent = state.path ? rootFolder() + '/' + state.path + ( state.dirty ? ' *' : '' ) : '–';
		saveButton.disabled = ! state.path || ! state.writable || ! state.dirty || state.saving;
		saveButton.textContent = state.saving ? config.i18n.saving : config.i18n.save;
		backupsButton.disabled = ! state.path || ! can( 'backups' );
		if ( refreshButton ) {
			refreshButton.disabled = ! state.slug || ! treeReady || refreshing || state.saving;
		}
		if ( selectedLabel ) {
			selectedLabel.textContent = selected ? selected.path : '–';
		}
		updateStatus();
		updateStatusBar();
		updateTabStates();
		saveSession();
	}

	function selectNode( node ) {
		selected = { path: node.path, type: node.type };
		Tree.select( treeBox, node.path );
		updateUI();
	}

	function selectPath( path ) {
		var node = path ? findNode( treeData, path ) : null;

		selected = node ? { path: node.path, type: node.type } : null;
		Tree.select( treeBox, selected ? selected.path : '' );
		updateUI();
	}

	/* ---------- Open file tabs ---------- */

	/**
	 * Get the current content of a tab.
	 *
	 * @param {Object} tab Tab.
	 * @return {string} Content.
	 */
	function tabValue( tab ) {
		if ( tab === activeTab ) {
			return getValue();
		}
		return cm ? tab.doc.getValue() : tab.value;
	}

	function tabDirty( tab ) {
		return tab === activeTab ? state.dirty : tab.dirty;
	}

	function anyDirty() {
		return tabs.some( tabDirty );
	}

	function findTab( path ) {
		var found = null;

		tabs.some( function ( tab ) {
			if ( tab.path === path ) {
				found = tab;
			}
			return null !== found;
		} );

		return found;
	}

	/**
	 * Ask before discarding changes in the file that is open now.
	 *
	 * @return {boolean} True when it is fine to continue.
	 */
	function confirmDiscardCurrent() {
		return ! state.dirty || window.confirm( config.i18n.confirmDiscard );
	}

	/**
	 * Remember the editor state of the active tab before another one is shown.
	 */
	function stashActive() {
		if ( ! activeTab ) {
			return;
		}

		activeTab.original = state.original;
		activeTab.writable = state.writable;
		activeTab.dirty = state.dirty;

		if ( cm ) {
			activeTab.doc = cm.getDoc();
			activeTab.scroll = cm.getScrollInfo();
		} else {
			activeTab.value = editorField.value;
			activeTab.scroll = { left: editorField.scrollLeft, top: editorField.scrollTop };
		}
	}

	function activateTab( tab ) {
		if ( activeTab === tab ) {
			return;
		}

		stashActive();
		activeTab = tab;

		state.loading = true;
		if ( cm ) {
			cm.swapDoc( tab.doc );
		} else {
			editorField.value = tab.value;
		}
		state.loading = false;

		state.path = tab.path;
		state.original = tab.original;
		state.writable = tab.writable;
		state.dirty = tab.dirty;

		setMode( tab.path );
		setReadOnly( ! tab.writable );
		showImage( tab.image );

		if ( cm ) {
			cm.scrollTo( tab.scroll.left, tab.scroll.top );
			cm.refresh();
		} else {
			editorField.scrollLeft = tab.scroll.left;
			editorField.scrollTop = tab.scroll.top;
		}

		if ( tab.image ) {
			hideNotice();
		} else if ( ! can( 'save' ) ) {
			showNotice( config.i18n.noEditAccess, 'warning' );
		} else if ( ! tab.writable ) {
			showNotice( config.i18n.notWritable, 'warning' );
		} else {
			hideNotice();
		}

		Tree.setActive( treeBox, tab.path );
		updateUI();
	}

	/**
	 * Open a loaded file in a new tab and show it.
	 *
	 * @param {string}  path     File path.
	 * @param {string}  content  File content.
	 * @param {boolean} writable Whether the file can be edited.
	 * @param {Object}  image    Optional. Image data, for a preview tab.
	 */
	function addTab( path, content, writable, image ) {
		var existing = findTab( path );

		if ( existing ) {
			activateTab( existing );
			return;
		}

		var tab = {
			path: path,
			writable: writable,
			dirty: false,
			image: image || null,
			doc: null,
			value: '',
			original: '',
			scroll: { left: 0, top: 0 }
		};

		if ( cm ) {
			tab.doc = new DocClass( content, modeFor( path ) );
			// Read back the value, because the editor normalizes line endings.
			tab.original = tab.doc.getValue();
		} else {
			tab.value = content;
			tab.original = content;
		}

		tabs.push( tab );
		renderTabs();
		activateTab( tab );
	}

	/**
	 * Show an empty editor, when no file is open.
	 */
	function showEmpty() {
		state.loading = true;
		if ( cm ) {
			cm.swapDoc( new DocClass( '' ) );
		} else {
			editorField.value = '';
		}
		state.loading = false;

		state.path = '';
		state.original = '';
		state.writable = false;
		state.dirty = false;

		showImage( null );
		setReadOnly( true );
		Tree.setActive( treeBox, '' );
		updateUI();
	}

	/**
	 * Remove a tab without asking. The next tab is shown when the active one is removed.
	 *
	 * @param {Object} tab Tab.
	 */
	function removeTab( tab ) {
		var index = tabs.indexOf( tab );

		if ( -1 === index ) {
			return;
		}

		var wasActive = tab === activeTab;

		tabs.splice( index, 1 );

		if ( wasActive ) {
			activeTab = null;
			var next = tabs[ index ] || tabs[ index - 1 ] || null;

			if ( next ) {
				activateTab( next );
			} else {
				showEmpty();
			}
		}

		renderTabs();
	}

	/**
	 * Close a tab. Asks first when it has unsaved changes.
	 *
	 * @param {Object} tab Tab.
	 */
	function closeTab( tab ) {
		if ( tabDirty( tab ) && ! window.confirm( config.i18n.confirmClose ) ) {
			return;
		}

		removeTab( tab );
	}

	function renderTabs() {
		if ( ! tabBar ) {
			return;
		}

		tabBar.textContent = '';
		tabBar.hidden = ! tabs.length;

		tabs.forEach( function ( tab ) {
			var name = baseName( tab.path );
			var item = document.createElement( 'div' );
			var label = document.createElement( 'button' );
			var text = document.createElement( 'span' );
			var dot = document.createElement( 'span' );
			var close = document.createElement( 'button' );

			item.className = 'fsed-filetab';
			label.type = 'button';
			label.className = 'fsed-filetab-label';
			label.setAttribute( 'role', 'tab' );
			label.title = tab.path;
			text.className = 'fsed-filetab-name';
			text.textContent = name;
			dot.className = 'fsed-filetab-dot';
			dot.setAttribute( 'aria-hidden', 'true' );
			close.type = 'button';
			close.className = 'fsed-filetab-close';
			close.textContent = '×';
			close.setAttribute( 'aria-label', config.i18n.closeTab.replace( '%s', function () {
				return name;
			} ) );
			close.title = config.i18n.closeTab.replace( '%s', function () {
				return name;
			} );

			label.addEventListener( 'click', function () {
				activateTab( tab );
				focusEditor();
			} );
			close.addEventListener( 'click', function () {
				closeTab( tab );
			} );
			item.addEventListener( 'auxclick', function ( event ) {
				if ( 1 === event.button ) {
					event.preventDefault();
					closeTab( tab );
				}
			} );

			label.appendChild( text );
			label.appendChild( dot );
			item.appendChild( label );
			item.appendChild( close );
			tabBar.appendChild( item );
		} );

		updateTabStates();
	}

	/**
	 * Mark the active tab and the tabs with unsaved changes.
	 */
	function updateTabStates() {
		if ( ! tabBar ) {
			return;
		}

		Array.prototype.forEach.call( tabBar.children, function ( item, index ) {
			var tab = tabs[ index ];

			if ( ! tab ) {
				return;
			}

			var active = tab === activeTab;

			item.classList.toggle( 'is-active', active );
			item.classList.toggle( 'is-dirty', tabDirty( tab ) );
			item.firstChild.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );
	}

	function handleChange() {
		if ( state.loading || ! state.path ) {
			return;
		}
		state.dirty = getValue() !== state.original;
		updateUI();
	}

	/* ---------- Requests ---------- */

	function fsedError( message ) {
		var error = new Error( message );
		error.isFsed = true;
		return error;
	}

	/**
	 * Explain a response that is not from the plugin, such as a firewall page or a PHP error.
	 *
	 * @param {number} status HTTP status.
	 * @param {string} text   Response body.
	 * @return {string} Message.
	 */
	function httpErrorMessage( status, text ) {
		var body = String( text ).replace( /^\s+|\s+$/g, '' );

		if ( '0' === body || '-1' === body ) {
			return config.i18n.errSession;
		}
		if ( 413 === status ) {
			return config.i18n.errTooBig;
		}
		if ( 401 === status || 403 === status || 406 === status || 418 === status || 429 === status ) {
			return config.i18n.errBlocked.replace( '%d', String( status ) );
		}
		if ( 502 === status || 503 === status || 504 === status ) {
			return config.i18n.errUnavailable.replace( '%d', String( status ) );
		}
		if ( status >= 500 ) {
			return config.i18n.errServer.replace( '%d', String( status ) );
		}
		return config.i18n.errUnexpected.replace( '%d', String( status ) );
	}

	function request( action, params ) {
		var body = new window.FormData();

		body.append( 'action', 'fsed_' + action );
		body.append( 'nonce', config.nonce );
		body.append( 'type', state.type );
		body.append( 'slug', state.slug );

		Object.keys( params || {} ).forEach( function ( key ) {
			body.append( key, params[ key ] );
		} );

		return window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( response ) {
				return response.text().then( function ( text ) {
					var json = null;

					try {
						json = JSON.parse( text );
					} catch ( e ) {
						json = null;

						// PHP may print a warning or notice before the answer. Read the JSON that follows it.
						var start = text.indexOf( '{"success"' );
						if ( start > 0 ) {
							try {
								json = JSON.parse( text.slice( start ) );
							} catch ( e2 ) {
								json = null;
							}
						}
					}

					return { status: response.status, text: text, json: json };
				} );
			}, function () {
				// The request never got an answer: offline, server down, or the connection was cut.
				throw fsedError( config.i18n.errNetwork );
			} )
			.then( function ( result ) {
				var json = result.json;

				if ( json && json.success ) {
					return json.data;
				}

				if ( json && json.data && json.data.message ) {
					throw fsedError( json.data.message );
				}

				throw fsedError( httpErrorMessage( result.status, result.text ) );
			} );
	}

	/* ---------- Actions ---------- */

	/**
	 * Close every tab and empty the editor. Used when another theme or plugin is loaded.
	 */
	function clearFile() {
		fileToken++;
		fileLoading = false;
		tabs = [];
		activeTab = null;
		showEmpty();
		renderTabs();
	}

	function openFile( path ) {
		var existing = findTab( path );

		if ( existing ) {
			activateTab( existing );
			return Promise.resolve();
		}

		if ( tabs.length >= MAX_TABS ) {
			showNotice( config.i18n.tooManyTabs, 'warning' );
			return Promise.resolve();
		}

		var token = ++fileToken;

		fileLoading = true;
		updateStatus();

		return request( 'read', { path: path } )
			.then( function ( data ) {
				if ( token !== fileToken ) {
					return;
				}

				fileLoading = false;
				addTab( data.path, data.content, !! data.writable && can( 'save' ), data.image );

				if ( data.image ) {
					hideNotice();
				} else if ( ! can( 'save' ) ) {
					showNotice( config.i18n.noEditAccess, 'warning' );
				} else if ( state.writable ) {
					hideNotice();
				} else {
					showNotice( config.i18n.notWritable, 'warning' );
				}
			} )
			.catch( function ( error ) {
				if ( token === fileToken ) {
					fileLoading = false;
					updateStatus();
					showError( error );
				}
			} );
	}

	/**
	 * Normalize line endings, so a file with Windows line endings compares equal to the editor text.
	 *
	 * @param {string} text Text.
	 * @return {string} Text with \n line endings.
	 */
	function normalizeEol( text ) {
		return String( text ).replace( /\r\n?/g, '\n' );
	}

	/**
	 * Before saving, check that the file on disk is still the version that was opened.
	 *
	 * @param {string} path     File path.
	 * @param {string} expected Content when the file was opened or last saved.
	 * @return {Promise} Resolves true to go on with the save, false to stop.
	 */
	function checkDisk( path, expected ) {
		return request( 'read', { path: path } ).then( function ( data ) {
			if ( normalizeEol( data.content ) === normalizeEol( expected ) ) {
				return true;
			}
			return window.confirm( config.i18n.confirmOverwrite );
		}, function () {
			// The file could not be read (for example it was deleted). Let the save decide.
			return true;
		} );
	}

	function saveFile() {
		if ( ! state.path || ! state.writable || ! state.dirty || state.saving ) {
			return;
		}

		var tab = activeTab;
		var path = state.path;
		var content = getValue();

		state.saving = true;
		updateUI();

		checkDisk( path, tab === activeTab ? state.original : tab.original )
			.then( function ( proceed ) {
				if ( ! proceed ) {
					return false;
				}

				return request( 'save', { path: path, content: content } ).then( function () {
					return true;
				} );
			} )
			.then( function ( saved ) {
				if ( ! saved ) {
					return;
				}

				tab.original = content;

				if ( tab === activeTab ) {
					state.original = content;
					state.dirty = getValue() !== state.original;
				} else {
					tab.dirty = tabValue( tab ) !== content;
				}

				showNotice( config.i18n.saved, 'success' );
			} )
			.catch( showError )
			.then( function () {
				state.saving = false;
				updateUI();
			} );
	}

	/**
	 * Get the main file of the selected theme or plugin.
	 *
	 * @return {string} style.css for a theme, the main plugin file for a plugin.
	 */
	function mainFilePath() {
		return 'theme' === state.type ? 'style.css' : baseName( state.slug );
	}

	function loadTree() {
		var token = ++treeToken;

		sessionReady = false;
		clearFile();
		treeData = [];
		treeReady = false;
		selected = null;
		treeLoading = !! state.slug;
		updateUI();
		treeBox.textContent = config.i18n.loading;

		if ( ! state.slug ) {
			Tree.render( treeBox, [], openFile, config.i18n.empty );
			return;
		}

		request( 'tree' )
			.then( function ( data ) {
				if ( token !== treeToken ) {
					return;
				}
				hideNotice();
				treeLoading = false;
				treeData = data.tree;
				treeReady = true;
				Tree.render( treeBox, data.tree, openFile, config.i18n.empty, selectNode );
				updateUI();

				// Reopen the files that were open last time, or the main file.
				restoreSession( data.tree );
			} )
			.catch( function ( error ) {
				if ( token === treeToken ) {
					sessionReady = true;
					treeLoading = false;
					treeBox.textContent = '';
					updateStatus();
					showError( error );
				}
			} );
	}

	function populateItems() {
		var items = config.items[ state.type ] || [];
		var found = false;

		itemSelect.textContent = '';

		items.forEach( function ( item ) {
			var option = document.createElement( 'option' );
			option.value = item.slug;
			option.textContent = item.name;
			itemSelect.appendChild( option );
			if ( item.slug === state.slug ) {
				found = true;
			}
		} );

		if ( found ) {
			itemSelect.value = state.slug;
		} else {
			state.slug = itemSelect.value;
		}
	}

	/**
	 * Switch between themes and plugins, or between items of one type.
	 */
	typeSelect.addEventListener( 'change', function () {
		if ( anyDirty() && ! window.confirm( config.i18n.confirmDiscard ) ) {
			typeSelect.value = state.type;
			return;
		}

		state.type = typeSelect.value;
		state.slug = '';
		populateItems();
		loadTree();
	} );

	itemSelect.addEventListener( 'change', function () {
		if ( anyDirty() && ! window.confirm( config.i18n.confirmDiscard ) ) {
			itemSelect.value = state.slug;
			return;
		}

		state.slug = itemSelect.value;
		loadTree();
	} );

	/* ---------- Backups ---------- */

	function setStatus( text ) {
		modalStatus.textContent = text;
	}

	function openModal() {
		modalOpener = document.activeElement;
		modal.hidden = false;
		document.body.classList.add( 'fsed-modal-open' );
		modal.querySelector( '.fsed-modal-close' ).focus();
	}

	function closeModal() {
		backupToken++;
		modal.hidden = true;
		document.body.classList.remove( 'fsed-modal-open' );
		if ( modalOpener && modalOpener.focus ) {
			modalOpener.focus();
		}
	}

	function trapFocus( event ) {
		if ( 'Tab' !== event.key ) {
			return;
		}

		var nodes = modal.querySelectorAll( 'button:not([disabled]), textarea' );
		if ( ! nodes.length ) {
			return;
		}

		var first = nodes[ 0 ];
		var last = nodes[ nodes.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	function clearPreview() {
		backupPreview.value = '';
	}

	function selectBackup( id ) {
		var token = ++backupToken;

		selectedBackup = id;
		restoreButton.disabled = true;
		setStatus( config.i18n.loadingBackups );

		Array.prototype.forEach.call( backupList.querySelectorAll( '.fsed-backup-item' ), function ( item ) {
			item.classList.toggle( 'is-active', item.getAttribute( 'data-id' ) === id );
		} );

		request( 'backup_read', { path: state.path, id: id } )
			.then( function ( data ) {
				if ( token !== backupToken ) {
					return;
				}
				backupPreview.value = data.content;
				setStatus( '' );
				restoreButton.disabled = ! state.writable;
			} )
			.catch( function ( error ) {
				if ( token === backupToken ) {
					setStatus( error && error.isFsed ? error.message : config.i18n.error );
				}
			} );
	}

	function renderBackups( backups ) {
		backupList.textContent = '';
		clearPreview();
		selectedBackup = '';
		restoreButton.disabled = true;

		if ( ! backups.length ) {
			setStatus( config.i18n.noBackups );
			return;
		}

		setStatus( config.i18n.selectBackup );

		backups.forEach( function ( backup ) {
			var item = document.createElement( 'li' );
			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'fsed-backup-item';
			button.textContent = backup.label + ' · ' + backup.size;
			button.setAttribute( 'data-id', backup.id );

			var author = document.createElement( 'span' );
			author.className = 'fsed-backup-author';
			author.textContent = backup.author ? config.i18n.backupBy.replace( '%s', function () {
				return backup.author;
			} ) : config.i18n.backupUnknown;
			button.appendChild( author );

			button.addEventListener( 'click', function () {
				selectBackup( backup.id );
			} );

			item.appendChild( button );
			backupList.appendChild( item );
		} );
	}

	function openBackups() {
		if ( ! state.path ) {
			return;
		}

		var token = ++backupToken;

		modalFile.textContent = state.path;
		backupList.textContent = '';
		clearPreview();
		selectedBackup = '';
		restoreButton.disabled = true;
		setStatus( config.i18n.loadingBackups );
		openModal();

		request( 'backups', { path: state.path } )
			.then( function ( data ) {
				if ( token === backupToken ) {
					renderBackups( data.backups );
				}
			} )
			.catch( function ( error ) {
				if ( token === backupToken ) {
					setStatus( error && error.isFsed ? error.message : config.i18n.error );
				}
			} );
	}

	/**
	 * Load the selected backup into the editor as an unsaved change.
	 * Nothing is written to disk until the user clicks Save.
	 */
	function restoreBackup() {
		if ( ! selectedBackup || ! state.writable || ! confirmDiscardCurrent() ) {
			return;
		}

		var content = backupPreview.value;

		state.loading = true;
		if ( cm ) {
			cm.setValue( content );
		} else {
			editorField.value = content;
		}
		state.loading = false;

		state.dirty = getValue() !== state.original;
		updateUI();
		closeModal();
		showNotice( config.i18n.backupLoaded, 'info' );
		focusEditor();
	}

	backupsButton.addEventListener( 'click', openBackups );
	restoreButton.addEventListener( 'click', restoreBackup );

	modal.addEventListener( 'click', function ( event ) {
		if ( event.target.closest && event.target.closest( '[data-fsed-close]' ) ) {
			closeModal();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( modal.hidden ) {
			return;
		}
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			closeModal();
			return;
		}
		trapFocus( event );
	} );

	/* ---------- Events ---------- */

	if ( cm ) {
		cm.on( 'change', handleChange );
		cm.on( 'cursorActivity', updatePosition );
	} else {
		editorField.addEventListener( 'input', handleChange );
		editorField.addEventListener( 'keyup', updatePosition );
		editorField.addEventListener( 'click', updatePosition );
	}

	saveButton.addEventListener( 'click', function () {
		saveFile();
		focusEditor();
	} );

	// Ctrl+S or Cmd+S saves the open file.
	document.addEventListener( 'keydown', function ( event ) {
		if ( ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
			event.preventDefault();
			if ( ! modal.hidden ) {
				return;
			}
			saveFile();
		}
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( anyDirty() ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );

	/* ---------- Refresh (file and tree) ---------- */

	/**
	 * Put freshly read content into a tab.
	 *
	 * @param {Object} tab  Tab.
	 * @param {Object} data Response of the read request.
	 */
	function applyReload( tab, data ) {
		if ( -1 === tabs.indexOf( tab ) ) {
			return;
		}

		// An image tab has no text to reload. Show the new picture.
		if ( tab.image ) {
			if ( data.image ) {
				tab.image = data.image;
				if ( tab === activeTab ) {
					showImage( tab.image );
				}
			}
			return;
		}

		var writable = !! data.writable && can( 'save' );

		if ( tab === activeTab ) {
			var cursor = cm ? cm.getCursor() : null;
			var scroll = cm ? cm.getScrollInfo() : { left: editorField.scrollLeft, top: editorField.scrollTop };

			setValue( data.content );

			if ( cm ) {
				cm.setCursor( cursor );
				cm.scrollTo( scroll.left, scroll.top );
			} else {
				editorField.scrollLeft = scroll.left;
				editorField.scrollTop = scroll.top;
			}

			state.original = getValue();
			state.writable = writable;
			state.dirty = false;
			setReadOnly( ! writable );
		} else if ( cm ) {
			tab.doc.setValue( data.content );
			tab.doc.clearHistory();
			tab.original = tab.doc.getValue();
			tab.dirty = false;
		} else {
			tab.value = data.content;
			tab.original = data.content;
			tab.dirty = false;
		}

		tab.writable = writable;
	}

	/**
	 * Reload the file tree and the open file from disk, without reloading the page.
	 */
	function refreshAll() {
		if ( refreshing || ! state.slug || ! treeReady || state.saving ) {
			return;
		}

		var tab = activeTab;
		var path = state.path;

		if ( tab && state.dirty && ! window.confirm( config.i18n.confirmReload ) ) {
			return;
		}

		var keep = selected ? selected.path : '';
		var token = ++treeToken;
		var failed = false;

		refreshing = true;
		updateUI();

		var filePromise = tab ? request( 'read', { path: path } ).catch( function ( error ) {
			failed = true;
			showError( error );
			return null;
		} ) : Promise.resolve( null );

		Promise.all( [ request( 'tree' ), filePromise ] )
			.then( function ( results ) {
				if ( token === treeToken ) {
					treeData = results[ 0 ].tree;
					Tree.render( treeBox, treeData, openFile, config.i18n.empty, selectNode );
					if ( state.path ) {
						Tree.setActive( treeBox, state.path );
					}
					selectPath( keep );
				}

				if ( results[ 1 ] ) {
					applyReload( tab, results[ 1 ] );
				}

				if ( failed ) {
					return;
				}

				if ( tab && ! can( 'save' ) ) {
					showNotice( config.i18n.noEditAccess, 'warning' );
				} else if ( tab && ! state.writable ) {
					showNotice( config.i18n.notWritable, 'warning' );
				} else {
					showNotice( config.i18n.refreshed, 'success' );
				}
			} )
			.catch( showError )
			.then( function () {
				refreshing = false;
				updateUI();
			} );
	}

	if ( refreshButton ) {
		refreshButton.addEventListener( 'click', refreshAll );
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( ( event.ctrlKey || event.metaKey ) && event.altKey && 'KeyR' === event.code ) {
			event.preventDefault();
			if ( ! modal.hidden ) {
				return;
			}
			refreshAll();
		}
	} );

	/* ---------- Quick open (Ctrl+P) ---------- */

	var quick = { root: null, input: null, list: null, items: [], index: 0, opener: null };

	/**
	 * Collect every file that can be opened.
	 *
	 * @param {Array} nodes Tree nodes.
	 * @param {Array} list  Result list.
	 * @return {Array} Files with path, name and lower-case path.
	 */
	function collectFiles( nodes, list ) {
		nodes.forEach( function ( node ) {
			if ( 'dir' === node.type ) {
				collectFiles( node.children || [], list );
			} else if ( node.editable || node.image ) {
				list.push( { path: node.path, name: node.name, low: node.path.toLowerCase(), lowName: node.name.toLowerCase() } );
			}
		} );

		return list;
	}

	/**
	 * Score a file against a query. Lower is better, -1 means no match.
	 *
	 * @param {Object} file  File from collectFiles().
	 * @param {string} query Lower-case query without spaces.
	 * @return {number} Score.
	 */
	function quickScore( file, query ) {
		var at;
		var pos = 0;
		var i;

		if ( ! query ) {
			return 0;
		}

		at = file.lowName.indexOf( query );
		if ( 0 === at ) {
			return 1;
		}
		if ( at > 0 ) {
			return 2 + at / 100;
		}

		at = file.low.indexOf( query );
		if ( at >= 0 ) {
			return 3 + at / 1000;
		}

		// Letters in order, for example "clsapi" finds class-api.php.
		for ( i = 0; i < query.length; i++ ) {
			pos = file.low.indexOf( query.charAt( i ), pos );
			if ( -1 === pos ) {
				return -1;
			}
			pos++;
		}

		return 4 + file.low.length / 1000;
	}

	function quickRender() {
		var query = quick.input.value.toLowerCase().replace( /\s+/g, '' );
		var scored = [];

		collectFiles( treeData, [] ).forEach( function ( file ) {
			var score = quickScore( file, query );
			if ( score >= 0 ) {
				scored.push( { file: file, score: score } );
			}
		} );

		scored.sort( function ( a, b ) {
			return a.score - b.score || a.file.path.localeCompare( b.file.path );
		} );

		quick.items = scored.slice( 0, 50 ).map( function ( entry ) {
			return entry.file;
		} );
		quick.index = 0;
		quick.list.textContent = '';

		if ( ! quick.items.length ) {
			var none = document.createElement( 'li' );
			none.className = 'fsed-qo-none';
			none.textContent = config.i18n.quickOpenNone || 'No matching files.';
			quick.list.appendChild( none );
			quick.input.removeAttribute( 'aria-activedescendant' );
			return;
		}

		quick.items.forEach( function ( file, index ) {
			var row = document.createElement( 'li' );
			var name = document.createElement( 'span' );
			var dir = document.createElement( 'span' );
			var slash = file.path.lastIndexOf( '/' );

			row.id = 'fsed-qo-' + index;
			row.className = 'fsed-qo-item';
			row.setAttribute( 'role', 'option' );
			name.className = 'fsed-qo-name';
			name.textContent = file.name;
			dir.className = 'fsed-qo-dir';
			dir.textContent = slash > 0 ? file.path.slice( 0, slash ) : '';
			row.appendChild( name );
			row.appendChild( dir );
			row.addEventListener( 'mousemove', function () {
				quickSelect( index, false );
			} );
			row.addEventListener( 'click', function () {
				quickChoose( index );
			} );
			quick.list.appendChild( row );
		} );

		quickSelect( 0, true );
	}

	function quickSelect( index, scroll ) {
		var rows = quick.list.querySelectorAll( '.fsed-qo-item' );

		if ( ! rows.length ) {
			return;
		}

		quick.index = ( index + rows.length ) % rows.length;

		Array.prototype.forEach.call( rows, function ( row, i ) {
			var on = i === quick.index;
			row.classList.toggle( 'is-active', on );
			row.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			if ( on && scroll ) {
				row.scrollIntoView( { block: 'nearest' } );
			}
		} );

		quick.input.setAttribute( 'aria-activedescendant', 'fsed-qo-' + quick.index );
	}

	function quickClose() {
		if ( ! quick.root || quick.root.hidden ) {
			return;
		}

		quick.root.hidden = true;

		if ( quick.opener && quick.opener.focus && document.body.contains( quick.opener ) ) {
			quick.opener.focus();
		}
	}

	function quickChoose( index ) {
		var file = quick.items[ index ];

		if ( ! file ) {
			return;
		}

		quick.opener = null;
		quickClose();
		openFile( file.path );
	}

	function quickBuild() {
		var box = document.createElement( 'div' );
		var label = config.i18n.quickOpen || 'Quick open';

		quick.root = document.createElement( 'div' );
		quick.root.className = 'fsed-qo';
		quick.root.hidden = true;
		box.className = 'fsed-qo-box';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', label );

		quick.input = document.createElement( 'input' );
		quick.input.type = 'text';
		quick.input.className = 'fsed-qo-input';
		quick.input.placeholder = config.i18n.quickOpenHint || 'Type part of a file name…';
		quick.input.setAttribute( 'role', 'combobox' );
		quick.input.setAttribute( 'aria-expanded', 'true' );
		quick.input.setAttribute( 'aria-controls', 'fsed-qo-list' );
		quick.input.setAttribute( 'aria-label', label );
		quick.input.setAttribute( 'autocomplete', 'off' );
		quick.input.setAttribute( 'spellcheck', 'false' );

		quick.list = document.createElement( 'ul' );
		quick.list.id = 'fsed-qo-list';
		quick.list.className = 'fsed-qo-list';
		quick.list.setAttribute( 'role', 'listbox' );

		box.appendChild( quick.input );
		box.appendChild( quick.list );
		quick.root.appendChild( box );
		( wrapper || document.body ).appendChild( quick.root );

		quick.root.addEventListener( 'mousedown', function ( event ) {
			if ( event.target === quick.root ) {
				quickClose();
			}
		} );
		quick.input.addEventListener( 'input', quickRender );
		quick.input.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				quickSelect( quick.index + 1, true );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				quickSelect( quick.index - 1, true );
			} else if ( 'Enter' === event.key ) {
				event.preventDefault();
				quickChoose( quick.index );
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				quickClose();
			} else if ( 'Tab' === event.key ) {
				event.preventDefault();
			}
		} );
	}

	function quickOpen() {
		if ( ! quick.root ) {
			quickBuild();
		}

		quick.opener = document.activeElement;
		quick.input.value = '';
		quick.root.hidden = false;
		quickRender();
		quick.input.focus();
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! ( event.ctrlKey || event.metaKey ) || event.altKey || event.shiftKey || 'KeyP' !== event.code ) {
			return;
		}

		event.preventDefault();

		if ( quick.root && ! quick.root.hidden ) {
			quickClose();
			return;
		}

		if ( ! treeReady || ! modal.hidden ) {
			return;
		}

		quickOpen();
	} );

	/* ---------- Fullscreen ---------- */

	var fullscreenButton = document.getElementById( 'fsed-fullscreen' );
	var wrapper = document.querySelector( '.fsed-wrapper' );

	function isFullscreen() {
		return !! wrapper && wrapper.classList.contains( 'is-fullscreen' );
	}

	/**
	 * Make the editor and the file list fill the browser window, or go back.
	 *
	 * @param {boolean} on True for fullscreen.
	 */
	function setFullscreen( on ) {
		wrapper.classList.toggle( 'is-fullscreen', on );
		document.body.classList.toggle( 'fsed-is-fullscreen', on );
		fullscreenButton.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		fullscreenButton.querySelector( '.fsed-btn-label' ).textContent = on ? config.i18n.exitFullscreen : config.i18n.fullscreen;
		fullscreenButton.title = on ? config.i18n.tipExitFullscreen : config.i18n.tipFullscreen;

		// Let CodeMirror measure its new size once the layout has changed.
		window.requestAnimationFrame( function () {
			if ( cm ) {
				cm.refresh();
			}
		} );
	}

	if ( fullscreenButton && wrapper ) {
		fullscreenButton.addEventListener( 'click', function () {
			setFullscreen( ! isFullscreen() );
		} );

		// Escape leaves fullscreen, unless it was just used to close a dialog.
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! event.defaultPrevented && isFullscreen() && ! ( quick.root && ! quick.root.hidden ) ) {
				setFullscreen( false );
			}
		} );
	}

	/* ---------- Remember the open project and files ---------- */

	var sessionKey = 'fsed_session_' + ( config.userId || 0 );

	function readSessions() {
		var data = null;

		try {
			data = JSON.parse( window.localStorage.getItem( sessionKey ) || 'null' );
		} catch ( e ) {
			data = null;
		}

		if ( ! data || 'object' !== typeof data || ! data.projects || 'object' !== typeof data.projects ) {
			data = { last: null, projects: {} };
		}

		return data;
	}

	function projectKey() {
		return state.type + '|' + state.slug;
	}

	/**
	 * Save the selected theme or plugin and its open files in this browser.
	 */
	function saveSession() {
		if ( ! sessionReady || ! state.slug ) {
			return;
		}

		var data = readSessions();
		var keys;

		data.last = { type: state.type, slug: state.slug };
		data.projects[ projectKey() ] = {
			files: tabs.map( function ( tab ) {
				return tab.path;
			} ),
			active: state.path
		};

		// Keep the list short.
		keys = Object.keys( data.projects );
		if ( keys.length > 30 ) {
			keys.slice( 0, keys.length - 30 ).forEach( function ( key ) {
				if ( key !== projectKey() ) {
					delete data.projects[ key ];
				}
			} );
		}

		try {
			window.localStorage.setItem( sessionKey, JSON.stringify( data ) );
		} catch ( e ) {
			// Storage can be blocked or full. The editor works without it.
		}
	}

	/**
	 * Open the main file of the theme or plugin.
	 *
	 * @param {Array} tree File tree.
	 */
	function openMainFile( tree ) {
		var main = mainFilePath();
		var node = findNode( tree, main );

		sessionReady = true;

		if ( node && 'file' === node.type && node.editable ) {
			openFile( main );
		} else {
			updateStatus();
		}
	}

	/**
	 * Reopen the files that were open last time. Falls back to the main file.
	 *
	 * @param {Array} tree File tree.
	 */
	function restoreSession( tree ) {
		var entry = readSessions().projects[ projectKey() ];
		var files = entry && Array.isArray( entry.files ) ? entry.files.filter( function ( path ) {
			var node = 'string' === typeof path ? findNode( tree, path ) : null;
			return node && 'file' === node.type && ( node.editable || node.image );
		} ).slice( 0, MAX_TABS ) : [];

		if ( ! files.length ) {
			openMainFile( tree );
			return;
		}

		var token = ++fileToken;
		var chain = Promise.resolve();

		fileLoading = true;
		updateStatus();

		// Read the files one after the other, so the tabs keep their order.
		files.forEach( function ( path ) {
			chain = chain.then( function () {
				if ( token !== fileToken ) {
					return null;
				}

				return request( 'read', { path: path } ).then( function ( data ) {
					if ( token === fileToken ) {
						addTab( data.path, data.content, !! data.writable && can( 'save' ), data.image );
					}
				}, function () {
					// The file may have been deleted or can no longer be read. Skip it.
				} );
			} );
		} );

		chain.then( function () {
			if ( token !== fileToken ) {
				return;
			}

			fileLoading = false;

			if ( ! tabs.length ) {
				openMainFile( tree );
				return;
			}

			var active = entry.active ? findTab( entry.active ) : null;

			if ( active ) {
				activateTab( active );
			}

			sessionReady = true;
			updateUI();
		} );
	}

	// Start with the theme or plugin that was open last time, unless the URL names one.
	if ( ! /[?&](type|slug)=/.test( window.location.search ) ) {
		var lastOpen = readSessions().last;

		if ( lastOpen && 'string' === typeof lastOpen.type && 'string' === typeof lastOpen.slug && ( config.items[ lastOpen.type ] || [] ).some( function ( item ) {
			return item.slug === lastOpen.slug;
		} ) ) {
			state.type = lastOpen.type;
			state.slug = lastOpen.slug;
			typeSelect.value = state.type;
			populateItems();
		}
	}

	setReadOnly( true );
	updateUI();
	loadTree();
}( window, document ) );
