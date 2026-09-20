/**
 * Persist dismissal of the consent notice (dismissal counts as "No thanks").
 *
 * @package StandaloneTech\Telemetry
 */

( function () {
	'use strict';

	document.addEventListener(
		'click',
		function ( event ) {
			var button = event.target.closest ? event.target.closest( '.sts-telemetry-notice .notice-dismiss' ) : null;
			var notice = button ? button.closest( '.sts-telemetry-notice' ) : null;
			var body;

			if ( ! notice ) {
				return;
			}

			body = new FormData();
			body.append( 'action', notice.getAttribute( 'data-action' ) );
			body.append( 'nonce', notice.getAttribute( 'data-nonce' ) );
			window.fetch( notice.getAttribute( 'data-ajax-url' ), { method: 'POST', credentials: 'same-origin', body: body } );
		}
	);
}() );
