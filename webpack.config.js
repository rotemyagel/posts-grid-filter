/**
 * The configuration from wp-scripts, plus the shared design tokens
 * (src/tokens.css), so they're minified like the blocks' stylesheets. They
 * are registered once in PHP as the "wmpgf-tokens" style, which each
 * block.json lists, rather than imported by each block.
 */
const [
	scriptConfig,
	moduleConfig,
] = require( '@wordpress/scripts/config/webpack.config' );

const TOKENS = 'tokens';

/**
 * A stylesheet-only entry still makes webpack write a script and its
 * asset file. The tokens have no script, so both are dropped, and so is
 * the right-to-left copy: they use no left or right, so it would be the
 * same file.
 */
class DropStylesheetOnlyScript {
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'DropStylesheetOnlyScript',
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'DropStylesheetOnlyScript',
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_REPORT,
					},
					() => {
						for ( const name of [
							`${ TOKENS }.js`,
							`${ TOKENS }.asset.php`,
							`${ TOKENS }-rtl.css`,
						] ) {
							if ( compilation.getAsset( name ) ) {
								compilation.deleteAsset( name );
							}
						}
					}
				);
			}
		);
	}
}

/**
 * The font stays where it is, in assets/fonts/ with its licence, and the
 * built CSS points at it there: no hashed copy in build/.
 *
 * @param {Object} rule A module rule.
 * @return {Object} The rule.
 */
const fontInPlace = ( rule ) =>
	String( rule.test ).includes( 'woff2' )
		? {
				...rule,
				generator: {
					filename: '../assets/fonts/[name][ext]',
					emit: false,
				},
		  }
		: rule;

module.exports = [
	{
		...scriptConfig,
		entry: async () => ( {
			...( await scriptConfig.entry() ),
			[ TOKENS ]: './src/tokens.css',
		} ),
		module: {
			...scriptConfig.module,
			rules: scriptConfig.module.rules.map( fontInPlace ),
		},
		plugins: [ ...scriptConfig.plugins, new DropStylesheetOnlyScript() ],
	},
	moduleConfig,
];
