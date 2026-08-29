<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

use LogicException;

/**
 * Aggregate root for one parsed evolution definition.
 *
 * Nodes are keyed by stable editor-facing IDs while edges refer to those IDs. The
 * renderer later maps them to compact numeric indexes for client markup. Mutation
 * is restricted to parser-time add methods; callers receive read-only model values.
 */
final class EvolutionGraph {
	/** @var array<string,EvolutionNode> */
	private array $nodes = [];

	/** @var EvolutionEdge[] */
	private array $edges = [];

	/**
	 * @param string $direction
	 * @param string $theme
	 * @param int $defaultImageWidth
	 * @param int $defaultImageHeight
	 * @param bool $zoom
	 * @param bool $controls
	 * @param string[] $warnings
	 * @param string $layout
	 * @param string|null $center
	 * @param string $radialShape
	 * @param string $radialStart
	 */
	public function __construct(
		public readonly string $direction,
		public readonly string $theme,
		public readonly int $defaultImageWidth,
		public readonly int $defaultImageHeight,
		public readonly bool $zoom,
		public readonly bool $controls,
		private array $warnings = [],
		public readonly string $layout = 'layered',
		public readonly ?string $center = null,
		public readonly string $radialShape = 'circle',
		public readonly string $radialStart = 'top'
	) {
	}

	public function addNode( EvolutionNode $node ): void {
		// Defend the aggregate invariant even if a caller bypasses EvolutionParser.
		if ( isset( $this->nodes[$node->id] ) ) {
			throw new LogicException( "Duplicate node ID: {$node->id}" );
		}
		$this->nodes[$node->id] = $node;
	}

	public function addEdge( EvolutionEdge $edge ): void {
		$this->edges[] = $edge;
	}

	/** @return array<string,EvolutionNode> */
	public function getNodes(): array {
		return $this->nodes;
	}

	/** @return EvolutionEdge[] */
	public function getEdges(): array {
		return $this->edges;
	}

	/** @return string[] */
	public function getWarnings(): array {
		return $this->warnings;
	}
}
