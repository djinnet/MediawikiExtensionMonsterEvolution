<?php

declare( strict_types=1 );

use MediaWiki\Extension\MonsterEvolution\Parser\EvolutionParser;
use MediaWiki\Extension\MonsterEvolution\Rendering\EvolutionRenderer;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiFileResolver;
use MediaWiki\Extension\MonsterEvolution\Resolution\WikiLinkResolver;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;
use MediaWiki\MediaWikiServices;

return [
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
	'MonsterEvolution.Parser' => static function ( MediaWikiServices $services ): EvolutionParser {
		return new EvolutionParser(
			$services->getService( 'MonsterEvolution.Limits' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultDirection' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultTheme' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultImageWidth' ),
			$services->getMainConfig()->get( 'MonsterEvolutionDefaultImageHeight' )
		);
	},
	'MonsterEvolution.Renderer' => static function ( MediaWikiServices $services ): EvolutionRenderer {
		$config = $services->getMainConfig();
		return new EvolutionRenderer(
			new WikiLinkResolver( $services->getTitleFactory() ),
			new WikiFileResolver( $services->getTitleFactory(), $services->getRepoGroup() ),
			$services->getLinkRendererFactory()->create(),
			(bool)$config->get( 'MonsterEvolutionEnableZoom' ),
			(string)$config->get( 'MonsterEvolutionMissingImage' )
		);
	},
];
