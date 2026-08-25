<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Model;

use LogicException;

final class EvolutionGraph {
	/** @var array<string,EvolutionNode> */
	private array $nodes = [];

	/** @var EvolutionEdge[] */
	private array $edges = [];

	/** @param string[] $warnings */
	public function __construct(
		public readonly string $direction,
		public readonly string $theme,
		public readonly int $defaultImageWidth,
		public readonly int $defaultImageHeight,
		public readonly bool $zoom,
		public readonly bool $controls,
		private array $warnings = []
	) {
	}

	public function addNode( EvolutionNode $node ): void {
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
