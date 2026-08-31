# MonsterEvolution usage guide

This guide is for wiki editors who create charts and administrators who customize the extension. For installation, development, and security architecture, see [README.md](README.md).

## 1. Create your first chart

Place an `<evolution>` tag on a wiki page:

```wiki
<evolution>
Small Slime -> Big Slime -> King Slime
</evolution>
```

This shorthand creates one linked card for every distinct name and one arrow for every `->`. Shorthand is useful for a simple linear chart. It does not guess images.

For branches, images, custom links, conditions, or linked label icons, declare nodes explicitly:

```wiki
<evolution direction="left-to-right" theme="cards">
[node id="slime" name="Slime" image="Slime.png" link="Slime"]
[node id="fire" name="Fire Slime" image="Fire Slime.png" link="Fire Slime"]
[node id="water" name="Water Slime" image="Water Slime.png" link="Water Slime"]

slime -> fire [label="Fire Stone" icon="Fire Stone.png" link="Fire Stone"]
slime -> water [label="Water Stone" icon="Water Stone.png" iconPosition="above"]
</evolution>
```

The `id` is an internal chart identifier. Once any `[node]` declaration exists, every arrow endpoint must use a declared ID. This catches spelling mistakes instead of silently creating an unwanted node.

Node links and arrow-label links that point to nonexistent pages remain normal
MediaWiki red links. By default, the page containing the chart is also placed in
`Category:Pages with missing Monster Evolution links`, allowing editors to find
and repair those targets. Administrators can disable this maintenance category with:

```php
$wgMonsterEvolutionEnableMissingPageTrackingCategory = false;
```

## 2. How a chart is structured

A chart has three layers:

1. The `<evolution>` tag controls the whole graph.
2. `[node ...]` declarations describe the cards.
3. Arrow statements describe directed relationships between cards.

Coordinates are never entered manually. The browser assigns layers or radial rings and draws SVG arrows. Branches, merges, multiple roots, disconnected groups, cycles, reversible changes, and self-loops are all valid because the data model is a directed graph rather than a tree.

Blank lines are ignored. Attribute names are case-insensitive. Attribute values must be quoted with matching single or double quotes.

## 3. Graph options

Options are written on the opening tag:

```wiki
<evolution direction="bottom-to-top" theme="compact" zoom="true" controls="true">
...
</evolution>
```

| Option | Accepted values | Default | Notes |
| --- | --- | --- | --- |
| `layout` | `layered`, `radial` | `layered` | Chooses the layout algorithm. Radial layout requires `center`. |
| `direction` | `left-to-right`, `right-to-left`, `top-to-bottom`, `bottom-to-top` | `left-to-right` | `horizontal` and `vertical` are aliases for left-to-right and top-to-bottom. |
| `center` | Declared node ID | Empty | Required for radial layout and rejected for layered layout. |
| `radialShape` | `circle`, `polygon` | `circle` | Circle staggers alternate rings; polygon keeps common angular spokes. |
| `radialStart` | `top`, `right`, `bottom`, `left` | `top` | Position of the first source-order node on every radial ring. |
| `theme` | `default`, `compact`, `cards`, `minimal` | `default` | Themes change card density and decoration, not graph meaning. |
| `imageWidth` | Whole number from 16–512 | `96` | Default thumbnail width in pixels. |
| `imageHeight` | Whole number from 16–512 | `96` | Default thumbnail height in pixels. |
| `zoom` | `true`, `false`, `yes`, `no`, `1`, `0` | `false` | Enables client zoom behavior. |
| `controls` | Same Boolean values | `false` | Shows zoom buttons only when `zoom` is also enabled and the administrator allows zoom. |

### Radial layout

Radial layout is useful when one creature has many alternatives, such as Eevee:

```wiki
<evolution
 layout="radial"
 center="eevee"
 radialShape="circle"
 radialStart="top"
 theme="cards"
 zoom="true"
 controls="true"
>
[node id="eevee" name="Eevee"]
[node id="vaporeon" name="Vaporeon"]
[node id="jolteon" name="Jolteon"]
[node id="flareon" name="Flareon"]

eevee -> vaporeon [label="Water Stone"]
eevee -> jolteon [label="Thunder Stone"]
eevee -> flareon [label="Fire Stone"]
</evolution>
```

