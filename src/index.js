/**
 * Adds language attributes to container blocks
 *
 * Loaded in every block editor, the widgets editor included. The page/template panel is in
 * document.js, kept apart because it needs the post editor's packages.
 */

import './index.scss';

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { SelectControl, PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { LangCodeControl } from './lang-code-control';

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
