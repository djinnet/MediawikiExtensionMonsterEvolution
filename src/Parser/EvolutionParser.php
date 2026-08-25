<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\MonsterEvolution\Parser;

use MediaWiki\Extension\MonsterEvolution\Model\EvolutionCondition;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionEdge;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionGraph;
use MediaWiki\Extension\MonsterEvolution\Model\EvolutionNode;
use MediaWiki\Extension\MonsterEvolution\Security\EvolutionLimits;

final class EvolutionParser {
	private const NODE_ATTRIBUTES = [
		'id', 'name', 'image', 'link', 'subtitle', 'form', 'tooltip', 'class',
		'imagewidth', 'imageheight',
	];

	private const EDGE_ATTRIBUTES = [ 'type', 'label', 'conditions' ];
	private const GRAPH_ATTRIBUTES = [ 'direction', 'theme', 'imagewidth', 'imageheight', 'zoom', 'controls' ];
	private const DIRECTIONS = [ 'left-to-right', 'right-to-left', 'top-to-bottom', 'bottom-to-top' ];
	private const THEMES = [ 'default', 'compact', 'cards', 'minimal' ];

	private EvolutionTokenizer $tokenizer;

	public function __construct(
		private readonly EvolutionLimits $limits,
		private readonly string $defaultDirection = 'left-to-right',
		private readonly string $defaultTheme = 'default',
		private readonly int $defaultImageWidth = 96,
		private readonly int $defaultImageHeight = 96
	) {
		$this->tokenizer = new EvolutionTokenizer( $limits );
	}

	/**
	 * @param string $source
	 * @param array<string,string> $tagAttributes
	 */
	public function parse( string $source, array $tagAttributes = [] ): EvolutionGraph {
		if ( strlen( $source ) > $this->limits->maxInputBytes ) {
			throw $this->error( 'The graph input is too large.', 0, 'monsterevolution-error-input-limit' );
		}
		if ( !mb_check_encoding( $source, 'UTF-8' ) ) {
			throw $this->error( 'The graph input is not valid UTF-8.', 0 );
		}

		$options = $this->normalizeKeys( $tagAttributes );
		$this->assertAllowedAttributes( $options, self::GRAPH_ATTRIBUTES, 0 );
		$direction = strtolower( trim( $options['direction'] ?? $this->defaultDirection ) );
		$direction = match ( $direction ) {
			'horizontal' => 'left-to-right',
			'vertical' => 'top-to-bottom',
			default => $direction,
		};
		if ( !in_array( $direction, self::DIRECTIONS, true ) ) {
			throw $this->error( "Invalid direction \"$direction\".", 0, 'monsterevolution-error-option' );
		}
		$theme = strtolower( trim( $options['theme'] ?? $this->defaultTheme ) );
		if ( !in_array( $theme, self::THEMES, true ) ) {
			throw $this->error( "Invalid theme \"$theme\".", 0, 'monsterevolution-error-option' );
		}
		$imageWidth = $this->parseDimension( $options['imagewidth'] ?? null, $this->defaultImageWidth, 0 );
		$imageHeight = $this->parseDimension( $options['imageheight'] ?? null, $this->defaultImageHeight, 0 );
		$zoom = $this->parseBoolean( $options['zoom'] ?? 'false', 0 );
		$controls = $this->parseBoolean( $options['controls'] ?? 'false', 0 ) && $zoom;
		$graph = new EvolutionGraph(
			$direction,
			$theme,
			$imageWidth,
			$imageHeight,
			$zoom,
			$controls
		);

		$statements = $this->tokenizer->tokenize( str_replace( "\r\n", "\n", $source ) );
		$edgeStatements = [];
		$hasDeclarations = false;
		foreach ( $statements as $statement ) {
			if ( preg_match( '/^\[\s*node(?:\s|\])/i', $statement->text ) ) {
				$hasDeclarations = true;
				$node = $this->parseNode( $statement );
				if ( isset( $graph->getNodes()[$node->id] ) ) {
					throw $this->error(
						"Duplicate node ID \"{$node->id}\".",
						$statement->line,
						'monsterevolution-error-duplicate-node',
						[ $node->id ]
					);
				}
				if ( count( $graph->getNodes() ) >= $this->limits->maxNodes ) {
					throw $this->error(
						'The node limit was exceeded.',
						$statement->line,
						'monsterevolution-error-node-limit'
					);
				}
				$graph->addNode( $node );
			} else {
				$edgeStatements[] = $statement;
			}
		}

		$autoIds = [];
		foreach ( $edgeStatements as $statement ) {
			$this->parseEdgeStatement( $graph, $statement, $hasDeclarations, $autoIds );
		}
		if ( $graph->getNodes() === [] ) {
			throw $this->error( 'The graph contains no nodes.', 0, 'monsterevolution-error-empty' );
		}
		return $graph;
	}

