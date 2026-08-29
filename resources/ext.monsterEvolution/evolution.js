( function () {
	'use strict';

	const SVG_NS = 'http://www.w3.org/2000/svg';
	const initialized = new WeakSet();
	let instanceCounter = 0;

	/*
	 * The client is progressive enhancement only. Server HTML contains every node,
	 * relationship, link, condition, and MediaWiki-resolved image. JavaScript reads
	 * that inert representation, calculates geometry, and adds decorative SVG.
	 * It never parses wikitext or constructs a URL from editor-controlled text.
	 */

	/**
	 * Create an SVG element without using an HTML parsing sink.
	 *
	 * @param {string} name SVG element name
	 * @param {Object<string, string|number>} attributes Element attributes
	 * @return {SVGElement} Newly created SVG element
	 */
	function element( name, attributes ) {
		const node = document.createElementNS( SVG_NS, name );
		Object.keys( attributes ).forEach( ( key ) => {
			node.setAttribute( key, String( attributes[ key ] ) );
		} );
		return node;
	}

	function forEachElement( elements, callback ) {
		Array.prototype.forEach.call( elements, callback );
	}

	function numberAttribute( node, name ) {
		const value = Number.parseInt( node.getAttribute( name ) || '', 10 );
		return Number.isSafeInteger( value ) && value >= 0 ? value : -1;
	}

	function readGraph( root ) {
		const nodes = Array.from( root.querySelectorAll( '.mw-monster-evolution-node' ) );
		const edgeElements = Array.from( root.querySelectorAll( '.mw-monster-evolution-edge' ) );
		const edges = [];
		edgeElements.forEach( ( edgeElement ) => {
			const source = numberAttribute( edgeElement, 'data-source' );
			const target = numberAttribute( edgeElement, 'data-target' );
			if ( source >= 0 && target >= 0 && source < nodes.length && target < nodes.length ) {
				const requestedIconPosition = edgeElement.getAttribute( 'data-edge-icon-position' );
				edges.push( {
					source,
					target,
					type: edgeElement.getAttribute( 'data-edge-type' ) || 'custom',
					label: edgeElement.getAttribute( 'data-edge-label' ) || '',
					iconPosition: requestedIconPosition === 'above' ? 'above' : 'next-to',
					icon: edgeElement.querySelector( '.mw-monster-evolution-edge-icon-source img' ),
					link: edgeElement.querySelector( '.mw-monster-evolution-edge-label-link' ),
					element: edgeElement
				} );
			}
		} );
		return { nodes, edges };
	}

	/*
	 * Assign nodes to layers with Kahn's topological process. Real evolution data
	 * may contain cycles; when no zero-indegree node remains, the lowest unprocessed
	 * node becomes a deterministic cycle break. The processed set guarantees termination.
	 */
	function layerGraph( nodeCount, edges ) {
		const outgoing = Array.from( { length: nodeCount }, () => [] );
		const incoming = Array.from( { length: nodeCount }, () => [] );
		const indegree = new Array( nodeCount ).fill( 0 );
		const layer = new Array( nodeCount ).fill( 0 );
		edges.forEach( ( edge, edgeIndex ) => {
			outgoing[ edge.source ].push( { node: edge.target, edge: edgeIndex } );
			incoming[ edge.target ].push( { node: edge.source, edge: edgeIndex } );
			if ( edge.source !== edge.target ) {
				indegree[ edge.target ]++;
			}
		} );

		const queue = [];
		const processed = new Set();
		for ( let index = 0; index < nodeCount; index++ ) {
			if ( indegree[ index ] === 0 ) {
				queue.push( index );
			}
		}
		while ( processed.size < nodeCount ) {
			if ( queue.length === 0 ) {
				for ( let index = 0; index < nodeCount; index++ ) {
					if ( !processed.has( index ) ) {
						queue.push( index );
						break;
					}
				}
			}
			const current = queue.shift();
			if ( current === undefined || processed.has( current ) ) {
				continue;
			}
			processed.add( current );
			outgoing[ current ].forEach( ( relation ) => {
				if ( processed.has( relation.node ) || relation.node === current ) {
					return;
				}
				layer[ relation.node ] = Math.max( layer[ relation.node ], layer[ current ] + 1 );
				indegree[ relation.node ]--;
				if ( indegree[ relation.node ] <= 0 ) {
					queue.push( relation.node );
				}
			} );
		}

		const groups = [];
		layer.forEach( ( value, node ) => {
			groups[ value ] = groups[ value ] || [];
			groups[ value ].push( node );
		} );
		reduceCrossings( groups, outgoing, incoming );
		return { groups, outgoing, incoming };
	}

	function reduceCrossings( groups, outgoing, incoming ) {
		// Six alternating barycentric sweeps give stable, bounded crossing reduction.
		for ( let sweep = 0; sweep < 6; sweep++ ) {
			const forward = sweep % 2 === 0;
			const start = forward ? 1 : groups.length - 2;
			const end = forward ? groups.length : -1;
			const step = forward ? 1 : -1;
			for ( let layerIndex = start; layerIndex !== end; layerIndex += step ) {
				const reference = groups[ layerIndex - step ] || [];
				const positions = new Map( reference.map( ( node, index ) => [ node, index ] ) );
				const neighbors = forward ? incoming : outgoing;
				groups[ layerIndex ].sort( ( left, right ) => {
					const leftScore = barycenter( neighbors[ left ], positions );
					const rightScore = barycenter( neighbors[ right ], positions );
					return leftScore === rightScore ? left - right : leftScore - rightScore;
				} );
			}
		}
	}

	function barycenter( relations, positions ) {
		let total = 0;
		let count = 0;
		relations.forEach( ( relation ) => {
			if ( positions.has( relation.node ) ) {
				total += positions.get( relation.node );
				count++;
			}
		} );
		return count === 0 ? Number.MAX_SAFE_INTEGER : total / count;
	}

	function positionNodes( graph, sizes, direction ) {
		const horizontal = direction === 'left-to-right' || direction === 'right-to-left';
		const layerGap = 112;
		const nodeGap = 36;
		const margin = 36;
		const primarySizes = graph.groups.map( ( group ) => Math.max(
			1,
			...group.map( ( node ) => horizontal ? sizes[ node ].width : sizes[ node ].height )
		) );
		const secondarySizes = graph.groups.map( ( group ) => group.reduce(
			( total, node ) => total + ( horizontal ? sizes[ node ].height : sizes[ node ].width ),
			Math.max( 0, group.length - 1 ) * nodeGap
		) );
		const secondaryExtent = Math.max( 1, ...secondarySizes );
		const positions = new Array( sizes.length );
		let primaryOffset = margin;
		graph.groups.forEach( ( group, layerIndex ) => {
			let secondaryOffset = margin + ( secondaryExtent - secondarySizes[ layerIndex ] ) / 2;
			group.forEach( ( node ) => {
				const size = sizes[ node ];
				if ( horizontal ) {
					positions[ node ] = {
						x: primaryOffset + ( primarySizes[ layerIndex ] - size.width ) / 2,
						y: secondaryOffset
					};
					secondaryOffset += size.height + nodeGap;
				} else {
					positions[ node ] = {
						x: secondaryOffset,
						y: primaryOffset + ( primarySizes[ layerIndex ] - size.height ) / 2
					};
					secondaryOffset += size.width + nodeGap;
				}
			} );
			primaryOffset += primarySizes[ layerIndex ] + layerGap;
		} );

		let width = horizontal ? primaryOffset - layerGap + margin : secondaryExtent + margin * 2;
		let height = horizontal ? secondaryExtent + margin * 2 : primaryOffset - layerGap + margin;
		width = Math.max( width, 320 );
		height = Math.max( height, 180 );
		if ( direction === 'right-to-left' ) {
			positions.forEach( ( position, node ) => {
				position.x = width - margin - sizes[ node ].width - ( position.x - margin );
			} );
		} else if ( direction === 'bottom-to-top' ) {
			positions.forEach( ( position, node ) => {
				position.y = height - margin - sizes[ node ].height - ( position.y - margin );
			} );
		}
		const selfLoopCounts = graph.outgoing.map( ( relations, node ) => relations.filter(
			( relation ) => relation.node === node
		).length );
		const maximumSelfLoops = Math.max( 0, ...selfLoopCounts );
		if ( maximumSelfLoops > 0 ) {
			const clearance = 72 + ( maximumSelfLoops - 1 ) * 16;
			if ( horizontal ) {
				width += clearance;
			} else {
				height += clearance;
			}
		}
		return { positions, width, height, horizontal };
	}

	/**
	 * Group nodes by their shortest undirected distance from the selected center.
	 * Direction remains an edge semantic, but either incoming or outgoing neighbors
	 * belong on the next visual ring. Disconnected nodes receive one outer ring so a
	 * malformed or intentionally mixed graph remains finite and inspectable.
	 *
	 * @param {number} nodeCount Number of nodes in the graph
	 * @param {Array<Object>} edges Directed graph edges
	 * @param {number} center Selected center-node index
	 * @return {Array<Array<number>>} Node indexes grouped by radial distance
	 */
	function radialGroups( nodeCount, edges, center ) {
		const adjacency = Array.from( { length: nodeCount }, () => [] );
		edges.forEach( ( edge ) => {
			if ( edge.source !== edge.target ) {
				adjacency[ edge.source ].push( edge.target );
				adjacency[ edge.target ].push( edge.source );
			}
		} );
		const distances = new Array( nodeCount ).fill( -1 );
		const queue = [ center ];
		distances[ center ] = 0;
		while ( queue.length > 0 ) {
			const current = queue.shift();
			adjacency[ current ].forEach( ( neighbor ) => {
				if ( distances[ neighbor ] === -1 ) {
					distances[ neighbor ] = distances[ current ] + 1;
					queue.push( neighbor );
				}
			} );
		}
		const outerDistance = Math.max( 0, ...distances ) + 1;
		const groups = [];
		distances.forEach( ( distance, node ) => {
			const ring = distance === -1 ? outerDistance : distance;
			groups[ ring ] = groups[ ring ] || [];
			groups[ ring ].push( node );
		} );
		return groups.filter( Boolean );
	}

	/**
	 * Position one node at the center and successive graph-distance groups on rings.
	 * Ring radii account for the largest card diagonal and chord length, which gives
	 * a conservative no-overlap bound even when cards have different dimensions.
	 *
	 * @param {Object} data Parsed graph data
	 * @param {Array<Object>} sizes Measured node-card dimensions
	 * @param {number} center Selected center-node index
	 * @param {string} shape Radial distribution shape
	 * @param {string} start Starting side for the first radial node
	 * @return {Object} Calculated node positions and canvas dimensions
	 */
	function positionRadialNodes( data, sizes, center, shape, start ) {
		const groups = radialGroups( sizes.length, data.edges, center );
		const startAngles = {
			top: -Math.PI / 2,
			right: 0,
			bottom: Math.PI / 2,
			left: Math.PI
		};
		let startAngle = startAngles[ start ];
		if ( startAngle === undefined ) {
			startAngle = startAngles.top;
		}
		const positions = new Array( sizes.length );
		const radii = [ 0 ];
		let previousRadius = 0;
		let previousHalfDiagonal = Math.hypot(
			sizes[ center ].width,
			sizes[ center ].height
		) / 2;
		groups.slice( 1 ).forEach( ( group, offset ) => {
			const ring = offset + 1;
			const diagonals = group.map( ( node ) => Math.hypot(
				sizes[ node ].width,
				sizes[ node ].height
			) );
			const largestDiagonal = Math.max( ...diagonals );
			const separationRadius = previousRadius + previousHalfDiagonal +
				largestDiagonal / 2 + 84;
			const capacityRadius = group.length > 1 ?
				( largestDiagonal + 36 ) / ( 2 * Math.sin( Math.PI / group.length ) ) : 0;
			radii[ ring ] = Math.max( separationRadius, capacityRadius );
			previousRadius = radii[ ring ];
			previousHalfDiagonal = largestDiagonal / 2;
		} );

		const halfDiagonals = sizes.map( ( size ) => Math.hypot(
			size.width,
			size.height
		) / 2 );
		const largestHalfDiagonal = Math.max( ...halfDiagonals );
		const outerRadius = Math.max( 0, ...radii );
		const selfLoops = new Array( sizes.length ).fill( 0 );
		data.edges.forEach( ( edge ) => {
			if ( edge.source === edge.target ) {
				selfLoops[ edge.source ]++;
			}
		} );
		const maximumSelfLoops = Math.max( 0, ...selfLoops );
		const loopClearance = maximumSelfLoops > 0 ?
			72 + ( maximumSelfLoops - 1 ) * 16 : 0;
		const margin = 104 + loopClearance;
		const extent = Math.max( 160, outerRadius + largestHalfDiagonal + margin );
		const width = extent * 2;
		const height = extent * 2;
		const centerPoint = { x: extent, y: extent };
		groups.forEach( ( group, ring ) => {
			if ( ring === 0 ) {
				const size = sizes[ center ];
				positions[ center ] = {
					x: centerPoint.x - size.width / 2,
					y: centerPoint.y - size.height / 2
				};
				return;
			}
			// Circle mode staggers alternate rings. Polygon mode keeps common spokes,
			// producing stable regular-polygon vertices for a ring's source-order nodes.
			const stagger = shape === 'circle' && ring % 2 === 0 ? Math.PI / group.length : 0;
			group.forEach( ( node, index ) => {
				const angle = startAngle + stagger + index * Math.PI * 2 / group.length;
				positions[ node ] = {
					x: centerPoint.x + Math.cos( angle ) * radii[ ring ] - sizes[ node ].width / 2,
					y: centerPoint.y + Math.sin( angle ) * radii[ ring ] - sizes[ node ].height / 2
				};
			} );
		} );
		return { positions, width, height, horizontal: false, radial: true, centerPoint };
	}

	function edgePath(
		source,
		target,
		sourceSize,
		targetSize,
		horizontal,
		offset,
		outerLane,
		loopOffset
	) {
		if ( source === target ) {
			if ( horizontal ) {
				const horizontalX = source.x + sourceSize.width;
				const horizontalY = source.y + sourceSize.height / 2;
				const horizontalExtent = 56 + loopOffset;
				return {
					path: 'M ' + horizontalX + ' ' + horizontalY + ' C ' +
						( horizontalX + horizontalExtent ) + ' ' + ( horizontalY - 62 ) +
						', ' + ( horizontalX + horizontalExtent ) + ' ' + ( horizontalY + 62 ) +
						', ' + horizontalX + ' ' + ( horizontalY + 5 ),
					labelX: horizontalX + 48 + loopOffset,
					labelY: horizontalY - 48
				};
			}
			const verticalX = source.x + sourceSize.width / 2;
			const verticalY = source.y + sourceSize.height;
			const verticalExtent = 56 + loopOffset;
			return {
				path: 'M ' + verticalX + ' ' + verticalY + ' C ' +
					( verticalX - 62 ) + ' ' + ( verticalY + verticalExtent ) + ', ' +
					( verticalX + 62 ) + ' ' + ( verticalY + verticalExtent ) + ', ' +
					( verticalX + 5 ) + ' ' + verticalY,
				labelX: verticalX + 48,
				labelY: verticalY + 48 + loopOffset
			};
		}
		const sourceCenter = {
			x: source.x + sourceSize.width / 2,
			y: source.y + sourceSize.height / 2
		};
		const targetCenter = {
			x: target.x + targetSize.width / 2,
			y: target.y + targetSize.height / 2
		};
		if ( horizontal ) {
			const horizontalForward = targetCenter.x >= sourceCenter.x;
			const x1 = source.x + ( horizontalForward ? sourceSize.width : 0 );
			const x2 = target.x + ( horizontalForward ? 0 : targetSize.width );
			if ( Math.abs( targetCenter.x - sourceCenter.x ) > 420 ) {
				return {
					path: 'M ' + x1 + ' ' + sourceCenter.y + ' C ' + x1 + ' ' + ( outerLane + 24 ) +
						', ' + x1 + ' ' + outerLane + ', ' + x1 + ' ' + outerLane + ' L ' +
						x2 + ' ' + outerLane + ' C ' + x2 + ' ' + outerLane + ', ' + x2 + ' ' +
						( outerLane + 24 ) + ', ' + x2 + ' ' + targetCenter.y,
					labelX: ( x1 + x2 ) / 2,
					labelY: outerLane
				};
			}
			const horizontalMiddle = ( x1 + x2 ) / 2;
			return {
				path: 'M ' + x1 + ' ' + sourceCenter.y + ' C ' + horizontalMiddle + ' ' +
					( sourceCenter.y + offset ) + ', ' + horizontalMiddle + ' ' +
					( targetCenter.y + offset ) +
					', ' + x2 + ' ' + targetCenter.y,
				labelX: horizontalMiddle,
				labelY: ( sourceCenter.y + targetCenter.y ) / 2 + offset
			};
		}
		const forward = targetCenter.y >= sourceCenter.y;
		const y1 = source.y + ( forward ? sourceSize.height : 0 );
		const y2 = target.y + ( forward ? 0 : targetSize.height );
		if ( Math.abs( targetCenter.y - sourceCenter.y ) > 360 ) {
			return {
				path: 'M ' + sourceCenter.x + ' ' + y1 + ' C ' + ( outerLane + 24 ) + ' ' + y1 +
					', ' + outerLane + ' ' + y1 + ', ' + outerLane + ' ' + y1 + ' L ' +
					outerLane + ' ' + y2 + ' C ' + outerLane + ' ' + y2 + ', ' +
					( outerLane + 24 ) + ' ' + y2 + ', ' + targetCenter.x + ' ' + y2,
				labelX: outerLane,
				labelY: ( y1 + y2 ) / 2
			};
		}
		const middle = ( y1 + y2 ) / 2;
		return {
			path: 'M ' + sourceCenter.x + ' ' + y1 + ' C ' + ( sourceCenter.x + offset ) + ' ' +
				middle + ', ' + ( targetCenter.x + offset ) + ' ' + middle + ', ' +
				targetCenter.x + ' ' + y2,
			labelX: ( sourceCenter.x + targetCenter.x ) / 2 + offset,
			labelY: middle
		};
	}

	/**
	 * Connect arbitrary radial cards at their rectangle boundaries.
	 *
	 * @param {Object} source Source-node position
	 * @param {Object} target Target-node position
	 * @param {Object} sourceSize Source-card dimensions
	 * @param {Object} targetSize Target-card dimensions
	 * @param {number} offset Perpendicular path offset
	 * @param {number} loopOffset Repeated-loop offset
	 * @return {Object} SVG path data and label coordinates
	 */
	function radialEdgePath( source, target, sourceSize, targetSize, offset, loopOffset ) {
		if ( source === target ) {
			return edgePath( source, target, sourceSize, targetSize, true, offset, 0, loopOffset );
		}
		const sourceCenter = {
			x: source.x + sourceSize.width / 2,
			y: source.y + sourceSize.height / 2
		};
		const targetCenter = {
			x: target.x + targetSize.width / 2,
			y: target.y + targetSize.height / 2
		};
		const dx = targetCenter.x - sourceCenter.x;
		const dy = targetCenter.y - sourceCenter.y;
		const length = Math.max( 1, Math.hypot( dx, dy ) );
		const sourceScale = 1 / Math.max(
			Math.abs( dx ) / Math.max( 1, sourceSize.width / 2 ),
			Math.abs( dy ) / Math.max( 1, sourceSize.height / 2 )
		);
		const targetScale = 1 / Math.max(
			Math.abs( dx ) / Math.max( 1, targetSize.width / 2 ),
			Math.abs( dy ) / Math.max( 1, targetSize.height / 2 )
		);
		const x1 = sourceCenter.x + dx * sourceScale;
		const y1 = sourceCenter.y + dy * sourceScale;
		const x2 = targetCenter.x - dx * targetScale;
		const y2 = targetCenter.y - dy * targetScale;
		const normalX = -dy / length;
		const normalY = dx / length;
		const controlX = ( x1 + x2 ) / 2 + normalX * offset;
		const controlY = ( y1 + y2 ) / 2 + normalY * offset;
		return {
			path: 'M ' + x1 + ' ' + y1 + ' Q ' + controlX + ' ' + controlY + ' ' + x2 + ' ' + y2,
			labelX: controlX,
			labelY: controlY
		};
	}

	/**
	 * Build an edge label from server-rendered pieces. The icon is cloned from a
	 * MediaWiki-generated thumbnail, preserving repository URL handling and CSP.
	 *
	 * @param {HTMLElement} label Visual label container
	 * @param {Object} edge Parsed edge data
	 */
	function populateEdgeLabel( label, edge ) {
		if ( edge.link ) {
			const link = edge.link.cloneNode( true );
			const linkedIcon = link.querySelector( '.mw-monster-evolution-edge-icon-source img' );
			if ( linkedIcon ) {
				linkedIcon.classList.add( 'mw-monster-evolution-edge-icon' );
			}
			label.appendChild( link );
			return;
		}
		if ( edge.icon ) {
			const icon = edge.icon.cloneNode( true );
			icon.removeAttribute( 'id' );
			icon.classList.add( 'mw-monster-evolution-edge-icon' );
			icon.setAttribute( 'aria-hidden', 'true' );
			label.appendChild( icon );
		}
		if ( edge.label !== '' ) {
			const text = document.createElement( 'span' );
			text.className = 'mw-monster-evolution-edge-label-text';
			text.textContent = edge.label;
			label.appendChild( text );
		}
	}

	function drawEdges( root, data, layoutResult, sizes, markerId ) {
		const svg = root.querySelector( '.mw-monster-evolution-svg' );
		const canvas = root.querySelector( '.mw-monster-evolution-canvas' );
		if ( !svg || !canvas ) {
			return;
		}
		while ( svg.firstChild ) {
			svg.removeChild( svg.firstChild );
		}
		forEachElement(
			canvas.querySelectorAll( '.mw-monster-evolution-edge-label' ),
			( label ) => label.remove()
		);
		const definitions = element( 'defs', {} );
		const marker = element( 'marker', {
			id: markerId,
			viewBox: '0 0 10 10',
			refX: 9,
			refY: 5,
			markerWidth: 7,
			markerHeight: 7,
			orient: 'auto-start-reverse'
		} );
		marker.appendChild( element( 'path', { d: 'M 0 0 L 10 5 L 0 10 z' } ) );
		definitions.appendChild( marker );
		svg.appendChild( definitions );
		svg.setAttribute( 'viewBox', '0 0 ' + layoutResult.width + ' ' + layoutResult.height );
		svg.setAttribute( 'width', String( layoutResult.width ) );
		svg.setAttribute( 'height', String( layoutResult.height ) );

		const pairs = new Set( data.edges.map( ( edge ) => edge.source + ':' + edge.target ) );
		const parallelCounts = new Map();
		const parallelHasIcons = new Map();
		const parallelSeen = new Map();
		data.edges.forEach( ( edge ) => {
			const key = edge.source + ':' + edge.target;
			parallelCounts.set( key, ( parallelCounts.get( key ) || 0 ) + 1 );
			parallelHasIcons.set( key, parallelHasIcons.get( key ) || Boolean( edge.icon ) );
		} );
		data.edges.forEach( ( edge, edgeIndex ) => {
			const key = edge.source + ':' + edge.target;
			const occurrence = parallelSeen.get( key ) || 0;
			parallelSeen.set( key, occurrence + 1 );
			const parallelCount = parallelCounts.get( key ) || 1;
			const reverse = edge.source !== edge.target && pairs.has( edge.target + ':' + edge.source );
			// Icons make labels substantially taller or wider than text-only pills.
			// Reserve larger parallel lanes for the whole directed pair so its curves
			// and labels remain consistently aligned and do not collide.
			const parallelSpacing = parallelHasIcons.get( key ) ?
				( layoutResult.radial || layoutResult.horizontal ? 52 : 100 ) : 18;
			const parallelOffset = ( occurrence - ( parallelCount - 1 ) / 2 ) * parallelSpacing;
			const reverseOffset = reverse ? ( edge.source < edge.target ? -14 : 14 ) : 0;
			const offset = parallelOffset + reverseOffset;
			const geometry = layoutResult.radial ? radialEdgePath(
				layoutResult.positions[ edge.source ],
				layoutResult.positions[ edge.target ],
				sizes[ edge.source ],
				sizes[ edge.target ],
				offset,
				occurrence * 16
			) : edgePath(
				layoutResult.positions[ edge.source ],
				layoutResult.positions[ edge.target ],
				sizes[ edge.source ],
				sizes[ edge.target ],
				layoutResult.horizontal,
				offset,
				18 + ( edgeIndex % 3 ) * 10,
				occurrence * 16
			);
			const path = element( 'path', {
				d: geometry.path,
				class: 'mw-monster-evolution-edge-path mw-monster-evolution-edge-path--' + edge.type,
				'data-edge-index': edgeIndex,
				'marker-end': 'url(#' + markerId + ')'
			} );
			svg.appendChild( path );
			if ( edge.label !== '' || edge.icon || edge.link ) {
				const label = document.createElement( 'div' );
				// CSS classes:
				// * mw-monster-evolution-edge-label--icon-above
				// * mw-monster-evolution-edge-label--icon-next-to
				label.className = 'mw-monster-evolution-edge-label ' +
					'mw-monster-evolution-edge-label--icon-' + edge.iconPosition;
				label.setAttribute( 'data-edge-index', String( edgeIndex ) );
				populateEdgeLabel( label, edge );
				if ( edge.label !== '' ) {
					label.title = edge.label;
				}
				label.style.left = geometry.labelX + 'px';
				label.style.top = geometry.labelY + 'px';
				canvas.appendChild( label );
			}
		} );
	}

	function applyScale( root, state, nextScale ) {
		const stage = root.querySelector( '.mw-monster-evolution-stage' );
		const canvas = root.querySelector( '.mw-monster-evolution-canvas' );
		if ( !stage || !canvas ) {
			return;
		}
		state.scale = Math.min( 2, Math.max( 0.45, nextScale ) );
		canvas.style.transform = 'scale(' + state.scale + ')';
		stage.style.width = state.width * state.scale + 'px';
		stage.style.height = state.height * state.scale + 'px';
	}

	function layout( root, data, state, markerId ) {
		const canvas = root.querySelector( '.mw-monster-evolution-canvas' );
		if ( !canvas || data.nodes.length === 0 ) {
			return;
		}
		// Measurements are divided by the current zoom so relayout always works in
		// unscaled canvas coordinates and does not compound previous transforms.
		const sizes = data.nodes.map( ( node ) => ( {
			width: Math.ceil( node.getBoundingClientRect().width / state.scale ),
			height: Math.ceil( node.getBoundingClientRect().height / state.scale )
		} ) );
		const layoutMode = root.getAttribute( 'data-layout' ) || 'layered';
		let result;
		if ( layoutMode === 'radial' ) {
			const requestedCenter = numberAttribute( root, 'data-center' );
			const hasRequestedCenter = requestedCenter >= 0 && requestedCenter < data.nodes.length;
			const center = hasRequestedCenter ? requestedCenter : 0;
			result = positionRadialNodes(
				data,
				sizes,
				center,
				root.getAttribute( 'data-radial-shape' ) || 'circle',
				root.getAttribute( 'data-radial-start' ) || 'top'
			);
		} else {
			const graph = layerGraph( data.nodes.length, data.edges );
			const direction = root.getAttribute( 'data-direction' ) || 'left-to-right';
			result = positionNodes( graph, sizes, direction );
		}
		state.width = result.width;
		state.height = result.height;
		canvas.style.width = result.width + 'px';
		canvas.style.height = result.height + 'px';
		data.nodes.forEach( ( node, index ) => {
			node.style.left = result.positions[ index ].x + 'px';
			node.style.top = result.positions[ index ].y + 'px';
		} );
		drawEdges( root, data, result, sizes, markerId );
		applyScale( root, state, state.scale );
	}

	function highlight( root, data, graph, selected ) {
		const activeNodes = new Set( [ selected ] );
		const activeEdges = new Set();
		const visit = ( adjacency ) => {
			// Iterative traversal handles deep and cyclic graphs without call-stack risk.
			const queue = [ selected ];
			const visited = new Set( queue );
			while ( queue.length > 0 ) {
				const current = queue.shift();
				adjacency[ current ].forEach( ( relation ) => {
					activeEdges.add( relation.edge );
					activeNodes.add( relation.node );
					if ( !visited.has( relation.node ) ) {
						visited.add( relation.node );
						queue.push( relation.node );
					}
				} );
			}
		};
		visit( graph.outgoing );
		visit( graph.incoming );
		root.classList.add( 'mw-monster-evolution--highlighting' );
		data.nodes.forEach( ( node, index ) => {
			node.classList.toggle( 'mw-monster-evolution-is-dimmed', !activeNodes.has( index ) );
			const button = node.querySelector( '.mw-monster-evolution-highlight' );
			if ( button ) {
				button.setAttribute( 'aria-pressed', index === selected ? 'true' : 'false' );
			}
		} );
		forEachElement( root.querySelectorAll( '[data-edge-index]' ), ( edge ) => {
			const index = numberAttribute( edge, 'data-edge-index' );
			edge.classList.toggle( 'mw-monster-evolution-is-dimmed', !activeEdges.has( index ) );
		} );
	}

	function clearHighlight( root ) {
		root.classList.remove( 'mw-monster-evolution--highlighting' );
		forEachElement( root.querySelectorAll( '.mw-monster-evolution-is-dimmed' ), ( node ) => {
			node.classList.remove( 'mw-monster-evolution-is-dimmed' );
		} );
		forEachElement( root.querySelectorAll( '.mw-monster-evolution-highlight' ), ( button ) => {
			button.setAttribute( 'aria-pressed', 'false' );
		} );
	}

	function initialize( root ) {
		if ( initialized.has( root ) ) {
			return;
		}
		// MediaWiki can emit wikipage.content more than once for the same subtree.
		// WeakSet prevents duplicate controls, SVG paths, observers, and listeners.
		initialized.add( root );
		const data = readGraph( root );
		if ( data.nodes.length === 0 ) {
			return;
		}
		const graph = layerGraph( data.nodes.length, data.edges );
		const state = { width: 1, height: 1, scale: 1, selected: -1 };
		const markerId = 'mw-monster-evolution-arrow-' + ++instanceCounter;
		root.classList.add( 'mw-monster-evolution--enhanced' );
		layout( root, data, state, markerId );

		data.nodes.forEach( ( node, index ) => {
			const button = node.querySelector( '.mw-monster-evolution-highlight' );
			if ( !button ) {
				return;
			}
			button.addEventListener( 'click', () => {
				if ( state.selected === index ) {
					state.selected = -1;
					clearHighlight( root );
				} else {
					state.selected = index;
					highlight( root, data, graph, index );
				}
			} );
			button.addEventListener( 'keydown', ( event ) => {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					button.click();
				}
			} );
		} );

		forEachElement( root.querySelectorAll( '[data-zoom-action]' ), ( button ) => {
			button.addEventListener( 'click', () => {
				const action = button.getAttribute( 'data-zoom-action' );
				const viewport = root.querySelector( '.mw-monster-evolution-viewport' );
				if ( action === 'in' ) {
					applyScale( root, state, state.scale + 0.15 );
				} else if ( action === 'out' ) {
					applyScale( root, state, state.scale - 0.15 );
				} else if ( action === 'reset' ) {
					applyScale( root, state, 1 );
				} else if ( action === 'fit' && viewport ) {
					const fitScale = Math.min( 1, ( viewport.clientWidth - 12 ) / state.width );
					applyScale( root, state, fitScale );
				}
			} );
		} );

		if ( typeof ResizeObserver !== 'undefined' ) {
			let resizeFrame = 0;
			const observer = new ResizeObserver( () => {
				cancelAnimationFrame( resizeFrame );
				resizeFrame = requestAnimationFrame( () => {
					layout( root, data, state, markerId );
				} );
			} );
			observer.observe( root );
		}
	}

	function initializeWithin( content ) {
		const root = content && content[ 0 ] ? content[ 0 ] : content;
		if ( !root || !root.querySelectorAll ) {
			return;
		}
		if ( root.matches && root.matches( '.mw-monster-evolution' ) ) {
			initialize( root );
		}
		forEachElement( root.querySelectorAll( '.mw-monster-evolution' ), initialize );
	}

	mw.hook( 'wikipage.content' ).add( initializeWithin );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initializeWithin( document ), { once: true } );
	} else {
		initializeWithin( document );
	}
}() );
