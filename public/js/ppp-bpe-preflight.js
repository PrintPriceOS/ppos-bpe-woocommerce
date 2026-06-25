(function () {
	'use strict';

	var config = window.pppBpePreflight;
	if ( ! config ) {
		return;
	}

	var section   = document.getElementById( 'ppp-bpe-preflight-section' );
	var statusEl  = document.getElementById( 'ppp-bpe-preflight-status' );
	var messageEl = document.getElementById( 'ppp-bpe-preflight-message' );
	var reportEl  = document.getElementById( 'ppp-bpe-preflight-report' );
	var checksEl  = document.getElementById( 'ppp-bpe-preflight-checks' );
	var summaryEl = document.getElementById( 'ppp-bpe-preflight-summary' );
	var errorEl   = document.getElementById( 'ppp-bpe-preflight-error' );
	var startBtn  = document.getElementById( 'ppp-bpe-preflight-start' );

	if ( ! section ) {
		return;
	}

	var i18n = config.i18n;
	var pollTimer = null;

	/* ── Populate text ── */
	document.getElementById( 'ppp-bpe-preflight-title' ).textContent = i18n.title;
	document.getElementById( 'ppp-bpe-preflight-description' ).textContent = i18n.description;
	document.getElementById( 'ppp-bpe-preflight-report-title' ).textContent = i18n.reportTitle;

	/* ── Status rendering ── */
	var statusClasses = {
		none:                'ppp-bpe-preflight__status--none',
		preflight_pending:   'ppp-bpe-preflight__status--pending',
		preflight_passed:    'ppp-bpe-preflight__status--passed',
		preflight_warnings:  'ppp-bpe-preflight__status--warnings',
		preflight_blocked:   'ppp-bpe-preflight__status--blocked'
	};

	var statusLabels = {
		none:                '',
		preflight_pending:   i18n.statusPending,
		preflight_passed:    i18n.statusPassed,
		preflight_warnings:  i18n.statusWarnings,
		preflight_blocked:   i18n.statusBlocked
	};

	var statusMessages = {
		preflight_pending:   i18n.messagePending,
		preflight_passed:    i18n.messagePassed,
		preflight_warnings:  i18n.messageWarnings,
		preflight_blocked:   i18n.messageBlocked
	};

	function updateStatus( status ) {
		statusEl.textContent = statusLabels[ status ] || status;
		statusEl.className = 'ppp-bpe-preflight__status ' + ( statusClasses[ status ] || '' );

		if ( 'none' === status ) {
			statusEl.style.display = 'none';
		} else {
			statusEl.style.display = '';
		}

		var msg = statusMessages[ status ] || '';
		if ( msg ) {
			messageEl.textContent = msg;
			messageEl.style.display = '';
		} else {
			messageEl.style.display = 'none';
		}
	}

	/* ── Report rendering ── */
	function renderReport( report ) {
		if ( ! report || ! report.checks || report.checks.length === 0 ) {
			reportEl.style.display = 'none';
			return;
		}

		checksEl.innerHTML = '';

		var table = document.createElement( 'table' );
		table.className = 'ppp-bpe-preflight__table';

		var thead = document.createElement( 'thead' );
		var headRow = document.createElement( 'tr' );
		[ i18n.coverFile.replace( / PDF$/, '' ), '', '' ].forEach( function () {} );

		var headers = [ 'File', 'Check', 'Result' ];
		headers.forEach( function ( text, idx ) {
			var th = document.createElement( 'th' );
			th.textContent = idx === 0 ? i18n.interiorFile.replace( / PDF$/, '' ).replace( /.*/, text )
				: text;
			headRow.appendChild( th );
		});

		var thFile = document.createElement( 'th' );
		thFile.textContent = i18n.interiorFile.replace( / PDF$/, '' ).slice( 0, 0 ) || 'File';
		var thCheck = document.createElement( 'th' );
		thCheck.textContent = 'Check';
		var thResult = document.createElement( 'th' );
		thResult.textContent = 'Result';
		headRow.innerHTML = '';
		headRow.appendChild( thFile );
		headRow.appendChild( thCheck );
		headRow.appendChild( thResult );
		thead.appendChild( headRow );
		table.appendChild( thead );

		var tbody = document.createElement( 'tbody' );

		report.checks.forEach( function ( check ) {
			var row = document.createElement( 'tr' );

			var tdFile = document.createElement( 'td' );
			tdFile.textContent = check.file || '—';
			row.appendChild( tdFile );

			var tdName = document.createElement( 'td' );
			tdName.textContent = check.name || '—';
			row.appendChild( tdName );

			var tdSev = document.createElement( 'td' );
			var badge = document.createElement( 'span' );
			badge.className = 'ppp-bpe-preflight__severity ppp-bpe-preflight__severity--' + ( check.severity || 'info' );

			var sevLabels = {
				error:   i18n.severityError,
				warning: i18n.severityWarning,
				info:    i18n.severityInfo
			};
			badge.textContent = sevLabels[ check.severity ] || check.severity || 'Info';
			tdSev.appendChild( badge );

			if ( check.message ) {
				var msgSpan = document.createElement( 'span' );
				msgSpan.className = 'ppp-bpe-preflight__check-message';
				msgSpan.textContent = ' ' + check.message;
				tdSev.appendChild( msgSpan );
			}

			row.appendChild( tdSev );
			tbody.appendChild( row );
		});

		table.appendChild( tbody );
		checksEl.appendChild( table );

		if ( report.summary ) {
			summaryEl.textContent = report.summary;
			summaryEl.style.display = '';
		} else {
			summaryEl.style.display = 'none';
		}

		reportEl.style.display = '';
	}

	/* ── Button state ── */
	function updateButton( status ) {
		if ( 'preflight_pending' === status ) {
			startBtn.textContent = i18n.statusPending;
			startBtn.disabled = true;
			startBtn.style.display = '';
		} else if ( 'none' === status ) {
			startBtn.textContent = i18n.startButton;
			startBtn.disabled = false;
			startBtn.style.display = '';
		} else {
			startBtn.textContent = i18n.rerunButton;
			startBtn.disabled = false;
			startBtn.style.display = '';
		}
	}

	/* ── Error display ── */
	function showError( message ) {
		errorEl.textContent = message;
		errorEl.style.display = '';
	}

	function clearError() {
		errorEl.style.display = 'none';
		errorEl.textContent = '';
	}

	/* ── Polling ── */
	function startPolling() {
		stopPolling();
		pollTimer = setInterval( function () {
			pollStatus();
		}, config.pollInterval || 5000 );
	}

	function stopPolling() {
		if ( pollTimer ) {
			clearInterval( pollTimer );
			pollTimer = null;
		}
	}

	function pollStatus() {
		fetch( config.statusUrl, {
			method: 'GET',
			headers: { 'X-WP-Nonce': config.nonce }
		})
		.then( function ( response ) {
			return response.json();
		})
		.then( function ( data ) {
			if ( data.status && data.status !== 'preflight_pending' && data.status !== 'none' ) {
				stopPolling();
				updateStatus( data.status );
				updateButton( data.status );
				if ( data.report ) {
					renderReport( data.report );
				}
			}
		})
		.catch( function () {
			/* Silent — keep polling */
		});
	}

	/* ── Start preflight ── */
	startBtn.addEventListener( 'click', function () {
		clearError();
		startBtn.disabled = true;
		startBtn.textContent = i18n.startingButton;

		fetch( config.startUrl, {
			method: 'POST',
			headers: {
				'X-WP-Nonce': config.nonce,
				'Content-Type': 'application/json'
			},
			body: '{}'
		})
		.then( function ( response ) {
			return response.json().then( function ( body ) {
				return { ok: response.ok, body: body };
			});
		})
		.then( function ( result ) {
			if ( ! result.ok ) {
				showError( result.body.error || i18n.errorGeneric );
				startBtn.disabled = false;
				startBtn.textContent = i18n.startButton;
				return;
			}

			updateStatus( 'preflight_pending' );
			updateButton( 'preflight_pending' );
			reportEl.style.display = 'none';
			startPolling();
		})
		.catch( function () {
			showError( i18n.errorGeneric );
			startBtn.disabled = false;
			startBtn.textContent = i18n.startButton;
		});
	});

	/* ── Initial state ── */
	updateStatus( config.status );
	updateButton( config.status );

	if ( config.report ) {
		renderReport( config.report );
	}

	if ( 'preflight_pending' === config.status ) {
		startPolling();
	}

})();