	private function parseNode( EvolutionStatement $statement ): EvolutionNode {
		$text = $statement->text;
		if ( $text[strlen( $text ) - 1] !== ']' ) {
			throw $this->error( 'A node declaration must end with ].', $statement->line );
		}
		if ( !preg_match( '/^\[\s*node\b/i', $text, $match ) ) {
			throw $this->error( 'Invalid node declaration.', $statement->line );
		}
		$attributeText = substr( $text, strlen( $match[0] ), -1 );
		$attributes = $this->tokenizer->parseAttributes( $attributeText, $statement->line );
		$this->assertAllowedAttributes( $attributes, self::NODE_ATTRIBUTES, $statement->line );
		$id = trim( $attributes['id'] ?? '' );
		$name = trim( $attributes['name'] ?? '' );
		$this->validateId( $id, $statement->line );
		$this->validateText( $name, 'name', $statement->line, false, true );

		$image = $this->nullableTrim( $attributes['image'] ?? null );
		if ( $image !== null ) {
			$this->validateImageName( $image, $statement->line );
		}
		$link = $this->nullableTrim( $attributes['link'] ?? null );
		if ( $link !== null ) {
			$this->validateLinkTitle( $link, $statement->line );
		}
		$subtitle = $this->nullableTrim( $attributes['subtitle'] ?? null );
		$form = $this->nullableTrim( $attributes['form'] ?? null );
		$tooltip = $this->nullableTrim( $attributes['tooltip'] ?? null );
		foreach ( [ 'subtitle' => $subtitle, 'form' => $form, 'tooltip' => $tooltip ] as $key => $value ) {
			if ( $value !== null ) {
				$this->validateText( $value, $key, $statement->line, $key === 'tooltip' );
			}
		}

		$classes = [];
		$classValue = $this->nullableTrim( $attributes['class'] ?? null );
		if ( $classValue !== null ) {
			$classes = preg_split( '/\s+/', strtolower( $classValue ) ) ?: [];
			if ( count( $classes ) > 8 ) {
				throw $this->error( 'A node may have at most eight custom classes.', $statement->line );
			}
			foreach ( $classes as $class ) {
				$this->validateSemanticToken( $class, 'class', $statement->line );
			}
		}

		return new EvolutionNode(
			$id,
			$name,
			$image,
			$link,
			$subtitle,
			$form,
			$tooltip,
			$classes,
			isset( $attributes['imagewidth'] )
				? $this->parseDimension( $attributes['imagewidth'], $this->defaultImageWidth, $statement->line )
				: null,
			isset( $attributes['imageheight'] )
				? $this->parseDimension( $attributes['imageheight'], $this->defaultImageHeight, $statement->line )
				: null
		);
	}

