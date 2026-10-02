/**
 * Language code field, shared by the block panel and the page/template panel.
 */

import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Language code field: swaps underscores while typing, tidies the code when the field is left,
 * and shows an error under it when the code is not well formed. The checks live in
 * assets/js/lang-code.js, shared with the classic editor and Quick/Bulk Edit.
 */
export const LangCodeControl = ( { value, onChange, help, ...props } ) => {
	const langCode = window.nakedCatPluginsLangCode;
	const [ isFocused, setIsFocused ] = useState( false );
	// Not flagged while typing, so half-typed codes are not shown as errors.
	const isInvalid = !! langCode && ! isFocused && ! langCode.isValid( value );
	return (
		<TextControl
			{ ...props }
			value={ value }
			onChange={ ( next ) => onChange( langCode ? langCode.normalizeTyping( next ) : next ) }
			onFocus={ () => setIsFocused( true ) }
			onBlur={ () => {
				setIsFocused( false );
				const normalized = langCode ? langCode.normalize( value ) : value.trim();
				if ( normalized !== value ) {
					onChange( normalized );
				}
			} }
			aria-invalid={ isInvalid }
			help={
				isInvalid ? (
					<>
						<span style={ { display: 'block', color: '#cc1818' } }>
							{ __( 'This is not a valid language code. Use a code like “fr”, “pt” or “pt-PT”.', 'lang-attribute-blocks' ) }
						</span>
						{ help }
					</>
				) : help
			}
		/>
	);
};
