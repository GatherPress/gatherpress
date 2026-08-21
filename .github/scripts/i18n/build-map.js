/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

// The project webpack config spreads the default config's entries, and the
// default only exports the two-config array when the modules flag is set
// (the same flag `npm run build` passes). Setting it here lets this script
// require the real config instead of duplicating its entry list.
process.env.WP_EXPERIMENTAL_MODULES = '1';

/**
 * WordPress dependencies
 */
const projectConfig = require( path.resolve( process.cwd(), 'webpack.config.js' ) );

/**
 * Generates the src-to-build file map used by `wp i18n make-json --use-map`.
 *
 * The translation POT is extracted from `src/` so its references stay readable,
 * but WordPress loads translations for `build/` script paths at runtime. This
 * script bridges the two by deriving the mapping from the same webpack entry
 * points the build uses, then following static imports to find every bundled
 * source file, so it never has to be maintained by hand.
 *
 * Output goes to stdout as a JSON object keyed by source path. Values are
 * arrays because webpack bundles shared modules into every entry that imports
 * them, and `wp i18n make-json` emits one Jed JSON per build path:
 * { "src/blocks/rsvp/edit.js": [ "build/blocks/rsvp/index.js" ] }.
 *
 * @since 0.36.0
 */

/**
 * Collects the entry points of the classic webpack config.
 *
 * Mirrors how `npm run build` resolves entries: the project's webpack.config.js
 * spreads the default `script` config's entries and adds its own.
 *
 * @since 0.36.0
 *
 * @return {Object<string, string>} Entry names mapped to absolute source paths.
 */
function getEntries() {
	const configs = Array.isArray( projectConfig )
		? projectConfig
		: [ projectConfig ];

	const scriptConfig =
		configs.find(
			( config ) => ! config.experiments?.outputModule
		) ?? configs[ 0 ];

	return typeof scriptConfig.entry === 'function'
		? scriptConfig.entry()
		: scriptConfig.entry;
}

/**
 * Collects PHP render files copied verbatim from src to build.
 *
 * The block build copies `render.php` next to each block's build output without
 * touching the code inside, so a translation reference in `src/.../render.php`
 * resolves to the identical file under `build/`.
 *
 * @since 0.36.0
 *
 * @return {string[]} Source paths of render files that exist in both trees.
 */
function getRenderFiles() {
	const srcBlocks = path.resolve( process.cwd(), 'src', 'blocks' );
	const buildBlocks = path.resolve( process.cwd(), 'build', 'blocks' );

	if ( ! fs.existsSync( srcBlocks ) || ! fs.existsSync( buildBlocks ) ) {
		return [];
	}

	return fs
		.readdirSync( srcBlocks )
		.flatMap( ( block ) => {
			const srcFile = path.join( srcBlocks, block, 'render.php' );
			const buildFile = path.join( buildBlocks, block, 'render.php' );

			if ( ! fs.existsSync( srcFile ) || ! fs.existsSync( buildFile ) ) {
				return [];
			}

			return [ srcFile ];
		} );
}

/**
 * Resolves a static import specifier against the importing file.
 *
 * Handles the shapes this codebase uses: extension-less relative paths,
 * explicit .js extensions, and directory imports resolving to index.js.
 * Package imports and stylesheets return null; they contain no plugin
 * translatable strings worth mapping.
 *
 * @param {string} specifier Raw import path as written in the source.
 * @param {string} importer  Absolute path of the file doing the importing.
 * @since 0.36.0
 *
 * @return {string|null} Absolute resolved path, or null when unresolvable.
 */
function resolveImport( specifier, importer ) {
	if ( ! specifier.startsWith( '.' ) && ! specifier.startsWith( '/' ) ) {
		return null;
	}

	const base = path.resolve( path.dirname( importer ), specifier );
	const candidates = [
		base,
		`${ base }.js`,
		path.join( base, 'index.js' ),
	];

	return candidates.find( ( candidate ) => fs.existsSync( candidate ) && fs.statSync( candidate ).isFile() ) ?? null;
}

/**
 * Extracts relative import specifiers from a module's source text.
 *
 * Matches static imports and re-exports (`import x from '...'`,
 * `import '...'`, `export { y } from '...'`). Dynamic `import()` calls are out
 * of scope: they become separate async chunks under --experimental-modules,
 * which carry no statically extractable strings.
 *
 * @param {string} filePath Path of the module to scan.
 * @since 0.36.0
 *
 * @return {string[]} Import specifiers found in the file.
 */
