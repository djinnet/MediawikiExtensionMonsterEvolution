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
		const item = createElement( 'li', 'mw-monster-evolution-edge', edge.summary || edge.label || 'Edge' );
		item.setAttribute( 'data-edge-index', String( index ) );
		if ( edge.source !== null ) {
			item.setAttribute( 'data-source', String( edge.source ) );
		}
		if ( edge.target !== null ) {
			item.setAttribute( 'data-target', String( edge.target ) );
		}
		item.setAttribute( 'data-edge-type', edge.type || 'custom' );
		item.setAttribute( 'data-edge-label', edge.label || '' );
		list.appendChild( item );
	}

	function buildGraph( definition ) {
		const section = createElement( 'section', 'fixture-case' );
		section.setAttribute( 'data-case', definition.id );
		section.appendChild( createElement( 'h2', '', definition.title ) );

		const root = createElement(
			'div',
			'mw-monster-evolution mw-monster-evolution--default mw-monster-evolution--' + definition.direction
		);
		root.setAttribute( 'data-direction', definition.direction );
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
			{ source: 0, target: 1, label: 'Level' },
			{ source: 0, target: 1, label: 'Item' },
			{ source: 0, target: 1, label: 'Quest' }
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
			const root = section.querySelector( '.mw-monster-evolution' );
			const canvas = root.querySelector( '.mw-monster-evolution-canvas' );
			const canvasBounds = rectangle( canvas );
			const nodes = Array.from( root.querySelectorAll( '.mw-monster-evolution-node' ) );
			const paths = Array.from( root.querySelectorAll( '.mw-monster-evolution-edge-path' ) );
			const labels = Array.from( root.querySelectorAll( '.mw-monster-evolution-edge-label' ) );
			assert( root.classList.contains( 'mw-monster-evolution--enhanced' ), id + ': enhancement ran' );
			assert( canvasBounds.width >= 320 && canvasBounds.height >= 180, id + ': bounded canvas exists' );
			paths.forEach( ( path, index ) => {
				assert( !/(?:NaN|undefined|Infinity)/.test( path.getAttribute( 'd' ) || '' ),
					id + ': path ' + index + ' contains finite coordinates' );
			} );
			nodes.forEach( ( node, index ) => {
				const bounds = rectangle( node );
				assert( bounds.left >= canvasBounds.left - 1 && bounds.right <= canvasBounds.right + 1 &&
					bounds.top >= canvasBounds.top - 1 && bounds.bottom <= canvasBounds.bottom + 1,
					id + ': node ' + index + ' stays inside the canvas' );
				for ( let other = index + 1; other < nodes.length; other++ ) {
					assert( !intersects( bounds, rectangle( nodes[ other ] ) ),
						id + ': nodes ' + index + ' and ' + other + ' do not overlap' );
				}
			} );
			labels.forEach( ( label, index ) => {
				const bounds = rectangle( label );
				assert( bounds.left >= canvasBounds.left - 1 && bounds.right <= canvasBounds.right + 1 &&
					bounds.top >= canvasBounds.top - 1 && bounds.bottom <= canvasBounds.bottom + 1,
					id + ': label ' + index + ' stays inside the canvas' );
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
			const difference = rectangle( nodes[ 1 ] )[ check[ 1 ] ] - rectangle( nodes[ 0 ] )[ check[ 1 ] ];
			assert( Math.sign( difference ) === check[ 2 ], check[ 0 ] + ': source and target follow direction' );
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
