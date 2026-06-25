(function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.pppBpeQueue ) {
			return;
		}

		var config  = window.pppBpeQueue;
		var selects = document.querySelectorAll( '.ppp-bpe-status-select' );

		selects.forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				var orderId    = select.getAttribute( 'data-order-id' );
				var current    = select.getAttribute( 'data-current' );
				var newStatus  = select.value;
				var label      = select.options[ select.selectedIndex ].text;

				if ( newStatus === current ) {
					return;
				}

				var message = config.i18n.confirmChange.replace( '%s', label );
				if ( ! confirm( message ) ) {
					select.value = current;
					return;
				}

				var note = prompt( config.i18n.notePrompt ) || '';

				select.disabled = true;

				fetch( config.restUrl + '/' + orderId + '/status', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce
					},
					body: JSON.stringify( {
						status: newStatus,
						note: note
					} )
				} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					select.disabled = false;

					if ( ! result.ok ) {
						alert( result.data.error || config.i18n.error );
						select.value = current;
						return;
					}

					select.setAttribute( 'data-current', newStatus );

					var row   = select.closest( 'tr' );
					var badge = row.querySelector( '.ppp-bpe-production-badge' );
					if ( badge ) {
						badge.textContent = result.data.label;
						var colors = {
							new: '#6b7280',
							reviewing: '#2563eb',
							accepted: '#16a34a',
							in_prepress: '#7c3aed',
							in_production: '#d97706',
							completed: '#16a34a',
							shipped: '#059669',
							action_required: '#dc2626'
						};
						badge.style.background = colors[ newStatus ] || '#6b7280';
					}
				} )
				.catch( function () {
					select.disabled = false;
					alert( config.i18n.error );
					select.value = current;
				} );
			} );
		} );
	} );
})();
