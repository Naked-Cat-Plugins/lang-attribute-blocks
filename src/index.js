/**
 * Adds language attributes to container blocks
 */

import './index.scss';

import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { TextControl, SelectControl, PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

/**
 * Language code field: swaps underscores while typing, tidies the code when the field is left,
 * and shows an error under it when the code is not well formed. The checks live in
 * assets/js/lang-code.js, shared with the classic editor and Quick/Bulk Edit.
 */
const LangCodeControl = ( { value, onChange, help, ...props } ) => {
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


/**
 * Add language attributes to Group block
 */
const addLangAttributesToGroupBlock = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		// Only for supported blocks
		if (
			window.nakedCatPluginsLangAttributeBlocks
			&&
			window.nakedCatPluginsLangAttributeBlocks.supportedBlocks
			&& 
			! window.nakedCatPluginsLangAttributeBlocks.supportedBlocks.includes( props.name )
		) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;

		// Get existing lang and dir attributes or set default values (trimmed)
		const lang = attributes.lang || '';
		const dir = attributes.dir || 'ltr';

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Block Language', 'lang-attribute-blocks' ) }
						initialOpen={ true }
					>
						<LangCodeControl
							label={ __( 'Language Code', 'lang-attribute-blocks' ) }
							value={ lang }
							onChange={ ( value ) => setAttributes( { lang: value } ) }
							placeholder={ window.nakedCatPluginsLangAttributeBlocks?.placeholderText }
							help={ __( "Valid language code for this block, like “fr” or “pt-PT”, if different from the website's or page's main language (shown as a placeholder)", 'lang-attribute-blocks' ) }
						/>
						<SelectControl
							label={ __( 'Text Direction', 'lang-attribute-blocks' ) }
							value={ dir }
							options={[
								{ label: __( 'Left to right', 'lang-attribute-blocks' ), value: 'ltr' },
								{ label: __( 'Right to left', 'lang-attribute-blocks' ), value: 'rtl' },
							]}
							onChange={ ( value ) => setAttributes( { dir: value } ) }
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'addLangAttributesToGroupBlock' );

// Register the filters
addFilter(
	'editor.BlockEdit',
	'lang-attribute-blocks/add-lang-attributes-to-group-block',
	addLangAttributesToGroupBlock
);

function addLangAndDirAttributes( settings, name ) {
	// Only for supported blocks
	if (
		window.nakedCatPluginsLangAttributeBlocks
		&&
		window.nakedCatPluginsLangAttributeBlocks.supportedBlocks
		&& 
		window.nakedCatPluginsLangAttributeBlocks.supportedBlocks.includes( name )
	) {
		// Add custom attributes for lang and dir
		settings.attributes = {
			...settings.attributes,
			lang: {
				type: 'string',
				default: '',
			},
			dir: {
				type: 'string',
				default: 'ltr',
			},
		};
	}
	return settings;

}

// Add a class when the block has any lang attribute applied
const withLangAttr = createHigherOrderComponent( ( BlockListBlock ) => {
	return ( props ) => {
		// Only for supported blocks
		if (
			window.nakedCatPluginsLangAttributeBlocks
			&&
			window.nakedCatPluginsLangAttributeBlocks.supportedBlocks
			&& 
			! window.nakedCatPluginsLangAttributeBlocks.supportedBlocks.includes( props.block.name )
		) {
			return <BlockListBlock { ...props } />;
		}

		// Check if the block has a lang attribute set
		const hasLangAttribute = props.block.attributes.lang && props.block.attributes.lang.trim() !== '';

		if ( ! hasLangAttribute ) {
			return <BlockListBlock { ...props } />;
		}

		// Add to, not replace, any class set by other plugins' filters
		return <BlockListBlock { ...props } className={ [ props.className, 'naked-cat-plugins-has-lang-attr' ].filter( Boolean ).join( ' ' ) } />
	}
}, 'withLangAttr' );

// Register the filters - Register lang and dir attributes
addFilter(
	'blocks.registerBlockType',
	'lang-attribute-blocks/add-lang-and-dir-attributes',
	addLangAndDirAttributes
);

// Register the filters - Only register the highlighting filter if the setting is enabled
if ( 
	window.nakedCatPluginsLangAttributeBlocks 
	&& 
	window.nakedCatPluginsLangAttributeBlocks.highlightEnabled 
) {
	addFilter(
		'editor.BlockListBlock',
		'lang-attribute-blocks/add-lang-and-dir-attributes',
		withLangAttr
	);
}

/**
 * Page-level language controls in the Document Settings panel
 */
const PageLanguageControls = () => {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);
	const postId = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostId(),
		[]
	);
	const editablePostTypes = window.nakedCatPluginsLangAttributeBlocks?.editablePostTypes || [];
	const isEditablePostType = !! postType && editablePostTypes.includes( postType );
	const isTemplateEditor = postType === 'wp_template';
	const selectedTemplateSlugRaw = useSelect(
		( select ) => {
			if ( isTemplateEditor ) {
				return '';
			}
			return select( 'core/editor' ).getEditedPostAttribute( 'template' ) || '';
		},
		[ isTemplateEditor ]
	);
	const selectedTemplateSlug = String( selectedTemplateSlugRaw || '' )
		.replace( /^templates\//, '' )
		.replace( /\.html$/, '' );
	const templateEntityId = selectedTemplateSlug && window.nakedCatPluginsLangAttributeBlocks?.currentTheme
		? `${ window.nakedCatPluginsLangAttributeBlocks.currentTheme }//${ selectedTemplateSlug }`
		: '';
	const template = useSelect(
		( select ) => {
			if ( ! templateEntityId ) {
				return null;
			}
			return select( 'core' ).getEntityRecord( 'postType', 'wp_template', templateEntityId );
		},
		[ templateEntityId ]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta', postId );
	const [ templateLangMeta, setTemplateLangMeta ] = useEntityProp(
		'postType',
		'wp_template',
		'nakedcatplugins_lang_meta',
		isTemplateEditor ? postId : undefined
	);

	const isReady = isTemplateEditor
		? ( templateLangMeta !== undefined )
		: ( meta !== undefined && typeof meta === 'object' );

	const pageLang = isTemplateEditor
		? ( templateLangMeta?.lang ?? '' )
		: ( meta?._nakedcatplugins_page_lang ?? '' );
	const pageDir = isTemplateEditor
		? ( templateLangMeta?.dir ?? 'ltr' )
		: ( meta?._nakedcatplugins_page_dir ?? 'ltr' );
	const templateDefaultLang = ( template?.nakedcatplugins_lang_meta?.lang ?? '' ).trim();
	const websiteLanguagePlaceholder = window.nakedCatPluginsLangAttributeBlocks?.placeholderText;
	const fieldPlaceholder = ! isTemplateEditor && templateDefaultLang
		? sprintf(
			/* translators: %s: The template's default language code */
			__( '%s (default template language)', 'lang-attribute-blocks' ),
			templateDefaultLang
		)
		: websiteLanguagePlaceholder;
	const fieldHelpText = isTemplateEditor
		? __( "Valid language code for this template, like “fr” or “pt-PT”, if different from the website's main language (shown as a placeholder) - This overrides the HTML language attribute on all posts set to this template, unless overridden at the post level", 'lang-attribute-blocks' )
		: __( "Valid language code for this page/post, like “fr” or “pt-PT”, if different from the website's main language (shown as a placeholder) - This overrides the HTML language attribute", 'lang-attribute-blocks' );

	if ( ! isEditablePostType || ! isReady ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="nakedcatplugins-page-lang-panel"
			title={ isTemplateEditor ? __( 'Template Language', 'lang-attribute-blocks' ) : __( 'Page Language', 'lang-attribute-blocks' ) }
		>
			<LangCodeControl
				label={ __( 'Language Code', 'lang-attribute-blocks' ) }
				value={ pageLang }
				onChange={ ( value ) => isTemplateEditor
					? setTemplateLangMeta( { ...templateLangMeta, lang: value } )
					: setMeta( { ...meta, _nakedcatplugins_page_lang: value } )
				}
				placeholder={ fieldPlaceholder }
				help={ fieldHelpText }
			/>
			<SelectControl
				label={ __( 'Text Direction', 'lang-attribute-blocks' ) }
				value={ pageDir }
				options={[
					{ label: __( 'Left to right', 'lang-attribute-blocks' ), value: 'ltr' },
					{ label: __( 'Right to left', 'lang-attribute-blocks' ), value: 'rtl' },
				]}
				onChange={ ( value ) => isTemplateEditor
					? setTemplateLangMeta( { ...templateLangMeta, dir: value } )
					: setMeta( { ...meta, _nakedcatplugins_page_dir: value } )
				}
			/>
		</PluginDocumentSettingPanel>
	);
};

// Register the plugin to show page-level language controls in the Document panel
registerPlugin( 'nakedcatplugins-page-lang-controls', {
	render: PageLanguageControls,
} );