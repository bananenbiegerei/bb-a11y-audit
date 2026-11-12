( function () {
	'use strict';

	var restUrl = BBA11yData.restUrl;
	var nonce   = BBA11yData.nonce;

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

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str == null ? '' : str;
		return div.innerHTML;
	}

	function renderLocations( locations ) {
		var body = document.getElementById( 'bb-a11y-locations-body' );
		if ( ! locations.length ) {
			body.innerHTML = '<tr><td colspan="5">No locations yet. Add one below or use Auto-suggest.</td></tr>';
			return;
		}

		body.innerHTML = locations
			.map( function ( loc ) {
				return (
					'<tr data-id="' + loc.id + '">' +
					'<td>' + escapeHtml( loc.slug ) + '</td>' +
					'<td>' + escapeHtml( loc.label ) + '</td>' +
					'<td><a href="' + escapeHtml( loc.url ) + '" target="_blank" rel="noopener">' + escapeHtml( loc.url ) + '</a></td>' +
					'<td>' + escapeHtml( loc.default_selector ) + '</td>' +
					'<td>' +
					'<a class="button button-small" href="admin.php?page=bb-a11y-audit-scan&location_id=' + loc.id + '">Scan</a> ' +
					'<a class="button button-small" href="admin.php?page=bb-a11y-audit-history&location_id=' + loc.id + '">History</a> ' +
					'<button class="button button-small bb-a11y-delete-location" data-id="' + loc.id + '">Delete</button>' +
					'</td>' +
					'</tr>'
				);
			} )
			.join( '' );

		body.querySelectorAll( '.bb-a11y-delete-location' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Delete this location and all of its scan history?' ) ) {
					return;
				}
				apiFetch( '/locations/' + btn.dataset.id, { method: 'DELETE' } ).then( loadLocations );
			} );
		} );
	}

	function loadLocations() {
		apiFetch( '/locations' ).then( renderLocations );
	}

	function renderSuggestions( data ) {
		var container  = document.getElementById( 'bb-a11y-suggestions' );
		var special    = data.special || [];
		var templates  = data.templates || [];
		var postTypes  = data.post_types || [];

		if ( ! special.length && ! templates.length && ! postTypes.length ) {
			container.innerHTML = '<p>No suggestions — every detected template/post type already has a location.</p>';
			return;
		}

		var selectable = [];

		function checkboxRow( entry, extraLabel ) {
			var index = selectable.length;
			selectable.push( { slug: entry.slug, label: entry.label, url: entry.url } );
			return (
				'<tr>' +
				'<td><input type="checkbox" class="bb-a11y-suggestion-check" data-index="' + index + '" checked /></td>' +
				'<td>' + escapeHtml( entry.slug ) + '</td>' +
				'<td>' + escapeHtml( entry.label ) + ( extraLabel ? ' <span class="description">(' + escapeHtml( extraLabel ) + ')</span>' : '' ) + '</td>' +
				'<td><a href="' + escapeHtml( entry.url ) + '" target="_blank" rel="noopener">' + escapeHtml( entry.url ) + '</a></td>' +
				'</tr>'
			);
		}

		var html = '';

		if ( special.length ) {
			html +=
				'<h3>Special Locations</h3>' +
				'<table class="widefat striped"><thead><tr><th></th><th>Slug</th><th>Label</th><th>URL</th></tr></thead><tbody>' +
				special.map( function ( entry ) { return checkboxRow( entry ); } ).join( '' ) +
				'</tbody></table>';
		}

		if ( templates.length ) {
			html +=
				'<h3>Page Templates (active theme)</h3>' +
				'<table class="widefat striped"><thead><tr><th></th><th>Slug</th><th>Page</th><th>URL</th></tr></thead><tbody>' +
				templates
					.map( function ( entry ) {
						if ( ! entry.slug ) {
							return (
								'<tr>' +
								'<td></td>' +
								'<td colspan="3"><em>' + escapeHtml( entry.template_name ) + '</em> — no published page uses this template yet</td>' +
								'</tr>'
							);
						}
						return checkboxRow( entry, entry.template_name );
					} )
					.join( '' ) +
				'</tbody></table>';
		}

		if ( postTypes.length ) {
			html +=
				'<h3>Custom Post Types</h3>' +
				'<table class="widefat striped"><thead><tr><th></th><th>Slug</th><th>Label</th><th>URL</th></tr></thead><tbody>' +
				postTypes.map( function ( entry ) { return checkboxRow( entry ); } ).join( '' ) +
				'</tbody></table>';
		}

		html += '<p><button class="button button-primary" id="bb-a11y-accept-suggestions">Add Selected</button></p>';

		container.innerHTML = html;

		document.getElementById( 'bb-a11y-accept-suggestions' ).addEventListener( 'click', function () {
			var checked = Array.prototype.slice
				.call( container.querySelectorAll( '.bb-a11y-suggestion-check:checked' ) )
				.map( function ( cb ) {
					return selectable[ cb.dataset.index ];
				} );

			if ( ! checked.length ) {
				return;
			}

			Promise.all(
				checked.map( function ( s ) {
					return apiFetch( '/locations', {
						method: 'POST',
						body: JSON.stringify( Object.assign( {}, s, { source: 'auto' } ) ),
					} );
				} )
			).then( function () {
				container.innerHTML = '<p>Added.</p>';
				loadLocations();
			} );
		} );
	}

	document.getElementById( 'bb-a11y-fetch-suggestions' ).addEventListener( 'click', function () {
		document.getElementById( 'bb-a11y-suggestions' ).innerHTML = '<p>Loading…</p>';
		apiFetch( '/locations/suggestions' ).then( renderSuggestions );
	} );

	document.getElementById( 'bb-a11y-add-location' ).addEventListener( 'click', function () {
		var slug  = document.getElementById( 'bb-a11y-new-slug' ).value.trim();
		var label = document.getElementById( 'bb-a11y-new-label' ).value.trim();
		var url   = document.getElementById( 'bb-a11y-new-url' ).value.trim();
		var selector = document.getElementById( 'bb-a11y-new-selector' ).value.trim();

		if ( ! slug || ! url ) {
			window.alert( 'Slug and URL are required.' );
			return;
		}

		apiFetch( '/locations', {
			method: 'POST',
			body: JSON.stringify( { slug: slug, label: label || slug, url: url, default_selector: selector } ),
		} )
			.then( function () {
				document.getElementById( 'bb-a11y-new-slug' ).value = '';
				document.getElementById( 'bb-a11y-new-label' ).value = '';
				document.getElementById( 'bb-a11y-new-url' ).value = '';
				document.getElementById( 'bb-a11y-new-selector' ).value = '';
				loadLocations();
			} )
			.catch( function ( err ) {
				window.alert( err.message );
			} );
	} );

	loadLocations();
} )();
