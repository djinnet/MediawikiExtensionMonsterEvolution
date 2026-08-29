( function () {
	'use strict';

	function createElement( name, className, text ) {
		const node = document.createElement( name );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined ) {
			node.textContent = text;
		}
		return node;
	}

	function appendNode( canvas, name, index ) {
		const node = createElement( 'article', 'mw-monster-evolution-node' );
		node.setAttribute( 'data-node-index', String( index ) );
		node.appendChild( createElement( 'span', 'mw-monster-evolution-node-name', name ) );
		const button = createElement( 'button', 'mw-monster-evolution-highlight', '◎' );
		button.type = 'button';
		button.setAttribute( 'aria-pressed', 'false' );
		button.setAttribute( 'aria-label', 'Highlight paths through ' + name );
		node.appendChild( button );
		canvas.appendChild( node );
	}

	function appendEdge( list, edge, index ) {
		const item = createElement( 'li', 'mw-monster-evolution-edge' );
		item.setAttribute( 'data-edge-index', String( index ) );
		if ( edge.source !== null ) {
			item.setAttribute( 'data-source', String( edge.source ) );
		}
		if ( edge.target !== null ) {
			item.setAttribute( 'data-target', String( edge.target ) );
		}
		item.setAttribute( 'data-edge-type', edge.type || 'custom' );
		item.setAttribute( 'data-edge-label', edge.label || '' );
		item.setAttribute( 'data-edge-icon-position', edge.iconPosition || 'next-to' );
		let iconSource = null;
		if ( edge.icon ) {
			const source = createElement( 'span', 'mw-monster-evolution-edge-icon-source' );
			const icon = document.createElement( 'img' );
			icon.alt = '';
			icon.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" ' +
				'width="24" height="24"%3E%3Ccircle cx="12" cy="12" r="10" ' +
				'fill="%236ea8ff"/%3E%3C/svg%3E';
			source.appendChild( icon );
			iconSource = source;
		}
		if ( edge.link ) {
			const link = createElement( 'a', 'mw-monster-evolution-edge-label-link' );
			link.href = edge.link;
			if ( iconSource ) {
				link.appendChild( iconSource );
			}
			if ( edge.label ) {
				link.appendChild( createElement(
					'span',
					'mw-monster-evolution-edge-label-text',
					edge.label
				) );
			}
			item.appendChild( link );
		} else if ( iconSource ) {
			item.appendChild( iconSource );
		}
		item.appendChild( createElement( 'span', '', edge.summary || edge.label || 'Edge' ) );
		list.appendChild( item );
	}

	function buildGraph( definition ) {
		const section = createElement( 'section', 'fixture-case' );
		section.setAttribute( 'data-case', definition.id );
		section.appendChild( createElement( 'h2', '', definition.title ) );

		const root = createElement(
			'div',
			'mw-monster-evolution mw-monster-evolution--default mw-monster-evolution--' +
				definition.direction + ' mw-monster-evolution--layout-' + ( definition.layout || 'layered' )
		);
		root.setAttribute( 'data-direction', definition.direction );
		root.setAttribute( 'data-layout', definition.layout || 'layered' );
		if ( definition.layout === 'radial' ) {
			root.setAttribute( 'data-center', String( definition.center ) );
			root.setAttribute( 'data-radial-shape', definition.radialShape || 'circle' );
			root.setAttribute( 'data-radial-start', definition.radialStart || 'top' );
		}
		root.setAttribute( 'data-zoom', definition.controls ? 'true' : 'false' );
		root.setAttribute( 'aria-label', definition.title );

		if ( definition.controls ) {
			const controls = createElement( 'div', 'mw-monster-evolution-controls' );
			[ 'in', 'out', 'reset', 'fit' ].forEach( ( action ) => {
				const button = createElement( 'button', 'mw-monster-evolution-control', action );
				button.type = 'button';
				button.setAttribute( 'data-zoom-action', action );
				controls.appendChild( button );
			} );
			root.appendChild( controls );
		}

		const viewport = createElement( 'div', 'mw-monster-evolution-viewport' );
		viewport.tabIndex = 0;
		const stage = createElement( 'div', 'mw-monster-evolution-stage' );
		const canvas = createElement( 'div', 'mw-monster-evolution-canvas' );
		const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		svg.setAttribute( 'class', 'mw-monster-evolution-svg' );
		svg.setAttribute( 'aria-hidden', 'true' );
		canvas.appendChild( svg );
		definition.nodes.forEach( ( name, index ) => appendNode( canvas, name, index ) );
		stage.appendChild( canvas );
		viewport.appendChild( stage );
		root.appendChild( viewport );

		const relationships = createElement( 'section', 'mw-monster-evolution-relationships' );
		const list = createElement( 'ul' );
		definition.edges.forEach( ( edge, index ) => appendEdge( list, edge, index ) );
		relationships.appendChild( list );
		root.appendChild( relationships );
		section.appendChild( root );
		document.querySelector( '#fixtures' ).appendChild( section );
	}

	const simpleEdges = [ { source: 0, target: 1, label: 'Level 10', type: 'level' } ];
	[
		'left-to-right',
		'right-to-left',
		'top-to-bottom',
		'bottom-to-top'
	].forEach( ( direction ) => buildGraph( {
		id: direction,
		title: direction,
		direction,
		nodes: [ 'Start', 'Finish' ],
		edges: simpleEdges.concat( [ {
			source: 1,
			target: 1,
			label: 'Stay in form',
			type: 'temporary'
		} ] ),
		controls: direction === 'left-to-right'
	} ) );

	buildGraph( {
		id: 'cycle',
		title: 'Cycle, reciprocal edges, and repeated self-loops',
		direction: 'left-to-right',
		nodes: [ 'Alpha', 'Beta', 'Gamma' ],
		edges: [
			{ source: 0, target: 1, label: 'Forward' },
			{ source: 1, target: 0, label: 'Reverse', type: 'reversible' },
			{ source: 1, target: 2, label: 'Advance' },
			{ source: 2, target: 0, label: 'Restart' },
			{ source: 2, target: 2, label: 'Refresh one', type: 'temporary' },
			{ source: 2, target: 2, label: 'Refresh two', type: 'temporary' }
		]
	} );

	buildGraph( {
		id: 'parallel',
		title: 'Parallel transitions',
		direction: 'left-to-right',
		nodes: [ 'Base form', 'Many-path target' ],
		edges: [
			{
				source: 0,
				target: 1,
				label: 'Level',
				icon: true,
				iconPosition: 'next-to',
				link: '/wiki/Fire_Stone'
			},
			{ source: 0, target: 1, label: 'Item', icon: true, iconPosition: 'above' },
			{ source: 0, target: 1, icon: true, iconPosition: 'next-to' }
		]
	} );

	buildGraph( {
		id: 'radial-eevee',
		title: 'Eevee radial circle',
		direction: 'left-to-right',
		layout: 'radial',
		center: 0,
		radialShape: 'circle',
		radialStart: 'top',
		nodes: [
			'Eevee', 'Vaporeon', 'Jolteon', 'Flareon', 'Espeon',
			'Umbreon', 'Leafeon', 'Glaceon', 'Sylveon'
		],
		edges: Array.from( { length: 8 }, ( unused, index ) => ( {
			source: 0,
			target: index + 1,
			label: 'Method ' + ( index + 1 ),
			type: 'item'
		} ) )
	} );

	buildGraph( {
		id: 'radial-rings',
		title: 'Radial polygon with multiple rings',
		direction: 'left-to-right',
		layout: 'radial',
		center: 0,
		radialShape: 'polygon',
		radialStart: 'right',
		nodes: [ 'Center', 'A', 'B', 'C', 'D', 'A2', 'B2', 'Disconnected' ],
		edges: [
			{ source: 0, target: 1, label: 'A' },
			{ source: 0, target: 2, label: 'B' },
			{ source: 0, target: 3, label: 'C' },
			{ source: 0, target: 4, label: 'D' },
			{ source: 1, target: 5, label: 'A2' },
			{ source: 2, target: 6, label: 'B2' }
		]
	} );

	buildGraph( {
		id: 'radial-nonfirst-center',
		title: 'Radial center declared after another node',
		direction: 'left-to-right',
		layout: 'radial',
		center: 1,
		radialShape: 'circle',
		radialStart: 'bottom',
		nodes: [ 'First declaration', 'Selected center', 'Last declaration' ],
		edges: [
			{ source: 1, target: 0, label: 'First' },
			{ source: 1, target: 2, label: 'Last' },
			{ source: 1, target: 1, label: 'Center loop' }
		]
	} );

	buildGraph( {
		id: 'disconnected',
		title: 'Multiple roots and disconnected components',
		direction: 'top-to-bottom',
		nodes: [
			'First root with a deliberately long unbroken-name-to-test-wrapping',
			'First result',
			'Second root',
			'Second result',
			'Standalone form'
		],
		edges: [
			{ source: 0, target: 1, label: 'A condition label which wraps safely' },
			{ source: 2, target: 3, label: 'Other path' }
		]
	} );

	buildGraph( {
		id: 'malformed-data',
		title: 'Malformed client metadata is ignored',
		direction: 'left-to-right',
		nodes: [ 'Safe source', 'Safe target' ],
		edges: [
			{ source: null, target: 0, label: 'Missing source' },
			{ source: -1, target: 0, label: 'Negative source' },
			{ source: 0, target: 99, label: 'Large target' },
			{ source: 0, target: 1, label: 'Valid edge' }
		]
	} );

	const stressNodes = Array.from( { length: 24 }, ( unused, index ) => 'Stress node ' + index );
	const stressEdges = [];
	stressNodes.forEach( ( unused, index ) => {
		stressEdges.push( { source: index, target: ( index + 1 ) % stressNodes.length } );
		stressEdges.push( { source: index, target: ( index + 5 ) % stressNodes.length } );
	} );
	buildGraph( {
		id: 'dense-cycle',
		title: 'Dense bounded cyclic graph',
		direction: 'left-to-right',
		nodes: stressNodes,
		edges: stressEdges
	} );

	function rectangle( node ) {
		const bounds = node.getBoundingClientRect();
		return {
			left: bounds.left,
			top: bounds.top,
			right: bounds.right,
			bottom: bounds.bottom,
			width: bounds.width,
			height: bounds.height
		};
	}

	function intersects( first, second ) {
		return first.left < second.right && first.right > second.left &&
			first.top < second.bottom && first.bottom > second.top;
	}

	window.runMonsterEvolutionEdgeCases = function () {
		const failures = [];
		let assertions = 0;
		const assert = function ( condition, message ) {
			assertions++;
			if ( !condition ) {
				failures.push( message );
			}
		};
		const cases = Array.from( document.querySelectorAll( '[data-case]' ) );
		assert( document.body.scrollWidth <= window.innerWidth,
			'responsive: graphs do not create page-level horizontal overflow' );
		assert( window.innerWidth > 720 || cases.some( ( section ) => {
			const viewport = section.querySelector( '.mw-monster-evolution-viewport' );
			return viewport.scrollWidth > viewport.clientWidth;
		} ), 'responsive: wide mobile graphs scroll inside their own viewport' );

		cases.forEach( ( section ) => {
			const id = section.getAttribute( 'data-case' );
			const caseRoot = section.querySelector( '.mw-monster-evolution' );
			const caseCanvas = caseRoot.querySelector( '.mw-monster-evolution-canvas' );
			const canvasBounds = rectangle( caseCanvas );
			const nodes = Array.from( caseRoot.querySelectorAll( '.mw-monster-evolution-node' ) );
			const paths = Array.from( caseRoot.querySelectorAll( '.mw-monster-evolution-edge-path' ) );
			const labels = Array.from( caseRoot.querySelectorAll( '.mw-monster-evolution-edge-label' ) );
			assert(
				caseRoot.classList.contains( 'mw-monster-evolution--enhanced' ),
				id + ': enhancement ran'
			);
			const hasBoundedCanvas = canvasBounds.width >= 320 && canvasBounds.height >= 180;
			assert( hasBoundedCanvas, id + ': bounded canvas exists' );
			paths.forEach( ( path, index ) => {
				assert( !/(?:NaN|undefined|Infinity)/.test( path.getAttribute( 'd' ) || '' ),
					id + ': path ' + index + ' contains finite coordinates' );
			} );
			nodes.forEach( ( node, index ) => {
				const bounds = rectangle( node );
				const horizontallyContained = bounds.left >= canvasBounds.left - 1 &&
					bounds.right <= canvasBounds.right + 1;
				const verticallyContained = bounds.top >= canvasBounds.top - 1 &&
					bounds.bottom <= canvasBounds.bottom + 1;
				assert(
					horizontallyContained && verticallyContained,
					id + ': node ' + index + ' stays inside the canvas'
				);
				for ( let other = index + 1; other < nodes.length; other++ ) {
					assert( !intersects( bounds, rectangle( nodes[ other ] ) ),
						id + ': nodes ' + index + ' and ' + other + ' do not overlap' );
				}
			} );
			labels.forEach( ( label, index ) => {
				const bounds = rectangle( label );
				const horizontallyContained = bounds.left >= canvasBounds.left - 1 &&
					bounds.right <= canvasBounds.right + 1;
				const verticallyContained = bounds.top >= canvasBounds.top - 1 &&
					bounds.bottom <= canvasBounds.bottom + 1;
				assert(
					horizontallyContained && verticallyContained,
					id + ': label ' + index + ' stays inside the canvas'
				);
			} );
		} );

		[
			[ 'left-to-right', 'left', 1 ],
			[ 'right-to-left', 'left', -1 ],
			[ 'top-to-bottom', 'top', 1 ],
			[ 'bottom-to-top', 'top', -1 ]
		].forEach( ( check ) => {
			const nodes = document.querySelector( '[data-case="' + check[ 0 ] + '"]' )
				.querySelectorAll( '.mw-monster-evolution-node' );
			const targetPosition = rectangle( nodes[ 1 ] )[ check[ 1 ] ];
			const sourcePosition = rectangle( nodes[ 0 ] )[ check[ 1 ] ];
			assert(
				Math.sign( targetPosition - sourcePosition ) === check[ 2 ],
				check[ 0 ] + ': source and target follow direction'
			);
		} );

		const malformed = document.querySelector( '[data-case="malformed-data"]' );
		assert( malformed.querySelectorAll( '.mw-monster-evolution-edge-path' ).length === 1,
			'malformed-data: only the valid edge is rendered' );

		const parallel = document.querySelector( '[data-case="parallel"]' );
		const parallelPaths = Array.from( parallel.querySelectorAll( '.mw-monster-evolution-edge-path' ) )
			.map( ( path ) => path.getAttribute( 'd' ) );
		assert( new Set( parallelPaths ).size === 3, 'parallel: repeated transitions use distinct curves' );
		const parallelLabels = Array.from( parallel.querySelectorAll( '.mw-monster-evolution-edge-label' ) )
			.map( ( label ) => label.style.top + ':' + label.style.left );
		assert( new Set( parallelLabels ).size === 3, 'parallel: repeated labels use distinct positions' );
		const parallelLabelNodes = Array.from(
			parallel.querySelectorAll( '.mw-monster-evolution-edge-label' )
		);
		for ( let index = 0; index < parallelLabelNodes.length; index++ ) {
			for ( let other = index + 1; other < parallelLabelNodes.length; other++ ) {
				const currentBounds = rectangle( parallelLabelNodes[ index ] );
				const otherBounds = rectangle( parallelLabelNodes[ other ] );
				assert( !intersects( currentBounds, otherBounds ),
					'parallel: icon labels ' + index + ' and ' + other + ' do not overlap' );
			}
		}
		const iconLabels = parallel.querySelectorAll( '.mw-monster-evolution-edge-label' );
		assert( parallel.querySelectorAll( '.mw-monster-evolution-edge-icon' ).length === 3,
			'icons: server-rendered thumbnails are cloned into every visual label' );
		assert( getComputedStyle( iconLabels[ 0 ] ).flexDirection === 'row',
			'icons: next-to position uses a horizontal label layout' );
		assert( getComputedStyle( iconLabels[ 1 ] ).flexDirection === 'column',
			'icons: above position uses a vertical label layout' );
		assert( iconLabels[ 2 ].querySelector( '.mw-monster-evolution-edge-label-text' ) === null,
			'icons: an icon-only label renders without invented text' );
		const linkedLabel = iconLabels[ 0 ].querySelector( '.mw-monster-evolution-edge-label-link' );
		assert( linkedLabel !== null && linkedLabel.getAttribute( 'href' ) === '/wiki/Fire_Stone',
			'links: the server-rendered internal anchor is cloned into the visual label' );
		assert( linkedLabel !== null && linkedLabel.querySelector( 'img' ) !== null &&
			linkedLabel.textContent === 'Level',
		'links: the icon and label text are contained by one clickable anchor' );

		const radial = document.querySelector( '[data-case="radial-eevee"]' );
		const radialCanvas = rectangle( radial.querySelector( '.mw-monster-evolution-canvas' ) );
		const radialNodes = Array.from( radial.querySelectorAll( '.mw-monster-evolution-node' ) );
		const radialCenter = rectangle( radialNodes[ 0 ] );
		const centerX = radialCenter.left + radialCenter.width / 2;
		const centerY = radialCenter.top + radialCenter.height / 2;
		assert( Math.abs( centerX - ( radialCanvas.left + radialCanvas.width / 2 ) ) < 1 &&
			Math.abs( centerY - ( radialCanvas.top + radialCanvas.height / 2 ) ) < 1,
		'radial: selected Eevee node occupies the canvas center' );
		const radialDistances = radialNodes.slice( 1 ).map( ( node ) => {
			const bounds = rectangle( node );
			return Math.hypot(
				bounds.left + bounds.width / 2 - centerX,
				bounds.top + bounds.height / 2 - centerY
			);
		} );
		assert( Math.max( ...radialDistances ) - Math.min( ...radialDistances ) < 1,
			'radial: Eevee evolutions occupy one even circle' );
		assert( rectangle( radialNodes[ 1 ] ).top < radialCenter.top,
			'radial: radialStart top places the first evolution above the center' );
		const radialLabels = Array.from( radial.querySelectorAll( '.mw-monster-evolution-edge-label' ) );
		radialLabels.forEach( ( label, index ) => {
			radialNodes.forEach( ( node, nodeIndex ) => {
				assert( !intersects( rectangle( label ), rectangle( node ) ),
					'radial: label ' + index + ' does not cover node ' + nodeIndex );
			} );
		} );

		const rings = document.querySelector( '[data-case="radial-rings"]' );
		const ringNodes = Array.from( rings.querySelectorAll( '.mw-monster-evolution-node' ) );
		const ringCenter = rectangle( ringNodes[ 0 ] );
		const ringCenterX = ringCenter.left + ringCenter.width / 2;
		const ringCenterY = ringCenter.top + ringCenter.height / 2;
		const distanceFromCenter = ( node ) => {
			const bounds = rectangle( node );
			return Math.hypot(
				bounds.left + bounds.width / 2 - ringCenterX,
				bounds.top + bounds.height / 2 - ringCenterY
			);
		};
		assert( distanceFromCenter( ringNodes[ 5 ] ) > distanceFromCenter( ringNodes[ 1 ] ),
			'radial: later evolution generations use successive rings' );
		assert( distanceFromCenter( ringNodes[ 7 ] ) > distanceFromCenter( ringNodes[ 5 ] ),
			'radial: disconnected nodes remain visible on an outer ring' );
		assert( rectangle( ringNodes[ 1 ] ).left > ringCenter.right,
			'radial: radialStart right places the first polygon node to the right' );

		const nonfirst = document.querySelector( '[data-case="radial-nonfirst-center"]' );
		const nonfirstCanvas = rectangle( nonfirst.querySelector( '.mw-monster-evolution-canvas' ) );
		const nonfirstNodes = nonfirst.querySelectorAll( '.mw-monster-evolution-node' );
		const selectedCenter = rectangle( nonfirstNodes[ 1 ] );
		assert(
			Math.abs( selectedCenter.left + selectedCenter.width / 2 -
				( nonfirstCanvas.left + nonfirstCanvas.width / 2 ) ) < 1 &&
			Math.abs( selectedCenter.top + selectedCenter.height / 2 -
				( nonfirstCanvas.top + nonfirstCanvas.height / 2 ) ) < 1,
			'radial: an explicitly selected non-first node occupies the center'
		);

		const cycle = document.querySelector( '[data-case="cycle"]' );
		const loopPaths = Array.from( cycle.querySelectorAll( '.mw-monster-evolution-edge-path' ) ).slice( -2 )
			.map( ( path ) => path.getAttribute( 'd' ) );
		assert( new Set( loopPaths ).size === 2, 'cycle: repeated self-loops use distinct curves' );

		const interactive = document.querySelector( '[data-case="left-to-right"]' );
		const root = interactive.querySelector( '.mw-monster-evolution' );
		const canvas = root.querySelector( '.mw-monster-evolution-canvas' );
		const stage = root.querySelector( '.mw-monster-evolution-stage' );
		const highlight = root.querySelector( '.mw-monster-evolution-highlight' );
		highlight.click();
		assert( highlight.getAttribute( 'aria-pressed' ) === 'true', 'interaction: highlight activates' );
		highlight.click();
		assert( highlight.getAttribute( 'aria-pressed' ) === 'false', 'interaction: highlight toggles off' );
		root.querySelector( '[data-zoom-action="in"]' ).click();
		assert( canvas.style.transform === 'scale(1.15)', 'interaction: zoom in changes scale' );
		assert( Number.parseFloat( stage.style.width ) > Number.parseFloat( canvas.style.width ),
			'interaction: zoom updates the scrollable stage' );
		root.querySelector( '[data-zoom-action="reset"]' ).click();
		assert( canvas.style.transform === 'scale(1)', 'interaction: reset restores scale' );

		const styledRoot = interactive.querySelector( '.mw-monster-evolution' );
		const styledNode = styledRoot.querySelector( '.mw-monster-evolution-node' );
		const styledPath = styledRoot.querySelector( '.mw-monster-evolution-edge-path' );
		assert( getComputedStyle( styledRoot.querySelector( '.mw-monster-evolution-viewport' ) ).backgroundColor ===
			'rgba(0, 0, 0, 0)', 'theme: viewport background is transparent' );
		assert( getComputedStyle( styledNode ).color === 'rgb(255, 255, 255)',
			'theme: plain node text is white' );
		assert( getComputedStyle( styledPath ).stroke === 'rgb(255, 255, 255)',
			'theme: edge is white' );
		assert( getComputedStyle( styledPath ).filter !== 'none',
			'theme: white edge has contrast protection on light pages' );

		const results = document.querySelector( '#results' );
		const list = results.querySelector( 'ul' );
		results.setAttribute( 'data-assertions', String( assertions ) );
		if ( failures.length === 0 ) {
			results.setAttribute( 'data-test-status', 'passed' );
			results.querySelector( 'strong' ).textContent = assertions + ' browser assertions passed.';
		} else {
			results.setAttribute( 'data-test-status', 'failed' );
			results.querySelector( 'strong' ).textContent = failures.length + ' of ' + assertions +
				' browser assertions failed.';
			failures.forEach( ( failure ) => list.appendChild( createElement( 'li', '', failure ) ) );
		}
	};
}() );
