<?php

declare( strict_types=1 );

use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser;
use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionValueValidator;
use MediaWiki\Extension\MonsterEvolution\Rendering\EvolutionRenderer;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiFileResolver;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiLinkResolver;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use MediaWiki\Extension\MonsterEvolution\Security\LocalFileNamePolicy;
use MediaWiki\MediaWikiServices;

/*
 * Composition root: concrete MediaWiki adapters are chosen here and injected into
 * domain-facing services. Runtime classes do not reach back into the global service
 * container, which keeps dependencies visible and testable.
 */
return [
	// Security policy services are deliberately independent of MediaWiki state.
	'MonsterEvolution.LocalFileNamePolicy' => static function (): LocalFileNamePolicy {
		return new LocalFileNamePolicy();
	},
	'MonsterEvolution.Limits' => static function ( MediaWikiServices $services ): EvolutionLimits {
		$config = $services->getMainConfig();
		return new EvolutionLimits(
			$config->get( 'MonsterEvolutionMaxInputBytes' ),
			$config->get( 'MonsterEvolutionMaxNodes' ),
			$config->get( 'MonsterEvolutionMaxEdges' ),
			$config->get( 'MonsterEvolutionMaxConditionsPerEdge' ),
			$config->get( 'MonsterEvolutionMaxAttributes' ),
			$config->get( 'MonsterEvolutionMaxValueLength' ),
			$config->get( 'MonsterEvolutionMaxNodeIdLength' ),
			$config->get( 'MonsterEvolutionMaxGraphsPerPage' )
		);
	},
	'MonsterEvolution.ValueValidator' => static function ( MediaWikiServices $services ): EvolutionValueValidator {
		return new EvolutionValueValidator(
			$services->getService( 'MonsterEvolution.Limits' ),
			$services->getService( 'MonsterEvolution.LocalFileNamePolicy' )
		);
	},
	'MonsterEvolution.Parser' => static function ( MediaWikiServices $services ): EvolutionParser {
		return new EvolutionParser(
			$services->getService( 'MonsterEvolution.Limits' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultDirection' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultTheme' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultImageWidth' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultImageHeight' ),
			$services->getService( 'MonsterEvolution.ValueValidator' )
		);
	},
	'MonsterEvolution.Renderer' => static function ( MediaWikiServices $services ): EvolutionRenderer {
		$config = $services->getMainConfig();
		return new EvolutionRenderer(
			new WikiLinkResolver( $services->getTitleFactory() ),
			new WikiFileResolver( $services->getTitleFactory(), $services->getRepoGroup() ),
			$services->getLinkRendererFactory()->create(),
			(bool)$config->get( 'MonsterEvolutionEnableZoom' ),
			(string)$config->get( 'MonsterEvolutionMissingImage' ),
			(bool)$config->get( 'MonsterEvolutionEnableMissingPageTrackingCategory' )
		);
	},
];