	/**
	 * @param EvolutionGraph $graph
	 * @param EvolutionStatement $statement
	 * @param bool $explicit
	 * @param array<string,string> &$autoIds
	 */
	private function parseEdgeStatement(
		EvolutionGraph $graph,
		EvolutionStatement $statement,
		bool $explicit,
		array &$autoIds
	): void {
		$edgeText = $statement->text;
		$attributes = [];
		$blockStart = strpos( $edgeText, '[' );
		if ( $blockStart !== false ) {
			if ( $edgeText[strlen( $edgeText ) - 1] !== ']' ) {
				throw $this->error( 'An edge attribute block must end with ].', $statement->line );
			}
			$attributes = $this->tokenizer->parseAttributes(
				substr( $edgeText, $blockStart + 1, -1 ),
				$statement->line
			);
			$edgeText = trim( substr( $edgeText, 0, $blockStart ) );
		}
		$this->assertAllowedAttributes( $attributes, self::EDGE_ATTRIBUTES, $statement->line );
		$parts = preg_split(
			'/\s*(<->|->)\s*/',
			$edgeText,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( $parts === false || count( $parts ) < 3 || count( $parts ) % 2 === 0 ) {
			throw $this->error( 'Expected an evolution such as A -> B.', $statement->line );
		}

		$type = strtolower( trim( $attributes['type'] ?? 'custom' ) );
		$this->validateSemanticToken( $type, 'type', $statement->line );
		$label = $this->nullableTrim( $attributes['label'] ?? null );
		if ( $label !== null ) {
			$this->validateText( $label, 'label', $statement->line, true );
		}
		$conditions = $this->parseConditions( $attributes['conditions'] ?? null, $statement->line );

		for ( $index = 0; $index < count( $parts ) - 2; $index += 2 ) {
			$sourceText = trim( $parts[$index] );
			$operator = $parts[$index + 1];
			$targetText = trim( $parts[$index + 2] );
			if ( $sourceText === '' || $targetText === '' ) {
				throw $this->error( 'Evolution endpoints cannot be empty.', $statement->line );
			}
			$source = $this->resolveEndpoint( $graph, $sourceText, $explicit, $autoIds, $statement->line );
			$target = $this->resolveEndpoint( $graph, $targetText, $explicit, $autoIds, $statement->line );
			$this->addEdge( $graph, new EvolutionEdge(
				$source,
				$target,
				$type,
				$label,
				$conditions,
				$statement->line
			) );
			if ( $operator === '<->' ) {
				$this->addEdge( $graph, new EvolutionEdge(
					$target,
					$source,
					$type === 'custom' ? 'reversible' : $type,
					$label,
					$conditions,
					$statement->line
				) );
			}
		}
	}

	/**
	 * @param EvolutionGraph $graph
	 * @param string $endpoint
	 * @param bool $explicit
	 * @param array<string,string> &$autoIds
	 * @param int $line
	 */
	private function resolveEndpoint(
		EvolutionGraph $graph,
		string $endpoint,
		bool $explicit,
		array &$autoIds,
		int $line
	): string {
		if ( $explicit ) {
			$this->validateId( $endpoint, $line );
			if ( !isset( $graph->getNodes()[$endpoint] ) ) {
				throw $this->error(
					"Unknown node \"$endpoint\".",
					$line,
					'monsterevolution-error-unknown-node',
					[ $endpoint ]
				);
			}
			return $endpoint;
		}

		$this->validateText( $endpoint, 'name', $line, false, true );
		if ( isset( $autoIds[$endpoint] ) ) {
			return $autoIds[$endpoint];
		}
		if ( count( $graph->getNodes() ) >= $this->limits->maxNodes ) {
			throw $this->error( 'The node limit was exceeded.', $line, 'monsterevolution-error-node-limit' );
		}
		$id = 'auto-' . ( count( $autoIds ) + 1 );
		$autoIds[$endpoint] = $id;
		$graph->addNode( new EvolutionNode( $id, $endpoint, null, $endpoint ) );
		return $id;
	}

	private function addEdge( EvolutionGraph $graph, EvolutionEdge $edge ): void {
		if ( count( $graph->getEdges() ) >= $this->limits->maxEdges ) {
			throw $this->error( 'The edge limit was exceeded.', $edge->line, 'monsterevolution-error-edge-limit' );
		}
		$graph->addEdge( $edge );
	}

	/** @return EvolutionCondition[] */
	private function parseConditions( ?string $value, int $line ): array {
		$value = $this->nullableTrim( $value );
		if ( $value === null ) {
			return [];
		}
		$this->validateText( $value, 'conditions', $line, true );
		$parts = preg_split( '/[;\n]/u', $value ) ?: [];
		$conditions = [];
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' ) {
				continue;
			}
			if ( count( $conditions ) >= $this->limits->maxConditionsPerEdge ) {
				throw $this->error(
					'The condition limit was exceeded.',
					$line,
					'monsterevolution-error-condition-limit'
				);
			}
			$conditions[] = new EvolutionCondition( $part );
		}
		return $conditions;
	}

	private function validateId( string $id, int $line ): void {
		if ( $id === '' || strlen( $id ) > $this->limits->maxNodeIdLength ||
			!preg_match( '/\A[A-Za-z0-9_-]+\z/D', $id )
		) {
			throw $this->error( "Invalid node ID \"$id\".", $line, 'monsterevolution-error-invalid-id', [ $id ] );
		}
	}

	private function validateText(
		string $value,
		string $field,
		int $line,
		bool $allowNewlines = false,
		bool $required = false
	): void {
		if ( $required && trim( $value ) === '' ) {
			throw $this->error( "The $field value is required.", $line );
		}
		if ( mb_strlen( $value ) > $this->limits->maxValueLength ) {
			throw $this->error( "The $field value is too long.", $line, 'monsterevolution-error-limit' );
		}
		$controlPattern = $allowNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/';
		if ( preg_match( $controlPattern, $value ) ) {
			throw $this->error( "The $field value contains control characters.", $line );
		}
	}

	private function validateSemanticToken( string $value, string $field, int $line ): void {
		if ( !preg_match( '/\A[a-z][a-z0-9_-]{0,31}\z/D', $value ) ) {
			throw $this->error( "Invalid $field value \"$value\".", $line );
		}
	}

	private function validateLinkTitle( string $value, int $line ): void {
		$this->validateText( $value, 'link', $line );
		if (
			preg_match( '/\A\s*(?:https?|javascript|data|vbscript|file):/i', $value ) ||
			str_contains( $value, '://' )
		) {
			throw $this->error(
				'External or executable links are not allowed.',
				$line,
				'monsterevolution-error-invalid-link'
			);
		}
	}

	private function validateImageName( string $value, int $line ): void {
		$this->validateText( $value, 'image', $line );
		if ( str_contains( $value, '..' ) || str_contains( $value, '/' ) || str_contains( $value, '\\' ) ||
			str_contains( $value, ':' ) || preg_match( '/%(?:2e|2f|5c)/i', $value )
		) {
			throw $this->error(
				'The image must be a local MediaWiki file name.',
				$line,
				'monsterevolution-error-invalid-image'
			);
		}
	}

	/**
	 * @param array<string,string> $attributes
	 * @param string[] $allowed
	 */
	private function assertAllowedAttributes( array $attributes, array $allowed, int $line ): void {
		foreach ( array_keys( $attributes ) as $attribute ) {
			if ( !in_array( $attribute, $allowed, true ) ) {
				throw $this->error(
					"Unknown attribute \"$attribute\".",
					$line,
					'monsterevolution-error-unknown-attribute',
					[ $attribute ]
				);
			}
		}
	}

	private function parseDimension( ?string $value, int $default, int $line ): int {
		if ( $value === null || $value === '' ) {
			$value = (string)$default;
		}
		if ( !preg_match( '/\A[0-9]{1,3}\z/D', $value ) ) {
			throw $this->error( 'Image dimensions must be whole pixel values.', $line );
		}
		$dimension = (int)$value;
		if ( $dimension < 16 || $dimension > 512 ) {
			throw $this->error( 'Image dimensions must be between 16 and 512 pixels.', $line );
		}
		return $dimension;
	}

	private function parseBoolean( string $value, int $line ): bool {
		return match ( strtolower( trim( $value ) ) ) {
			'1', 'true', 'yes' => true,
			'0', 'false', 'no' => false,
			default => throw $this->error( "Invalid Boolean value \"$value\".", $line ),
		};
	}

	/**
	 * @param array<string,string> $attributes
	 * @return array<string,string>
	 */
	private function normalizeKeys( array $attributes ): array {
		$normalized = [];
		foreach ( $attributes as $key => $value ) {
			$key = strtolower( (string)$key );
			if ( isset( $normalized[$key] ) ) {
				throw $this->error( "Duplicate attribute \"$key\".", 0 );
			}
			$normalized[$key] = (string)$value;
		}
		return $normalized;
	}

	private function nullableTrim( ?string $value ): ?string {
		if ( $value === null ) {
			return null;
		}
		$value = trim( $value );
		return $value === '' ? null : $value;
	}

	private function error(
		string $message,
		int $line,
		string $messageKey = 'monsterevolution-error-syntax',
		array $params = []
	): EvolutionParseException {
		return new EvolutionParseException( $message, $line, $messageKey, $params ?: [ $message ] );
	}
}
