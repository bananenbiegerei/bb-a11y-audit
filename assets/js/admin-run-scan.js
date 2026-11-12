( function () {
	'use strict';

	var restUrl     = BBA11yData.restUrl;
	var nonce       = BBA11yData.nonce;
	var axeAssetUrl = BBA11yData.axeAssetUrl;
	var scanConfig  = BBA11yData.scanConfig || {};
	var defaultTags = ( scanConfig.tags && scanConfig.tags.length ) ? scanConfig.tags : ( BBA11yData.defaultTags || [] );
	var ruleOverrides = scanConfig.rules || {};

	var iframe = document.getElementById( 'bb-a11y-scan-frame' );

	function apiFetch( path, options ) {
		options = options || {};
		options.headers = Object.assign( {}, options.headers, {
			'X-WP-Nonce': nonce,
			'Content-Type': 'application/json',
		} );
		return fetch( restUrl + path, options ).then( function ( response ) {
			if ( ! response.ok ) {
				return response.json().then( function ( body ) {
					throw new Error( body.message || 'Request failed' );
				} );
			}
			return response.json();
		} );
	}

	/**
	 * Load a URL into the shared iframe and resolve once its DOM is
	 * confirmed accessible and non-empty, or reject with a human-readable
	 * reason (network timeout, or the page blocking iframe embedding via
	 * X-Frame-Options / CSP frame-ancestors).
	 */
	function loadIntoFrame( url ) {
		return new Promise( function ( resolve, reject ) {
			var timeoutId = window.setTimeout( function () {
				iframe.onload = null;
				reject( new Error( 'Timed out loading the page.' ) );
			}, 8000 );

			iframe.onload = function () {
				window.clearTimeout( timeoutId );

				var doc;
				try {
					doc = iframe.contentDocument;
				} catch ( e ) {
					reject( new Error( 'Could not access the page — likely blocked by X-Frame-Options / CSP frame-ancestors.' ) );
					return;
				}

				if ( ! doc || ! doc.body || ! doc.body.children.length ) {
					reject( new Error( 'Page loaded but appears empty — likely blocked by X-Frame-Options / CSP frame-ancestors.' ) );
					return;
				}

				resolve( doc );
			};

			iframe.src = url;
		} );
	}

	/**
	 * Inject axe-core into an already-loaded iframe document and run it.
	 */
	function runAxeScan( doc, selector ) {
		return new Promise( function ( resolve, reject ) {
			var script = doc.createElement( 'script' );
			script.src = axeAssetUrl;

			script.onload = function () {
				var context = selector ? { include: [ [ selector ] ] } : doc;
				var options = {
					runOnly: { type: 'tag', values: defaultTags },
				};

				if ( ruleOverrides && Object.keys( ruleOverrides ).length ) {
					options.rules = ruleOverrides;
				}

				iframe.contentWindow.axe
					.run( context, options )
					.then( resolve )
					.catch( reject );
			};
			script.onerror = function () {
				reject( new Error( 'Could not load axe-core inside the page.' ) );
			};

			doc.head.appendChild( script );
		} );
	}

	function saveScan( locationId, selector, url, results ) {
		return apiFetch( '/scans', {
			method: 'POST',
			body: JSON.stringify( {
				location_id: locationId,
				selector: selector,
				url: url,
				axe_version: results.testEngine.version,
				tags_used: defaultTags,
				results: results,
			} ),
		} );
	}

	/* ---- Batch scan ---- */

	var selectAll    = document.getElementById( 'bb-a11y-select-all-locations' );
	var scanSelected = document.getElementById( 'bb-a11y-scan-selected' );
	var batchStatus  = document.getElementById( 'bb-a11y-batch-status' );

	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			document.querySelectorAll( '.bb-a11y-batch-check' ).forEach( function ( cb ) {
				cb.checked = selectAll.checked;
			} );
		} );
	}

	function setRowStatus( row, text ) {
		row.querySelector( '.bb-a11y-batch-row-status' ).textContent = text;
	}

	async function runBatch( rows ) {
		for ( var i = 0; i < rows.length; i++ ) {
			var row      = rows[ i ].closest( 'tr' );
			var id       = rows[ i ].dataset.id;
			var url      = rows[ i ].dataset.url;
			var selector = rows[ i ].dataset.selector || '';

			batchStatus.textContent = 'Scanning ' + ( i + 1 ) + ' of ' + rows.length + '…';
			setRowStatus( row, 'Loading…' );

			try {
				var doc = await loadIntoFrame( url );
				setRowStatus( row, 'Scanning…' );
				var results = await runAxeScan( doc, selector );
				setRowStatus( row, 'Saving…' );
				await saveScan( id, selector, url, results );
				setRowStatus(
					row,
					results.violations.length
						? '✓ Saved — ' + results.violations.length + ' rule(s) failing'
						: '✓ Saved — pass'
				);
			} catch ( err ) {
				setRowStatus( row, 'Error: ' + err.message );
			}
		}

		batchStatus.textContent = 'Batch scan complete.';
	}

	if ( scanSelected ) {
		scanSelected.addEventListener( 'click', function () {
			var checked = Array.prototype.slice.call( document.querySelectorAll( '.bb-a11y-batch-check:checked' ) );

			if ( ! checked.length ) {
				batchStatus.textContent = 'Select at least one location first.';
				return;
			}

			scanSelected.disabled = true;
			runBatch( checked ).finally( function () {
				scanSelected.disabled = false;
			} );
		} );
	}

	/* ---- Manual / single scan ---- */

	var select        = document.getElementById( 'bb-a11y-location-select' );
	var selectorInput = document.getElementById( 'bb-a11y-selector-input' );
	var loadBtn       = document.getElementById( 'bb-a11y-load-page' );
	var scanBtn       = document.getElementById( 'bb-a11y-run-scan' );
	var saveBtn       = document.getElementById( 'bb-a11y-save-scan' );
	var status        = document.getElementById( 'bb-a11y-scan-status' );
	var preview       = document.getElementById( 'bb-a11y-scan-preview' );

	var lastResults = null;
	var lastDoc     = null;

	function setStatus( message, isError ) {
		status.textContent = message || '';
		status.style.color = isError ? '#b32d2e' : '';
	}

	select.addEventListener( 'change', function () {
		var option = select.options[ select.selectedIndex ];
		loadBtn.disabled = ! option.value;
		scanBtn.disabled = true;
		saveBtn.disabled = true;
		preview.innerHTML = '';
		setStatus( '' );

		selectorInput.value = option.dataset.selector || '';
	} );

	if ( select.value ) {
		select.dispatchEvent( new Event( 'change' ) );
	}

	loadBtn.addEventListener( 'click', function () {
		var option = select.options[ select.selectedIndex ];
		if ( ! option.value ) {
			return;
		}

		scanBtn.disabled = true;
		saveBtn.disabled = true;
		setStatus( 'Loading page…' );

		loadIntoFrame( option.dataset.url )
			.then( function ( doc ) {
				lastDoc = doc;
				setStatus( 'Page loaded. Ready to scan.' );
				scanBtn.disabled = false;
			} )
			.catch( function ( err ) {
				setStatus( err.message, true );
			} );
	} );

	scanBtn.addEventListener( 'click', function () {
		setStatus( 'Running scan…' );
		saveBtn.disabled = true;

		runAxeScan( lastDoc, selectorInput.value.trim() )
			.then( function ( results ) {
				lastResults = results;
				renderPreview( results );
				setStatus( 'Scan complete.' );
				saveBtn.disabled = false;
			} )
			.catch( function ( err ) {
				setStatus( 'Scan failed: ' + err.message, true );
			} );
	} );

	function renderPreview( results ) {
		var byImpact = { critical: 0, serious: 0, moderate: 0, minor: 0 };
		results.violations.forEach( function ( v ) {
			if ( byImpact.hasOwnProperty( v.impact ) ) {
				byImpact[ v.impact ]++;
			}
		} );

		if ( ! results.violations.length ) {
			preview.innerHTML = '<p><strong>✓ Pass</strong> — no violations found.</p>';
			return;
		}

		var rows = results.violations
			.map( function ( v ) {
				return (
					'<tr><td>' + v.id + '</td><td>' + v.impact + '</td><td>' + v.nodes.length + '</td><td>' + v.help + '</td></tr>'
				);
			} )
			.join( '' );

		preview.innerHTML =
			'<p>' +
			results.violations.length +
			' rule(s) failing — critical: ' +
			byImpact.critical +
			', serious: ' +
			byImpact.serious +
			', moderate: ' +
			byImpact.moderate +
			', minor: ' +
			byImpact.minor +
			'</p>' +
			'<table class="widefat striped"><thead><tr><th>Rule</th><th>Impact</th><th>Instances</th><th>Help</th></tr></thead><tbody>' +
			rows +
			'</tbody></table>';
	}

	saveBtn.addEventListener( 'click', function () {
		if ( ! lastResults ) {
			return;
		}

		var option = select.options[ select.selectedIndex ];

		setStatus( 'Saving…' );
		saveBtn.disabled = true;

		saveScan( option.value, selectorInput.value.trim(), option.dataset.url, lastResults )
			.then( function () {
				setStatus( 'Saved.' );
			} )
			.catch( function ( err ) {
				setStatus( 'Save failed: ' + err.message, true );
				saveBtn.disabled = false;
			} );
	} );
} )();
