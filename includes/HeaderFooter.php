<?php

use MediaWiki\Html\Html;
use MediaWiki\Parser\ParserOutput;

/**
 * @package HeaderFooter
 */
class HeaderFooter {

	const HF_NSHEADER = 'hf_nsheader';
	const HF_HEADER = 'hf_header';
	const HF_FOOTER = 'hf_footer';
	const HF_NSFOOTER = 'hf_nsfooter';
	const MAGIC_WORDS = [ self::HF_NSHEADER, self::HF_HEADER, self::HF_FOOTER, self::HF_NSFOOTER ];

	/**
	 * This hook no longer allows us to modify the parser output, so we have to use OutputPageBeforeHTML.
	 * However, in OutputPageBeforeHTML, we don't have access to page properties, i.e. magic words,
	 * so we need to set them here, on ParserOutput.
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 * @return void
	 */
	public static function onOutputPageParserOutput( OutputPage &$outputPage, ParserOutput $parserOutput ) {
		foreach ( self::MAGIC_WORDS as $prop ) {
			$outputPage->setProperty( $prop, $parserOutput->getPageProperty( $prop ) );
		}
	}

	public static function onOutputPageBeforeHTML( $out, &$text ): void {
		$action = $out->getRequest()->getVal( "action" );
		if ( ( $action == 'edit' ) || ( $action == 'submit' ) || ( $action == 'history' ) ) {
			return;
		}

		$title = $out->getTitle();
		$ns = $title->getNsText();
		$name = $title->getPrefixedDBKey();

		$nsheader = self::conditionalInclude( self::HF_NSHEADER, 'hf-nsheader', $ns, $out );
		$header   = self::conditionalInclude( self::HF_HEADER, 'hf-header', $name, $out );
		$footer   = self::conditionalInclude( self::HF_FOOTER, 'hf-footer', $name, $out );
		$nsfooter = self::conditionalInclude( self::HF_NSFOOTER, 'hf-nsfooter', $ns, $out );

		// Similarly to PageHooks, this being mw-parser-output is a lie,
		// but this makes the page update properly after an edit is saved,
		// i.e. it properly removes header/footer when magic words are added or removed.
		$text = Html::rawElement( 'div', [ 'class' => 'mw-parser-output' ],
			$nsheader . $header . $text . $footer . $nsfooter);

		global $egHeaderFooterEnableAsyncHeader, $egHeaderFooterEnableAsyncFooter;
		if ( $egHeaderFooterEnableAsyncFooter || $egHeaderFooterEnableAsyncHeader ) {
			$out->addModules( 'ext.headerfooter.dynamicload' );
		}
	}

	/**
	 * @param string[] &$doubleUnderscoreIDs
	 */
	public static function onGetDoubleUnderscoreIDs( array &$doubleUnderscoreIDs ): void {
		foreach ( self::MAGIC_WORDS as $magicWord ) {
			$doubleUnderscoreIDs[] = $magicWord;
		}
	}

	/**
	 * @param string $magicWord
	 * @param string $class
	 * @param string $unique
	 * @param OutputPage $out
	 * @return null|string
	 */
	public static function conditionalInclude( string $magicWord, string $class, string $unique, OutputPage $out ):
	?string {
		if ( $out->getProperty( $magicWord ) !== null ) {
			return null;
		}

		// both message ID, e.g. 'MediaWiki::hf-nsheader', as well as HTML ID
		$msgId = "$class-$unique";
		$params = [
			'class' => $class,
			'id' => $msgId,
		];

		global $egHeaderFooterEnableAsyncHeader, $egHeaderFooterEnableAsyncFooter;

		$isHeader = $class === 'hf-nsheader' || $class === 'hf-header';
		$isFooter = $class === 'hf-nsfooter' || $class === 'hf-footer';

		if ( ( $egHeaderFooterEnableAsyncFooter && $isFooter )
			 || ( $egHeaderFooterEnableAsyncHeader && $isHeader ) ) {

			// Just drop an empty div into the page.
			// Will fill it with async request after page load.
			return Html::rawElement( 'div', $params);
		} else {
			$msgText = wfMessage( $msgId )->parse();

			// don't need to bother if there is no content.
			if ( empty( $msgText ) ) {
				return null;
			}

			if ( wfMessage( $msgId )->inContentLanguage()->isBlank() ) {
				return null;
			}

			return Html::rawElement( 'div', $params, $msgText);
		}
	}

	public static function onResourceLoaderGetConfigVars( array &$vars ): void {
		global $egHeaderFooterEnableAsyncHeader, $egHeaderFooterEnableAsyncFooter;

		$vars['egHeaderFooter'] = [
			'enableAsyncHeader' => $egHeaderFooterEnableAsyncHeader,
			'enableAsyncFooter' => $egHeaderFooterEnableAsyncFooter,
		];
	}
}
