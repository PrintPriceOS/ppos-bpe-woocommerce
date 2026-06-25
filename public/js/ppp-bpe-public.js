(function () {
	'use strict';

	var config = window.pppBpeCalc;
	if ( ! config ) {
		return;
	}

	var form       = document.getElementById( 'ppp-bpe-calc-form' );
	var resultsEl  = document.getElementById( 'ppp-bpe-calc-results' );
	var loadingEl  = document.getElementById( 'ppp-bpe-calc-loading' );
	var errorEl    = document.getElementById( 'ppp-bpe-calc-error' );
	var summaryEl  = document.getElementById( 'ppp-bpe-calc-summary' );
	var breakdownEl = document.getElementById( 'ppp-bpe-calc-breakdown' );
	var totalEl    = document.getElementById( 'ppp-bpe-calc-total' );
	var submitBtn  = document.getElementById( 'ppp-bpe-calc-submit' );
	var cartBtn    = document.getElementById( 'ppp-bpe-add-to-cart' );
	var cartMsgEl  = document.getElementById( 'ppp-bpe-cart-message' );

	if ( ! form ) {
		return;
	}

	var hasCalculated = false;
	var debounceTimer = null;
	var lastOffer     = null;

	/* ── Binding page-count rules (injected from PHP via data attribute or inline) ── */
	var bindingRules = {
		perfect:       { min_pages: 40 },
		saddle_stitch: { max_pages: 80 },
		hardcover:     { min_pages: 40 },
		spiral:        {}
	};

	/* ── Currency formatter ── */
	var formatter;
	try {
		formatter = new Intl.NumberFormat( undefined, {
			style: 'currency',
			currency: config.currency || 'EUR',
			minimumFractionDigits: 2
		});
	} catch ( e ) {
		formatter = {
			format: function ( n ) {
				return ( config.currency || 'EUR' ) + ' ' + n.toFixed( 2 );
			}
		};
	}

	/* ── Helpers ── */

	function getFormData() {
		var radios = form.querySelectorAll( 'input[type="radio"]' );
		var data = {
			book_size:      form.elements.book_size.value,
			pages:          parseInt( form.elements.pages.value, 10 ) || 0,
			copies:         parseInt( form.elements.copies.value, 10 ) || 0,
			interior_color: '',
			cover_color:    '',
			binding:        form.elements.binding.value,
			paper:          form.elements.paper.value,
			country:        form.elements.country.value
		};

		for ( var i = 0; i < radios.length; i++ ) {
			if ( radios[ i ].checked ) {
				data[ radios[ i ].name ] = radios[ i ].value;
			}
		}

		return data;
	}

	function validateClient( data ) {
		clearWarnings();

		if ( data.pages < 8 || data.pages > 1000 ) {
			return 'Pages must be between 8 and 1000.';
		}
		if ( data.pages % 2 !== 0 ) {
			return 'Page count must be even.';
		}
		if ( data.copies < 1 || data.copies > 10000 ) {
			return 'Copies must be between 1 and 10,000.';
		}

		var rule = bindingRules[ data.binding ];
		if ( rule ) {
			if ( rule.min_pages && data.pages < rule.min_pages ) {
				showFieldWarning( 'ppp-bpe-binding',
					form.elements.binding.options[ form.elements.binding.selectedIndex ].text +
					' requires at least ' + rule.min_pages + ' pages.' );
				return form.elements.binding.options[ form.elements.binding.selectedIndex ].text +
					' requires at least ' + rule.min_pages + ' pages.';
			}
			if ( rule.max_pages && data.pages > rule.max_pages ) {
				showFieldWarning( 'ppp-bpe-binding',
					form.elements.binding.options[ form.elements.binding.selectedIndex ].text +
					' supports a maximum of ' + rule.max_pages + ' pages.' );
				return form.elements.binding.options[ form.elements.binding.selectedIndex ].text +
					' supports a maximum of ' + rule.max_pages + ' pages.';
			}
		}

		return null;
	}

	function showFieldWarning( fieldId, message ) {
		var field = document.getElementById( fieldId );
		if ( ! field ) {
			return;
		}
		var parent = field.closest( '.ppp-bpe-calculator__field' );
		if ( ! parent ) {
			return;
		}
		var warning = document.createElement( 'div' );
		warning.className = 'ppp-bpe-field-warning';
		warning.textContent = message;
		parent.appendChild( warning );
	}

	function clearWarnings() {
		var warnings = form.querySelectorAll( '.ppp-bpe-field-warning' );
		for ( var i = 0; i < warnings.length; i++ ) {
			warnings[ i ].parentNode.removeChild( warnings[ i ] );
		}
	}

	function showLoading() {
		loadingEl.style.display = '';
		resultsEl.style.display = 'none';
		submitBtn.disabled = true;
		submitBtn.textContent = config.i18n.calculating;
	}

	function hideLoading() {
		loadingEl.style.display = 'none';
		submitBtn.disabled = false;
		submitBtn.textContent = config.i18n.calculate;
	}

	function showError( message ) {
		errorEl.textContent = message;
		errorEl.style.display = '';
	}

	function clearError() {
		errorEl.style.display = 'none';
		errorEl.textContent = '';
	}

	function resetCartButton() {
		cartBtn.disabled = false;
		cartBtn.textContent = config.i18n.addToCart;
		cartBtn.classList.remove( 'ppp-bpe-calculator__add-to-cart--success' );
		cartMsgEl.style.display = 'none';
		cartMsgEl.textContent = '';
		cartMsgEl.className = 'ppp-bpe-calculator__cart-message';
	}

	function renderResults( data ) {
		/* Summary */
		var specs = data.specs;
		summaryEl.textContent = specs.book_size + ' — ' +
			specs.pages + ' pages — ' +
			specs.interior_color + ' interior — ' +
			specs.cover_color + ' cover — ' +
			specs.binding + ' — ' +
			specs.paper + ' — ' +
			specs.copies + ( specs.copies === 1 ? ' copy' : ' copies' );

		/* Breakdown table */
		var tbody = breakdownEl.querySelector( 'tbody' );
		tbody.innerHTML = '';

		var rows = [
			[ 'Interior printing', data.breakdown.interior_cost ],
			[ 'Cover',             data.breakdown.cover_cost ],
			[ 'Binding',           data.breakdown.binding_cost ],
			[ 'Setup',             data.breakdown.setup_cost ],
			[ 'Unit price',        data.unit_price ]
		];

		for ( var i = 0; i < rows.length; i++ ) {
			var tr = document.createElement( 'tr' );
			var td1 = document.createElement( 'td' );
			td1.textContent = rows[ i ][ 0 ];
			var td2 = document.createElement( 'td' );
			td2.textContent = formatter.format( rows[ i ][ 1 ] );
			tr.appendChild( td1 );
			tr.appendChild( td2 );
			tbody.appendChild( tr );
		}

		/* Total row */
		var trTotal = document.createElement( 'tr' );
		trTotal.className = 'ppp-bpe-row-total';
		var tdLabel = document.createElement( 'td' );
		tdLabel.textContent = config.i18n.total + ' (' + data.copies + ( data.copies === 1 ? ' copy)' : ' copies)' );
		var tdValue = document.createElement( 'td' );
		tdValue.textContent = formatter.format( data.total );
		trTotal.appendChild( tdLabel );
		trTotal.appendChild( tdValue );
		tbody.appendChild( trTotal );

		/* Total display */
		totalEl.innerHTML = formatter.format( data.total ) +
			'<span class="ppp-bpe-unit-price">' +
			formatter.format( data.unit_price ) + ' ' + config.i18n.perCopy +
			'</span>';

		/* Source badge for fallback visibility */
		var sourceEl = resultsEl.querySelector( '.ppp-bpe-source-badge' );
		if ( data.source === 'local_fallback' ) {
			if ( ! sourceEl ) {
				sourceEl = document.createElement( 'div' );
				sourceEl.className = 'ppp-bpe-source-badge ppp-bpe-source-fallback';
				totalEl.parentNode.insertBefore( sourceEl, totalEl.nextSibling );
			}
			sourceEl.textContent = config.i18n.fallbackNotice || 'Estimated price (service temporarily unavailable)';
			sourceEl.style.display = '';
		} else if ( sourceEl ) {
			sourceEl.style.display = 'none';
		}

		/* Enable add-to-cart */
		resetCartButton();

		resultsEl.style.display = '';
	}

	/* ── Calculate via REST ── */

	function doCalculate() {
		clearError();
		var data = getFormData();
		var validationError = validateClient( data );

		if ( validationError ) {
			showError( validationError );
			return;
		}

		showLoading();

		fetch( config.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   config.nonce
			},
			body: JSON.stringify( data )
		})
		.then( function ( response ) {
			return response.json().then( function ( body ) {
				return { ok: response.ok, body: body };
			});
		})
		.then( function ( result ) {
			hideLoading();
			if ( ! result.ok ) {
				if ( result.body.limit_reached ) {
					showError( config.i18n.limitReached || result.body.errors.join( '. ' ) );
					submitBtn.disabled = true;
					return;
				}
				var msg = result.body.errors
					? result.body.errors.join( '. ' )
					: config.i18n.errorGeneric;
				showError( msg );
				return;
			}
			hasCalculated = true;
			lastOffer = {
				data:      result.body,
				signature: result.body.offer_signature || '',
				source:    result.body.source || 'local'
			};
			renderResults( result.body );
		})
		.catch( function () {
			hideLoading();
			showError( config.i18n.errorGeneric );
		});
	}

	/* ── Add to Cart via REST ── */

	function doAddToCart() {
		if ( ! lastOffer || ! lastOffer.signature ) {
			return;
		}

		cartBtn.disabled = true;
		cartBtn.textContent = config.i18n.addingToCart;
		cartMsgEl.style.display = 'none';

		fetch( config.addToCartUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   config.nonce
			},
			body: JSON.stringify({
				offer:     lastOffer.data,
				signature: lastOffer.signature
			})
		})
		.then( function ( response ) {
			return response.json().then( function ( body ) {
				return { ok: response.ok, body: body };
			});
		})
		.then( function ( result ) {
			if ( ! result.ok ) {
				cartBtn.disabled = false;
				cartBtn.textContent = config.i18n.addToCart;
				cartMsgEl.className = 'ppp-bpe-calculator__cart-message ppp-bpe-calculator__cart-message--error';
				cartMsgEl.textContent = result.body.error || config.i18n.cartError;
				cartMsgEl.style.display = '';
				return;
			}

			cartBtn.textContent = config.i18n.addedToCart;
			cartBtn.classList.add( 'ppp-bpe-calculator__add-to-cart--success' );

			cartMsgEl.className = 'ppp-bpe-calculator__cart-message ppp-bpe-calculator__cart-message--success';
			cartMsgEl.innerHTML = '<a href="' + result.body.cart_url + '">' + config.i18n.viewCart + '</a>';
			cartMsgEl.style.display = '';

			lastOffer = null;
		})
		.catch( function () {
			cartBtn.disabled = false;
			cartBtn.textContent = config.i18n.addToCart;
			cartMsgEl.className = 'ppp-bpe-calculator__cart-message ppp-bpe-calculator__cart-message--error';
			cartMsgEl.textContent = config.i18n.cartError;
			cartMsgEl.style.display = '';
		});
	}

	/* ── Branding ── */

	function renderBranding( show ) {
		var container = document.getElementById( 'printpricepro-bpe-calculator' );
		if ( ! container ) { return; }

		var existing = container.querySelector( '.ppp-bpe-branding' );
		if ( show && ! existing ) {
			var el = document.createElement( 'div' );
			el.className = 'ppp-bpe-branding';
			el.innerHTML = 'Powered by <a href="https://printpricepro.com" target="_blank" rel="noopener">PrintPricePro</a>';
			container.appendChild( el );
		} else if ( ! show && existing ) {
			existing.parentNode.removeChild( existing );
		}
	}

	if ( config.showBranding ) {
		renderBranding( true );
	}

	/* ── Event listeners ── */

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		doCalculate();
	});

	cartBtn.addEventListener( 'click', function () {
		doAddToCart();
	});

	/* Auto-recalculate on field change after first calculation */
	var inputs = form.querySelectorAll( 'select, input[type="number"], input[type="radio"]' );
	for ( var i = 0; i < inputs.length; i++ ) {
		inputs[ i ].addEventListener( 'change', function () {
			if ( ! hasCalculated ) {
				return;
			}
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( doCalculate, 500 );
		});

		if ( inputs[ i ].type === 'number' ) {
			inputs[ i ].addEventListener( 'input', function () {
				if ( ! hasCalculated ) {
					return;
				}
				clearTimeout( debounceTimer );
				debounceTimer = setTimeout( doCalculate, 500 );
			});
		}
	}

})();
