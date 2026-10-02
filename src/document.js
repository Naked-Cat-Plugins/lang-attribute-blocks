/**
 * Page and template language panel in the Document Settings sidebar.
 *
 * Separate from index.js because it needs @wordpress/editor, which WordPress does not allow
 * next to the widgets editor, so it is not loaded there.
 */

import { __, sprintf } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { LangCodeControl } from './lang-code-control';

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
