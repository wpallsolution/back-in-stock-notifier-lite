( function ( $ ) {
	'use strict';

	var $box = $( '.bisnl[data-variable="1"]' );

	// Variable products: show the form only for an out-of-stock variation.
	$( '.variations_form' )
		.on( 'found_variation', function ( e, variation ) {
			if ( ! $box.length ) {
				return;
			}
			if ( variation && ! variation.is_in_stock ) {
				$box.find( 'input[name="variation_id"]' ).val( variation.variation_id );
				$box.find( '.bisnl__message' ).text( '' ).removeClass( 'is-error is-success' );
				$box.prop( 'hidden', false );
			} else {
				$box.prop( 'hidden', true );
			}
		} )
		.on( 'reset_data hide_variation', function () {
			$box.prop( 'hidden', true );
		} );

	$( document ).on( 'submit', '.bisnl__form', function ( e ) {
		e.preventDefault();

		var $form = $( this ),
			$msg = $form.find( '.bisnl__message' ),
			$btn = $form.find( '.bisnl__button' ),
			data = $form.serializeArray();

		data.push( { name: 'action', value: 'bisnl_subscribe' }, { name: 'nonce', value: bisnlData.nonce } );

		$btn.prop( 'disabled', true );
		$msg.removeClass( 'is-error is-success' ).text( '' );

		$.post( bisnlData.ajaxUrl, data )
			.done( function ( res ) {
				var ok = res && res.success;
				$msg.addClass( ok ? 'is-success' : 'is-error' ).text( res && res.data && res.data.message ? res.data.message : bisnlData.error );
			} )
			.fail( function ( xhr ) {
				var m = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				$msg.addClass( 'is-error' ).text( m || bisnlData.error );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	} );
} )( jQuery );
