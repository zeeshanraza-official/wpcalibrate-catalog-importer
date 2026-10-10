/**
 * JavaScript Controller for WPCalibrate Catalog Importer Admin.
 */
( function( $ ) {
	'use strict';

	$( function() {
		// 1. Template Loader in Step 3.
		$( '#apply_saved_template' ).on( 'change', function() {
			var $selected = $( this ).find( 'option:selected' );
			var mappingJson = $selected.data( 'mappings' );

			if ( ! mappingJson || typeof mappingJson !== 'object' ) {
				return;
			}

			$.each( mappingJson, function( colName, destField ) {
				var $select = $( 'select[name="mapping[' + CSS.escape( colName ) + ']"]' );
				if ( $select.length ) {
					$select.val( destField ).trigger( 'change' );
				}
			} );
		} );

		// 2. Step 6: Live Progress Polling.
		var $progressContainer = $( '.wpcalibrate-progress-container' );
		if ( $progressContainer.length ) {
			var jobId = $progressContainer.data( 'job-id' );
			var pollInterval = null;

			function pollJobStatus() {
				$.ajax( {
					url: wpcalibrateImporter.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'wpcalibrate_importer_get_progress',
						job_id: jobId,
						nonce: wpcalibrateImporter.nonce
					},
					success: function( response ) {
						if ( ! response || ! response.success ) {
							return;
						}

						var data = response.data;
						var pct = parseInt( data.progress, 10 ) || 0;

						// Update progress bar width.
						$( '.wpcalibrate-progress-bar' ).css( 'width', pct + '%' );

						// Update text.
						$( '.progress-status-text' ).text(
							'Processed ' + data.processed_rows + ' of ' + data.total_rows + ' rows (' + pct + '%)...'
						);
						$( '.progress-status-badge' ).text( ( data.status || '' ).toUpperCase() );

						// Update counters.
						$( '#counter-created' ).text( data.created_count );
						$( '#counter-updated' ).text( data.updated_count );
						$( '#counter-skipped' ).text( data.skipped_count );
						$( '#counter-failed' ).text( data.failed_count );

						// Check if job completed or finished.
						if ( data.is_finished ) {
							clearInterval( pollInterval );
							$( '#wpcalibrate-cancel-job-btn' ).hide();
							$( '#wpcalibrate-view-results-btn' ).show();
							$( '.progress-status-badge' )
								.removeClass( 'badge-info badge-warning' )
								.addClass( data.status === 'completed' ? 'badge-success' : 'badge-danger' );
						}
					}
				} );
			}

			// Start polling every 2 seconds.
			pollInterval = setInterval( pollJobStatus, 2000 );
		}

		// 3. Cancel Job Button.
		$( '#wpcalibrate-cancel-job-btn' ).on( 'click', function( e ) {
			e.preventDefault();
			var jobId = $( this ).data( 'job-id' );

			if ( ! confirm( wpcalibrateImporter.strings.confirmCancel ) ) {
				return;
			}

			var $btn = $( this );
			$btn.prop( 'disabled', true ).text( 'Cancelling...' );

			$.ajax( {
				url: wpcalibrateImporter.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_importer_cancel',
					job_id: jobId,
					nonce: wpcalibrateImporter.nonce
				},
				success: function( response ) {
					if ( response && response.success ) {
						$( '.progress-status-badge' )
							.removeClass( 'badge-info' )
							.addClass( 'badge-warning' )
							.text( 'CANCELLED' );
						$( '.progress-status-text' ).text( 'Job cancelled by administrator.' );
						$btn.hide();
						$( '#wpcalibrate-view-results-btn' ).show();
					} else {
						alert( response.data && response.data.message ? response.data.message : 'Error cancelling job.' );
						$btn.prop( 'disabled', false ).text( 'Cancel Import Job' );
					}
				},
				error: function() {
					alert( 'Network error while attempting cancellation.' );
					$btn.prop( 'disabled', false ).text( 'Cancel Import Job' );
				}
			} );
		} );

		// 4. One-Click Demo Catalog Import.
		$( '#wpcalibrate-btn-demo-import' ).on( 'click', function( e ) {
			e.preventDefault();
			var $btn = $( this );
			var $notice = $( '#wpcalibrate-demo-status-notice' );

			if ( ! confirm( 'Import bundled demo products into your WooCommerce catalog now?' ) ) {
				return;
			}

			$btn.prop( 'disabled', true ).text( '⏳ Importing Demo Products...' );
			$notice.show().html( '<div class="notice notice-info inline" style="margin: 0;"><p>⏳ Processing demo catalog, creating products and variations...</p></div>' );

			$.ajax( {
				url: wpcalibrateImporter.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wpcalibrate_import_demo_catalog',
					nonce: wpcalibrateImporter.nonce
				},
				success: function( response ) {
					if ( response && response.success ) {
						var data = response.data;
						$btn.text( '✅ Demo Products Imported!' );
						$notice.html(
							'<div class="notice notice-success inline" style="margin: 0;"><p>' +
							'<strong>Success!</strong> ' + ( data.message || 'Demo products imported.' ) + ' ' +
							'<a href="edit.php?post_type=product" class="button button-small" style="margin-left: 8px;">View Products in WooCommerce &rarr;</a> ' +
							'<a href="admin.php?page=wpcalibrate-catalog-importer-history" class="button button-small" style="margin-left: 4px;">View in Import History &rarr;</a>' +
							'</p></div>'
						);
					} else {
						var errMsg = response.data && response.data.message ? response.data.message : 'Error importing demo products.';
						$btn.prop( 'disabled', false ).text( '🚀 1-Click Demo Import' );
						$notice.html( '<div class="notice notice-error inline" style="margin: 0;"><p>' + errMsg + '</p></div>' );
					}
				},
				error: function() {
					$btn.prop( 'disabled', false ).text( '🚀 1-Click Demo Import' );
					$notice.html( '<div class="notice notice-error inline" style="margin: 0;"><p>Network error while importing demo products.</p></div>' );
				}
			} );
		} );
	} );
} )( jQuery );
