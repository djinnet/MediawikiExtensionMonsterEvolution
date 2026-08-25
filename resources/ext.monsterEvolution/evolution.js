( function () {
	'use strict';

	const SVG_NS = 'http://www.w3.org/2000/svg';
	const initialized = new WeakSet();
	let instanceCounter = 0;

	function element( name, attributes ) {
		const node = document.createElementNS( SVG_NS, name );
		Object.keys( attributes ).forEach( ( key ) => {
			node.setAttribute( key, String( attributes[ key ] ) );
		} );
		return node;
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
				edges.push( {
					source,
					target,
					type: edgeElement.getAttribute( 'data-edge-type' ) || 'custom',
					label: edgeElement.getAttribute( 'data-edge-label' ) || '',
					element: edgeElement
				} );
			}
		} );
		return { nodes, edges };
	}

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
		const maximumSelfLoops = Math.max( 0, ...graph.outgoing.map( ( relations, node ) =>
			relations.filter( ( relation ) => relation.node === node ).length
		) );
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

	function edgePath( source, target, sourceSize, targetSize, horizontal, offset, outerLane, loopOffset ) {
		if ( source === target ) {
			if ( horizontal ) {
				const x = source.x + sourceSize.width;
				const y = source.y + sourceSize.height / 2;
				const extent = 56 + loopOffset;
				return {
					path: 'M ' + x + ' ' + y + ' C ' + ( x + extent ) + ' ' + ( y - 62 ) +
						', ' + ( x + extent ) + ' ' + ( y + 62 ) + ', ' + x + ' ' + ( y + 5 ),
					labelX: x + 48 + loopOffset,
					labelY: y - 48
				};
			}
			const x = source.x + sourceSize.width / 2;
			const y = source.y + sourceSize.height;
			const extent = 56 + loopOffset;
			return {
				path: 'M ' + x + ' ' + y + ' C ' + ( x - 62 ) + ' ' + ( y + extent ) +
					', ' + ( x + 62 ) + ' ' + ( y + extent ) + ', ' + ( x + 5 ) + ' ' + y,
				labelX: x + 48,
				labelY: y + 48 + loopOffset
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
			const forward = targetCenter.x >= sourceCenter.x;
			const x1 = source.x + ( forward ? sourceSize.width : 0 );
			const x2 = target.x + ( forward ? 0 : targetSize.width );
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
			const middle = ( x1 + x2 ) / 2;
			return {
				path: 'M ' + x1 + ' ' + sourceCenter.y + ' C ' + middle + ' ' +
					( sourceCenter.y + offset ) + ', ' + middle + ' ' + ( targetCenter.y + offset ) +
					', ' + x2 + ' ' + targetCenter.y,
				labelX: middle,
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

	function drawEdges( root, data, layout, sizes, markerId ) {
		const svg = root.querySelector( '.mw-monster-evolution-svg' );
		const canvas = root.querySelector( '.mw-monster-evolution-canvas' );
		if ( !svg || !canvas ) {
			return;
		}
		while ( svg.firstChild ) {
			svg.removeChild( svg.firstChild );
		}
		canvas.querySelectorAll( '.mw-monster-evolution-edge-label' ).forEach( ( label ) => label.remove() );
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
		svg.setAttribute( 'viewBox', '0 0 ' + layout.width + ' ' + layout.height );
		svg.setAttribute( 'width', String( layout.width ) );
		svg.setAttribute( 'height', String( layout.height ) );

		const pairs = new Set( data.edges.map( ( edge ) => edge.source + ':' + edge.target ) );
		const parallelCounts = new Map();
		const parallelSeen = new Map();
		data.edges.forEach( ( edge ) => {
			const key = edge.source + ':' + edge.target;
			parallelCounts.set( key, ( parallelCounts.get( key ) || 0 ) + 1 );
		} );
		data.edges.forEach( ( edge, edgeIndex ) => {
			const key = edge.source + ':' + edge.target;
			const occurrence = parallelSeen.get( key ) || 0;
			parallelSeen.set( key, occurrence + 1 );
			const parallelCount = parallelCounts.get( key ) || 1;
			const reverse = edge.source !== edge.target && pairs.has( edge.target + ':' + edge.source );
			const parallelOffset = ( occurrence - ( parallelCount - 1 ) / 2 ) * 18;
			const offset = parallelOffset + ( reverse ? ( edge.source < edge.target ? -14 : 14 ) : 0 );
			const geometry = edgePath(
				layout.positions[ edge.source ],
				layout.positions[ edge.target ],
				sizes[ edge.source ],
				sizes[ edge.target ],
				layout.horizontal,
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
			if ( edge.label !== '' ) {
				const label = document.createElement( 'div' );
				label.className = 'mw-monster-evolution-edge-label';
				label.setAttribute( 'data-edge-index', String( edgeIndex ) );
				label.textContent = edge.label;
				label.title = edge.label;
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
		const sizes = data.nodes.map( ( node ) => ( {
			width: Math.ceil( node.getBoundingClientRect().width / state.scale ),
			height: Math.ceil( node.getBoundingClientRect().height / state.scale )
		} ) );
		const graph = layerGraph( data.nodes.length, data.edges );
		const direction = root.getAttribute( 'data-direction' ) || 'left-to-right';
		const result = positionNodes( graph, sizes, direction );
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
		root.querySelectorAll( '[data-edge-index]' ).forEach( ( edge ) => {
			const index = numberAttribute( edge, 'data-edge-index' );
			edge.classList.toggle( 'mw-monster-evolution-is-dimmed', !activeEdges.has( index ) );
		} );
	}

	function clearHighlight( root ) {
		root.classList.remove( 'mw-monster-evolution--highlighting' );
		root.querySelectorAll( '.mw-monster-evolution-is-dimmed' ).forEach( ( node ) => {
			node.classList.remove( 'mw-monster-evolution-is-dimmed' );
		} );
		root.querySelectorAll( '.mw-monster-evolution-highlight' ).forEach( ( button ) => {
			button.setAttribute( 'aria-pressed', 'false' );
		} );
	}

	function initialize( root ) {
		if ( initialized.has( root ) ) {
			return;
		}
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

		root.querySelectorAll( '[data-zoom-action]' ).forEach( ( button ) => {
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
					applyScale( root, state, Math.min( 1, ( viewport.clientWidth - 12 ) / state.width ) );
				}
			} );
		} );

		if ( 'ResizeObserver' in window ) {
			let resizeFrame = 0;
			const observer = new ResizeObserver( () => {
				window.cancelAnimationFrame( resizeFrame );
				resizeFrame = window.requestAnimationFrame( () => layout( root, data, state, markerId ) );
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
		root.querySelectorAll( '.mw-monster-evolution' ).forEach( initialize );
	}

	mw.hook( 'wikipage.content' ).add( initializeWithin );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => initializeWithin( document ), { once: true } );
	} else {
		initializeWithin( document );
	}
}() );