function getImportSpecifiers( filePath ) {
	const contents = fs.readFileSync( filePath, 'utf8' );
	const pattern = /(?:import|export)[^;'"]*?from\s*['"]([^'"]+)['"]|import\s*['"]([^'"]+)['"]/g;
	const specifiers = [];

	let match = pattern.exec( contents );

	while ( match ) {
		specifiers.push( match[ 1 ] || match[ 2 ] );
		match = pattern.exec( contents );
	}

	return specifiers;
}

/**
 * Walks an entry's static import graph and returns every reachable src file.
 *
 * @param {string} entryPath Absolute path of the entry module.
 * @since 0.36.0
 *
 * @return {Set<string>} Absolute paths of all modules reachable via static imports, including the entry itself.
 */
function collectBundledModules( entryPath ) {
	const visited = new Set();
	const queue = [ entryPath ];

	while ( queue.length > 0 ) {
		const current = queue.pop();

		if ( visited.has( current ) ) {
			continue;
		}
		visited.add( current );

		for ( const specifier of getImportSpecifiers( current ) ) {
			const resolved = resolveImport( specifier, current );

			// Stylesheets resolve but carry no JavaScript strings, and the
			// extractor never references them, so the walk stops there.
			if ( resolved && resolved.endsWith( '.js' ) ) {
				queue.push( resolved );
			}
		}
	}

	return visited;
}

/**
 * Converts an absolute path to a repo-relative POSIX path.
 *
 * @param {string} absolutePath Absolute filesystem path.
 * @since 0.36.0
 *
 * @return {string} Path relative to the repo root with forward slashes.
 */
function toRelative( absolutePath ) {
	return path.relative( process.cwd(), absolutePath ).split( path.sep ).join( '/' );
}

/**
 * Lists JavaScript files under a directory.
 *
 * @param {string} dir Directory to scan recursively.
 * @since 0.36.0
 *
 * @return {string[]} Absolute paths of .js files.
 */
function listJsFiles( dir ) {
	const files = [];

	for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
		const fullPath = path.join( dir, entry.name );

		if ( entry.isDirectory() ) {
			files.push( ...listJsFiles( fullPath ) );
		} else if ( entry.isFile() && entry.name.endsWith( '.js' ) ) {
			files.push( fullPath );
		}
	}

	return files;
}

/**
 * Lists repo-relative script paths emitted by a previous webpack run.
 *
 * @param {string} dir Build directory to scan recursively.
 * @since 0.36.0
 *
 * @return {Set<string>} Paths like "build/panels.js"; empty when no build exists yet.
 */
function listBuildScripts( dir ) {
	if ( ! fs.existsSync( dir ) ) {
		return new Set();
	}

	const scripts = [];

	for ( const file of listJsFiles( dir ) ) {
		const relativePath = toRelative( file );

		// Asset manifests and module chunks aside, only plain .js outputs
		// correspond one to one with entry names.
		if ( relativePath.endsWith( '.js' ) && ! relativePath.endsWith( '.module.js' ) ) {
			scripts.push( relativePath );
		}
	}

	return new Set( scripts );
}

const entries = getEntries();
const map = {};

// Every module a JS entry pulls in ends up inside that entry's build output,
// so each maps to `build/<name>.js`. Shared components appear under every
// entry that imports them; make-json accepts the array and writes one JSON per
// build path.
//
// Some declared entries emit no JavaScript at all: .scss entries produce only
// CSS, and stylesheet-only anchors like the Leaflet one lose their empty JS
// bundle to RemoveEmptyScriptsPlugin. When a previous build output exists, its
// emitted scripts are the source of truth for which targets are real, so keys
// pointing at nothing get dropped rather than producing dangling JSON names.
const emittedScripts = new Set(
	listBuildScripts( path.resolve( process.cwd(), 'build' ) )
);

for ( const [ name, srcPath ] of Object.entries( entries ) ) {
	if ( ! srcPath.endsWith( '.js' ) ) {
		continue;
	}

	const buildPath = `build/${ name }.js`;

	if ( emittedScripts.size > 0 && ! emittedScripts.has( buildPath ) ) {
		continue;
	}

	for ( const modulePath of collectBundledModules( srcPath ) ) {
		const key = toRelative( modulePath );

		map[ key ] = map[ key ] ?? [];
		map[ key ].push( buildPath );
	}
}

// render.php files are copied unchanged, so src and build paths mirror each other.
for ( const srcFile of getRenderFiles() ) {
	map[ toRelative( srcFile ) ] = [
		toRelative( srcFile ).replace( /^src\//, 'build/' ),
	];
}

process.stdout.write( JSON.stringify( map, null, 2 ) + '\n' );
