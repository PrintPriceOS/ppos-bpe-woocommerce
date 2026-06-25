(function () {
	'use strict';

	var config = window.pppBpeUpload;
	if ( ! config ) {
		return;
	}

	var form      = document.getElementById( 'ppp-bpe-upload-form' );
	var submitBtn = document.getElementById( 'ppp-bpe-upload-submit' );
	var errorEl   = document.getElementById( 'ppp-bpe-upload-error' );
	var successEl = document.getElementById( 'ppp-bpe-upload-success' );
	var statusEl  = document.getElementById( 'ppp-bpe-upload-status' );

	if ( ! form ) {
		return;
	}

	/* ── Populate i18n text ── */
	var i18n = config.i18n;
	document.getElementById( 'ppp-bpe-upload-title' ).textContent = i18n.title;
	document.getElementById( 'ppp-bpe-upload-description' ).textContent = i18n.description;
	document.getElementById( 'ppp-bpe-interior-label' ).textContent = i18n.interiorLabel;
	document.getElementById( 'ppp-bpe-cover-label' ).textContent = i18n.coverLabel;
	document.getElementById( 'ppp-bpe-interior-droptext' ).textContent = i18n.dragDrop;
	document.getElementById( 'ppp-bpe-cover-droptext' ).textContent = i18n.dragDrop;
	document.getElementById( 'ppp-bpe-interior-info' ).textContent = i18n.pdfOnly + ' — ' + i18n.maxSize;
	document.getElementById( 'ppp-bpe-cover-info' ).textContent = i18n.pdfOnly + ' — ' + i18n.maxSize;
	submitBtn.textContent = i18n.upload;

	/* ── File state ── */
	var selectedFiles = { interior_pdf: null, cover_pdf: null };

	/* ── Show existing files ── */
	function showExistingFile( type, fileData ) {
		var infoEl = document.getElementById( 'ppp-bpe-' + type + '-file-info' );
		if ( ! infoEl ) {
			return;
		}
		infoEl.textContent = i18n.uploaded + ': ' + fileData.filename + ' (' + fileData.size + ')';
		infoEl.style.display = '';

		var dropzone = document.getElementById( 'ppp-bpe-' + type + '-dropzone' );
		dropzone.classList.add( 'ppp-bpe-upload__dropzone--has-file' );

		var textEl = document.getElementById( 'ppp-bpe-' + type + '-droptext' );
		textEl.textContent = i18n.replace;
	}

	if ( config.interior ) {
		showExistingFile( 'interior', config.interior );
	}
	if ( config.cover ) {
		showExistingFile( 'cover', config.cover );
	}

	/* ── Status display ── */
	function updateStatus( status ) {
		var labels = {};
		labels.files_required = i18n.statusRequired;
		labels.files_uploaded = i18n.statusUploaded;
		labels.files_rejected = i18n.statusRejected;

		var classes = {};
		classes.files_required = 'ppp-bpe-upload__status--required';
		classes.files_uploaded = 'ppp-bpe-upload__status--uploaded';
		classes.files_rejected = 'ppp-bpe-upload__status--rejected';

		statusEl.textContent = labels[ status ] || status;
		statusEl.className = 'ppp-bpe-upload__status ' + ( classes[ status ] || '' );
	}

	updateStatus( config.status );

	/* ── Validate a single file client-side ── */
	function validateFile( file ) {
		if ( ! file ) {
			return null;
		}
		if ( file.type !== 'application/pdf' ) {
			return i18n.errorType;
		}
		if ( file.size > config.maxSize ) {
			return i18n.errorSize;
		}
		return null;
	}

	/* ── Dropzone setup ── */
	function setupDropzone( dropzoneId, inputId, field ) {
		var dropzone = document.getElementById( dropzoneId );
		var input    = document.getElementById( inputId );
		var textEl   = dropzone.querySelector( '.ppp-bpe-upload__dropzone-text' );

		dropzone.addEventListener( 'click', function () {
			input.click();
		});

		dropzone.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			dropzone.classList.add( 'ppp-bpe-upload__dropzone--active' );
		});

		dropzone.addEventListener( 'dragleave', function () {
			dropzone.classList.remove( 'ppp-bpe-upload__dropzone--active' );
		});

		dropzone.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			dropzone.classList.remove( 'ppp-bpe-upload__dropzone--active' );

			var files = e.dataTransfer.files;
			if ( files.length > 0 ) {
				var error = validateFile( files[0] );
				if ( error ) {
					showError( error );
					return;
				}
				clearError();
				input.files = files;
				selectedFiles[ field ] = files[0];
				textEl.textContent = files[0].name;
				dropzone.classList.add( 'ppp-bpe-upload__dropzone--has-file' );
				updateSubmitState();
			}
		});

		input.addEventListener( 'change', function () {
			if ( input.files.length > 0 ) {
				var error = validateFile( input.files[0] );
				if ( error ) {
					showError( error );
					input.value = '';
					return;
				}
				clearError();
				selectedFiles[ field ] = input.files[0];
				textEl.textContent = input.files[0].name;
				dropzone.classList.add( 'ppp-bpe-upload__dropzone--has-file' );
				updateSubmitState();
			}
		});
	}

	setupDropzone( 'ppp-bpe-interior-dropzone', 'ppp-bpe-interior-input', 'interior_pdf' );
	setupDropzone( 'ppp-bpe-cover-dropzone', 'ppp-bpe-cover-input', 'cover_pdf' );

	/* ── Helpers ── */
	function updateSubmitState() {
		submitBtn.disabled = ! selectedFiles.interior_pdf && ! selectedFiles.cover_pdf;
	}

	function showError( message ) {
		errorEl.textContent = message;
		errorEl.style.display = '';
		successEl.style.display = 'none';
	}

	function clearError() {
		errorEl.style.display = 'none';
		errorEl.textContent = '';
	}

	function showSuccess( message ) {
		successEl.textContent = message;
		successEl.style.display = '';
		errorEl.style.display = 'none';
	}

	/* ── Submit ── */
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		if ( ! selectedFiles.interior_pdf && ! selectedFiles.cover_pdf ) {
			return;
		}

		clearError();
		submitBtn.disabled = true;
		submitBtn.textContent = i18n.uploading;

		var formData = new FormData();
		if ( selectedFiles.interior_pdf ) {
			formData.append( 'interior_pdf', selectedFiles.interior_pdf );
		}
		if ( selectedFiles.cover_pdf ) {
			formData.append( 'cover_pdf', selectedFiles.cover_pdf );
		}

		fetch( config.uploadUrl, {
			method: 'POST',
			headers: {
				'X-WP-Nonce': config.nonce
			},
			body: formData
		})
		.then( function ( response ) {
			return response.json().then( function ( body ) {
				return { ok: response.ok, status: response.status, body: body };
			});
		})
		.then( function ( result ) {
			submitBtn.textContent = i18n.upload;

			if ( ! result.ok && result.status !== 207 ) {
				var errorMsg = '';
				if ( result.body.errors ) {
					var msgs = [];
					for ( var key in result.body.errors ) {
						if ( result.body.errors.hasOwnProperty( key ) ) {
							msgs.push( result.body.errors[ key ] );
						}
					}
					errorMsg = msgs.join( ' ' );
				} else {
					errorMsg = result.body.error || i18n.errorGeneric;
				}
				showError( errorMsg );
				submitBtn.disabled = false;
				return;
			}

			if ( result.body.uploaded ) {
				if ( result.body.uploaded.interior_pdf ) {
					showExistingFile( 'interior', {
						filename: result.body.uploaded.interior_pdf.filename,
						size: formatBytes( result.body.uploaded.interior_pdf.size )
					});
					selectedFiles.interior_pdf = null;
				}
				if ( result.body.uploaded.cover_pdf ) {
					showExistingFile( 'cover', {
						filename: result.body.uploaded.cover_pdf.filename,
						size: formatBytes( result.body.uploaded.cover_pdf.size )
					});
					selectedFiles.cover_pdf = null;
				}
			}

			if ( result.body.status ) {
				updateStatus( result.body.status );
			}

			if ( result.body.errors ) {
				var partialErrors = [];
				for ( var k in result.body.errors ) {
					if ( result.body.errors.hasOwnProperty( k ) ) {
						partialErrors.push( result.body.errors[ k ] );
					}
				}
				showError( partialErrors.join( ' ' ) );
			} else {
				if ( result.body.status === 'files_uploaded' ) {
					showSuccess( i18n.allUploaded );
				} else {
					showSuccess( i18n.success );
				}
			}

			updateSubmitState();
		})
		.catch( function () {
			submitBtn.textContent = i18n.upload;
			submitBtn.disabled = false;
			showError( i18n.errorGeneric );
		});
	});

	function formatBytes( bytes ) {
		if ( bytes === 0 ) {
			return '0 B';
		}
		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
		return ( bytes / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + units[ i ];
	}

})();
