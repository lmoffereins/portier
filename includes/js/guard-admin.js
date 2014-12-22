/**
 * Guard Admin Scripts
 *
 * @package Guard
 * @subpackage Administration
 */
jQuery(document).ready( function($) {
	var l10n = guardAdminL10n || {}, 
	    settings = l10n.settings || {};

	// Chosen
	$( '.chzn-select' ).chosen();

	// Pointer
	if ( settings.showPointer ) {
		$( '#wp-admin-bar-guard' ).pointer({
			content: '<?php echo $pointer_content; ?>',
			position: {
				edge: 'top',
				align: 'center',
				my: 'right+40 top'
			},
			close: function() {
				$.post( ajaxurl, {
					pointer: 'guard_protection',
					action: 'dismiss-wp-pointer'
				});
			}
		}).pointer('open');
	}
});
