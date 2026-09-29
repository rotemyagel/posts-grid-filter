/**
 * The @wordpress/scripts ESLint config, plus the Playwright rules for the
 * browser tests in tests/e2e.
 */
module.exports = {
	extends: [ require.resolve( '@wordpress/scripts/config/.eslintrc.js' ) ],
	overrides: [
		{
			files: [ 'tests/e2e/**/*.js' ],
			extends: [ 'plugin:@wordpress/eslint-plugin/test-playwright' ],
		},
	],
};