The selected node occupies the center. Directly connected nodes use the first
ring, later generations use successive rings, and disconnected nodes remain
visible on an outer ring. Nodes retain source order clockwise from
`radialStart`. `direction` remains accepted for compatibility but does not
control radial positioning.

Circle mode offsets alternate rings to reduce straight radial corridors.
Polygon mode keeps rings aligned to common angular spokes. For a single ring,
both modes create the regular polygon implied by its node count: six nodes form
a hexagon and eight nodes form an octagon.

## 4. Node attributes

```wiki
[node
 id="ember-cub"
 name="Ember Cub"
 image="Ember Cub.png"
 link="Bestiary/Ember Cub"
 subtitle="Fire type"
 form="Young form"
 tooltip="Found near the western volcano."
 class="starter fire"
 imageWidth="112"
 imageHeight="112"
]
```

| Attribute | Required | Meaning |
| --- | --- | --- |
| `id` | Yes | ASCII identifier containing letters, numbers, `_`, or `-`. IDs are case-sensitive. |
| `name` | Yes | Visible Unicode name. HTML-looking text is displayed as text, never executed. |
| `image` | No | Local MediaWiki filename without `File:`. Paths and external URLs are rejected. |
| `link` | No | Internal wiki title. External and executable URLs are rejected. |
| `subtitle` | No | Secondary visible text. |
| `form` | No | Form, variant, or stage description. |
| `tooltip` | No | Extra information shown through a keyboard-accessible `<details>` element. |
| `class` | No | Up to eight safe semantic tokens for administrator CSS. |
| `imageWidth`, `imageHeight` | No | Per-node thumbnail override from 16–512 pixels. |

If a requested node image is missing, the card remains in the graph and shows an accessible placeholder. Uploading or replacing the file later allows MediaWiki’s normal cache dependency handling to update the page.

## 5. Arrows, labels, links, conditions, and icons

The basic arrow is `source -> target`:

```wiki
young -> adult [type="level" label="Level 20"]
```

| Attribute | Default | Meaning |
| --- | --- | --- |
| `type` | `custom` | Safe semantic token used in a namespaced CSS class. Examples: `level`, `item`, `fusion`, `trade`, `quest`, or `temporary`. |
| `label` | Empty | Concise visible explanation placed near the arrow. |
| `link` | Empty | Internal wiki title opened by the label and icon. External URLs are rejected. |
| `conditions` | Empty | Semicolon- or newline-separated requirements retained in the accessible relationship list. |
| `icon` | Empty | Local MediaWiki file shown in the floating arrow label. Do not include `File:`. |
| `iconPosition` | `next-to` | `next-to` places the icon before the text; `above` stacks it over the text. |

### Icon beside the label

```wiki
eevee -> flareon [
 type="item"
 label="Fire Stone"
 icon="Fire Stone icon.png"
 iconPosition="next-to"
 link="Fire Stone"
]
```

The complete floating label—both icon and text—is one internal link. Use an
ordinary MediaWiki title, such as `Fire Stone`, `Item:Fire Stone`, or
`Items/Fire Stone`; underscores are also accepted. Do not use `[[...]]`, an
external URL, or the `File:` prefix. If a linked icon-only label references a
missing file, the wiki title becomes visible so the link never turns into an
empty, unclickable label.

### Icon above the label

```wiki
eevee -> vaporeon [
 type="item"
 label="Water Stone"
 icon="Water Stone icon.png"
 iconPosition="above"
]
```

### Icon without label text

```wiki
sealed -> awakened [icon="Awakening emblem.svg"]
```

Icons are rendered as 24 × 24 pixel thumbnails. Only local MediaWiki files are accepted. A missing or failed icon is omitted while the arrow and textual relationship remain. Internal links are resolved by MediaWiki and remain available in the non-JavaScript relationship list. The client clones MediaWiki’s server-rendered anchor and thumbnail into the visual label; it does not build image or link URLs from wikitext.

### Structured conditions

```wiki
base -> night-form [
 type="condition"
 label="Night evolution"
 conditions="Friendship >= 80; Time = Night; Not holding Sun Charm"
]
```

Use a concise `label` for the chart and put detailed rules in `conditions`. This keeps dense graphs readable while retaining the full requirements for assistive technology and the non-JavaScript fallback.

