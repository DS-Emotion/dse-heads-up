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

	// Finish editing — save any pending changes, then reload the page so the
	// header counts are recalculated and the board re-sorts (your card stays
	// pinned to the top). Reloading avoids the stale count that could appear
	// when only part of the board was updated via AJAX.
	$( document ).on( 'click', '.uao-done-btn', function () {
		var $btn   = $( this );
		var card   = $btn.closest( '.uao-card' );
		var $wo    = card.find( '[data-field="working_on"]' );
		var $msg   = card.find( '[data-field="message"]' );
		var $state = card.find( '.uao-savestate' );

		// Cancel pending debounced autosaves so we do not double-post.
		clearTimeout( timers[ card.data( 'user' ) + '-working_on' ] );
		clearTimeout( timers[ card.data( 'user' ) + '-message' ] );

		// Guard against a double click while saving.
		$btn.prop( 'disabled', true );

		// Persist both text fields (status saves on click), then reload once
		// the server has stored everything.
		$.when(
			post( card, 'working_on', $.trim( $wo.val() ), $state ),
			post( card, 'message', $msg.val(), null )
		).always( function () {
			window.location.reload();
		} );
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
