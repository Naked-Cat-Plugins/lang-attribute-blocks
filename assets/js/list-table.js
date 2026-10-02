/**
 * Posts list table: pre-fill the Quick Edit language fields and handle the Bulk Edit remove option.
 *
 * @since 3.3
 */
( function ( $ ) {
	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}

	// Wrap the core Quick Edit opener to copy the row's values into our fields.
	const coreEdit = inlineEditPost.edit;
	inlineEditPost.edit = function ( id ) {
		coreEdit.apply( this, arguments );

		const postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : 0;
		if ( ! postId ) {
			return;
		}
		const $data = $( '#post-' + postId ).find( '.nakedcatplugins-lang-data' );
		const $edit = $( '#edit-' + postId );
		const $lang = $edit.find( 'input[name="nakedcatplugins_quick_edit_lang"]' ).val( $data.attr( 'data-lang' ) || '' );
		$edit.find( 'select[name="nakedcatplugins_quick_edit_dir"]' ).val( $data.attr( 'data-dir' ) === 'rtl' ? 'rtl' : 'ltr' );
		// Flag a code saved before codes were checked.
		if ( $lang.length && window.nakedCatPluginsLangCode ) {
			window.nakedCatPluginsLangCode.check( $lang[ 0 ] );
		}
	};

	// Removing the language makes the other two Bulk Edit fields meaningless.
	$( document ).on( 'change', 'input[name="nakedcatplugins_bulk_edit_remove"]', function () {
		$( this )
			.closest( '.nakedcatplugins-lang-bulk-edit' )
			.find( 'input[name="nakedcatplugins_bulk_edit_lang"], select[name="nakedcatplugins_bulk_edit_dir"]' )
			.prop( 'disabled', this.checked );
	} );
} )( jQuery );