## 6. Common graph patterns

### Chain

```wiki
A -> B -> C [type="level" label="Level up"]
```

Attributes on a chain apply to every generated arrow.

### Branch

```wiki
base -> fire
base -> water
base -> earth
```

### Merge or fusion

```wiki
parent-a -> fusion [type="fusion" label="Fusion"]
parent-b -> fusion [type="fusion" label="Fusion"]
```

### Reversible form change

```wiki
normal <-> powered [type="form" label="Change form"]
```

`<->` creates two directed arrows. If no custom type is supplied, the reverse arrow receives the `reversible` type.

### Cycle and self-loop

```wiki
A -> B
B -> C
C -> A [label="Rebirth"]
C -> C [type="temporary" label="Refresh form"]
```

### Multiple disconnected lines

```wiki
A -> B
C -> D
[node id="standalone" name="No known evolution"]
```

## 7. Templates and parser-function form

The tag expands templates and parser functions once before parsing:

```wiki
<evolution>
[node id="base" name="{{Creature name|Base}}" link="Creature:Base"]
[node id="adult" name="{{Creature name|Adult}}" link="Creature:Adult"]
base -> adult [label="{{Evolution requirement|20}}"]
</evolution>
```

Template-friendly parser-function form:

```wiki
{{#evolution:
A -> B -> C
|direction=top-to-bottom
|theme=compact
|zoom=true
|controls=true
}}
```

Parser-function options must use `key=value`. Normal MediaWiki expansion and recursion limits still apply. Expanded text remains untrusted chart syntax; it never becomes pre-approved HTML.

## 8. Styling for a wiki or skin

The outer graph exposes CSS custom properties:

```css
.mw-monster-evolution {
    --monster-evolution-card: rgba( 16, 22, 30, 0.9 );
    --monster-evolution-color: #fff;
    --monster-evolution-muted: rgba( 255, 255, 255, 0.78 );
    --monster-evolution-border: rgba( 255, 255, 255, 0.42 );
    --monster-evolution-accent: #6ea8ff;
    --monster-evolution-edge: #fff;
}
```

Place overrides in `MediaWiki:Common.css` or a skin stylesheet. User classes become prefixed selectors such as `.mw-monster-evolution-node--custom-starter`, preventing arbitrary class injection.

Arrow types become selectors such as `.mw-monster-evolution-edge-path--fusion`. Keep overrides below `.mw-monster-evolution` so they do not affect unrelated wiki content.

## 9. Accessibility and no-JavaScript behavior

- Cards, links, image alternatives, labels, conditions, and relationships are rendered by the server.
- Without JavaScript, cards appear in source order and relationships remain a visible list.
- With JavaScript, SVG arrows are decorative and hidden from assistive technology; the semantic relationship list remains available.
- Path highlighting works with pointer, touch, Enter, and Space.
- The graph viewport is keyboard-focusable and scrolls internally when the chart is wider than the page.
- Reduced-motion preferences disable transitions, and print styles restore a simple card-and-list layout.

## 10. Troubleshooting

### Cards appear but arrows do not

Check that the `ext.monsterEvolution` ResourceLoader module loaded without a browser error. The textual relationship list is the deliberate fallback when JavaScript cannot enhance the graph.

### “Unknown node”

At least one explicit node declaration exists, so every arrow endpoint must match a declared `id` exactly. Names and IDs are different fields.

### An icon or image is missing

Confirm the file exists locally or in a configured shared MediaWiki repository. Use only the filename, for example `Fire Stone.png`, not `File:Fire Stone.png`, a filesystem path, or a URL.

### A graph is very wide or tall

Choose a direction that fits the data, try `theme="compact"`, or enable zoom controls. Wide graphs scroll inside their own viewport and should not widen the whole page.

### The parser reports a source line

The line refers to the expanded chart definition. Check matching quotes and brackets, `key="value"` syntax, valid IDs, and declared endpoints near that line.

## 11. Complete examples

The [demo](demo) directory includes generic and franchise-themed examples for Pokémon, Digimon, LumenTale, Coromon, Anode Heart, and Temtem. They contain no bundled game artwork; replace sample local filenames with files your wiki is legally permitted to host.
