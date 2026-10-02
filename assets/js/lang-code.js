/**
 * Language code checks, shared by the block editor panels, the classic editor metabox and Quick/Bulk Edit.
 *
 * Checks that a code is well formed per RFC 5646 (BCP 47), the format the HTML lang attribute takes.
 * It is not checked against the IANA registry, so a well-formed code that does not exist passes.
 * The 17 irregular "grandfathered" tags (i-klingon, en-GB-oed...) are left out: all are deprecated
 * in favour of codes that do match.
 *
 * Plain inputs opt in with the "nakedcatplugins-lang-code-input" class, and show a sibling
 * ".nakedcatplugins-lang-code-error" element when the code is not valid.
 *
 * @since 3.3
 */
( function () {
	// Language (2-3 letters with up to 3 extended subtags, or 4-8 letters), script, region,
	// variants, extensions and private use; or private use on its own.
	const pattern = /^(?:(?:[a-z]{2,3}(?:-[a-z]{3}){0,3}|[a-z]{4,8})(?:-[a-z]{4})?(?:-(?:[a-z]{2}|\d{3}))?(?:-(?:[a-z\d]{5,8}|\d[a-z\d]{3}))*(?:-[a-wyz\d](?:-[a-z\d]{2,8})+)*(?:-x(?:-[a-z\d]{1,8})+)?|x(?:-[a-z\d]{1,8})+)$/i;

	const api = {
		/**
		 * While typing: WordPress locales use underscores, language tags use hyphens.
		 *
		 * @param {string} value Field value.
		 * @return {string} Value with hyphens.
		 */
		normalizeTyping( value ) {
			return String( value || '' ).replace( /_/g, '-' );
		},

		/**
		 * When done typing: also trim, drop the WordPress-only locale suffixes (de_DE_formal,
		 * pt_PT_ao90...) and apply the conventional casing (pt-PT, zh-Hant-TW). Mirrors
		 * Lang_Attribute_Blocks::sanitize_lang_code() in PHP.
		 *
		 * @param {string} value Field value.
		 * @return {string} Normalised code.
		 */
		normalize( value ) {
			const code = api.normalizeTyping( value ).trim().replace( /-(?:formal|informal|ao90)$/i, '' );
			if ( '' === code ) {
				return '';
			}
			let afterSingleton = false;
			return code
				.split( '-' )
				.map( ( subtag, index ) => {
					if ( 0 === index || afterSingleton || 1 === subtag.length ) {
						afterSingleton = afterSingleton || 1 === subtag.length;
						return subtag.toLowerCase();
					}
					if ( /^[a-z]{4}$/i.test( subtag ) ) {
						return subtag.charAt( 0 ).toUpperCase() + subtag.slice( 1 ).toLowerCase();
					}
					if ( /^[a-z]{2}$/i.test( subtag ) ) {
						return subtag.toUpperCase();
					}
					return subtag.toLowerCase();
				} )
				.join( '-' );
		},

		/**
		 * Whether a code is well formed. Empty is valid: it means "no language set".
		 *
		 * @param {string} value Code.
		 * @return {boolean} Whether it is valid.
		 */
		isValid( value ) {
			const code = String( value || '' ).trim();
			return '' === code || pattern.test( code );
		},

		/**
		 * Show or hide a plain input's error message.
		 *
		 * @param {HTMLInputElement} input The language code input.
		 */
		check( input ) {
			const valid = api.isValid( input.value );
			const error = input.parentNode.querySelector( '.nakedcatplugins-lang-code-error' );
			input.setAttribute( 'aria-invalid', valid ? 'false' : 'true' );
			if ( error ) {
				error.hidden = valid;
			}
		},
	};

	window.nakedCatPluginsLangCode = api;

	const isLangInput = ( element ) => element instanceof HTMLInputElement && element.classList.contains( 'nakedcatplugins-lang-code-input' );

	// Swap underscores as they are typed, keeping the caret where it was (same length).
	document.addEventListener( 'input', ( event ) => {
		const input = event.target;
		if ( ! isLangInput( input ) || -1 === input.value.indexOf( '_' ) ) {
			return;
		}
		const caret = input.selectionStart;
		input.value = api.normalizeTyping( input.value );
		input.setSelectionRange( caret, caret );
	} );

	// Hide the error while editing, so half-typed codes are not flagged.
	document.addEventListener( 'focusin', ( event ) => {
		if ( isLangInput( event.target ) ) {
			const error = event.target.parentNode.querySelector( '.nakedcatplugins-lang-code-error' );
			if ( error ) {
				error.hidden = true;
			}
		}
	} );

	// Tidy up and check once the field is left.
	document.addEventListener( 'focusout', ( event ) => {
		if ( isLangInput( event.target ) ) {
			event.target.value = api.normalize( event.target.value );
			api.check( event.target );
		}
	} );

	// Flag codes saved before this check existed.
	const checkAll = () => document.querySelectorAll( 'input.nakedcatplugins-lang-code-input' ).forEach( api.check );
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', checkAll );
	} else {
		checkAll();
	}
} )();
