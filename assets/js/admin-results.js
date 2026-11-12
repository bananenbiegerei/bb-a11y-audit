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

	function noteEndpoint( row ) {
		if ( 'pass' === row.status ) {
			return '/scans/' + row.scan_id;
		}
		if ( 'violation' === row.status ) {
			return '/violations/' + row.violation_id;
		}
		return null;
	}

	function renderRows( rows ) {
		var body = document.getElementById( 'bb-a11y-results-body' );

		if ( ! rows.length ) {
			body.innerHTML = '<tr><td colspan="7">No locations yet. Add some on the Locations page.</td></tr>';
			return;
		}

		body.innerHTML = rows
			.map( function ( row ) {
				var ruleId = '';
				var impact = '';
				var wcag = '';
				var description = '';
				var note = '';
				var editable = false;

				if ( 'pass' === row.status ) {
					ruleId = '✓ PASS';
					description = 'No accessibility issues found';
					note = row.note || '';
					editable = true;
				} else if ( 'violation' === row.status ) {
					ruleId = row.rule_id;
					impact = row.impact ? row.impact.toUpperCase() : '';
					wcag = row.wcag || '';
					description = row.instance_count + ' instance' + ( 1 === row.instance_count ? '' : 's' ) + ' - ' + ( row.description || '' );
					note = row.note || '';
					editable = true;
				}

				var actions =
					'<a class="button button-small" href="admin.php?page=bb-a11y-audit-scan&location_id=' + row.location_id + '">Re-scan</a> ' +
					'<a class="button button-small" href="admin.php?page=bb-a11y-audit-history&location_id=' + row.location_id + '">History</a>';

				var noteCell = editable
					? '<textarea class="bb-a11y-note-input" rows="2">' + escapeHtml( note ) + '</textarea>'
					: '';

				return (
					'<tr data-status="' + row.status + '" data-scan-id="' + ( row.scan_id || '' ) + '" data-violation-id="' + ( row.violation_id || '' ) + '">' +
					'<td>' + escapeHtml( row.slug ) + '</td>' +
					'<td>' + escapeHtml( ruleId ) + '</td>' +
					'<td>' + escapeHtml( impact ) + '</td>' +
					'<td>' + escapeHtml( wcag ) + '</td>' +
					'<td>' + escapeHtml( description ) + '</td>' +
					'<td>' + noteCell + '</td>' +
					'<td>' + actions + '</td>' +
					'</tr>'
				);
			} )
			.join( '' );

		body.querySelectorAll( '.bb-a11y-note-input' ).forEach( function ( textarea ) {
			textarea.addEventListener( 'blur', function () {
				var tr  = textarea.closest( 'tr' );
				var row = {
					status: tr.dataset.status,
					scan_id: tr.dataset.scanId,
					violation_id: tr.dataset.violationId,
				};
				var endpoint = noteEndpoint( row );
				if ( ! endpoint ) {
					return;
				}
				apiFetch( endpoint, {
					method: 'PATCH',
					body: JSON.stringify( { note: textarea.value } ),
				} ).catch( function ( err ) {
					window.alert( 'Failed to save note: ' + err.message );
				} );
			} );
		} );
	}

	apiFetch( '/results' ).then( renderRows );
} )();
