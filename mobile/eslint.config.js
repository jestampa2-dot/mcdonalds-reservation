// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require('eslint-config-expo/flat');

module.exports = defineConfig([
  {
    ignores: ['dist/**', 'dist-*/**'],
  },
  expoConfig,
  {
    rules: {
      // Remote booking options intentionally revalidate dependent form state.
      'react-hooks/set-state-in-effect': 'off',
    },
  },
]);
