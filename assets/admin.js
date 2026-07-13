/* global jQuery, UAO */
( function ( $ ) {
	'use strict';

	var timers = {};

	function post( card, field, value, $state ) {
		var user = card.data( 'user' );
		if ( $state ) {
			$state.removeClass( 'is-saved is-error' ).text( UAO.saving );
		}
		return $.post( UAO.ajaxUrl, {
			action:  'uao_save',
			nonce:   UAO.nonce,
			user_id: user,
			field:   field,
			value:   value
		} ).done( function ( res ) {
			if ( res && res.success ) {
				if ( $state ) {
					$state.addClass( 'is-saved' ).text( UAO.saved );
					setTimeout( function () { $state.text( '' ).removeClass( 'is-saved' ); }, 1600 );
				}
				if ( res.data && res.data.updated && card ) {
					card.find( '.uao-card__time' )
						.text( UAO.updated + ' ' + res.data.updated )
						.css( 'display', '' );
				}
			} else if ( $state ) {
				$state.addClass( 'is-error' ).text( ( res && res.data && res.data.message ) || UAO.error );
			}
		} ).fail( function () {
			if ( $state ) { $state.addClass( 'is-error' ).text( UAO.error ); }
		} );
	}

	// Status buttons.
	$( document ).on( 'click', '.uao-statusbtn', function () {
		var $btn   = $( this );
		var card   = $btn.closest( '.uao-card' );
		var status = $btn.data( 'status' );
		var $state = card.find( '.uao-savestate' );

		// UI: active button.
		card.find( '.uao-statusbtn' ).removeClass( 'is-active' );
		$btn.addClass( 'is-active' );

		// UI: card colour + open/closed state.
		card.removeClass( 'uao-card--inactive uao-card--in_progress' )
			.addClass( 'uao-card--' + status );
		var open = ( status === 'in_progress' );
		card.toggleClass( 'is-open', open );

		// UI: badge label.
		if ( UAO.labels && UAO.labels[ status ] ) {
			card.find( '.uao-badge__text' ).text( UAO.labels[ status ] );
		}

		post( card, 'status', status, $state );
	} );

	// Text fields (working_on, message) — debounced autosave.
	$( document ).on( 'input', '.uao-input', function () {
		var $el   = $( this );
		var card  = $el.closest( '.uao-card' );
		var field = $el.data( 'field' );
		var key   = card.data( 'user' ) + '-' + field;
		clearTimeout( timers[ key ] );
		timers[ key ] = setTimeout( function () {
			post( card, field, $el.val(), card.find( '.uao-savestate' ) );
		}, 800 );
	} );

	// Save immediately on blur.
	$( document ).on( 'blur', '.uao-input', function () {
		var $el   = $( this );
		var card  = $el.closest( '.uao-card' );
		var field = $el.data( 'field' );
		var key   = card.data( 'user' ) + '-' + field;
		clearTimeout( timers[ key ] );
		post( card, field, $el.val(), card.find( '.uao-savestate' ) );
	} );

	// Toggle edit mode on your own card.
	$( document ).on( 'click', '.uao-edit-btn', function () {
		var card = $( this ).closest( '.uao-card' );
		card.find( '.uao-self-view' ).hide();
		card.find( '.uao-card__edit' ).show();
	} );

	// Finish editing — sync the read-only view from the inputs, then collapse.
	$( document ).on( 'click', '.uao-done-btn', function () {
		var card = $( this ).closest( '.uao-card' );
		var wo   = $.trim( card.find( '[data-field="working_on"]' ).val() );
		var msg  = card.find( '[data-field="message"]' ).val();

		var $title = card.find( '.uao-view-title' );
		if ( wo ) {
			$title.text( UAO.workingOn + ' ' + wo ).show();
		} else {
			$title.hide();
		}

		var $msg = card.find( '.uao-view-msg' );
		if ( $.trim( msg ) ) {
			$msg.text( msg ).removeClass( 'uao-card__msg--muted' );
		} else {
			$msg.text( UAO.noUpdate ).addClass( 'uao-card__msg--muted' );
		}

		card.find( '.uao-card__edit' ).hide();
		card.find( '.uao-self-view' ).show();
	} );

	// User Guide modal open/close.
	$( document ).on( 'click', '#uao-guide-open', function ( e ) {
		e.preventDefault();
		$( '#uao-guide-modal' ).css( 'display', 'flex' );
	} );
	$( document ).on( 'click', '[data-uao-guide-close]', function () {
		$( '#uao-guide-modal' ).hide();
	} );
	$( document ).on( 'keyup', function ( e ) {
		if ( 'Escape' === e.key ) {
			$( '#uao-guide-modal' ).hide();
		}
	} );
} )( jQuery );
