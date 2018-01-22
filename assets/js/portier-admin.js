/**
 * Portier Admin Scripts
 *
 * @package Portier
 * @subpackage Administration
 */
jQuery(document).ready( function($) {
	var l10n = portierAdminL10n || {}, 
	    settings = l10n.settings || {};

	// Chosen
	$( '.chzn-select' ).chosen();

	// Pointer
	if ( settings.showPointer ) {
		$( '#wp-admin-bar-portier' ).pointer({
			content: l10n.pointerContent,
			position: {
				edge: 'top',
				align: 'center',
				my: 'right+40 top'
			},
			close: function() {
				$.post( ajaxurl, {
					pointer: 'portier_protection',
					action: 'dismiss-wp-pointer'
				});
			}
		}).pointer('open');
	}
});
