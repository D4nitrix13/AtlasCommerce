// @ts-check
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import astro from 'eslint-plugin-astro';
import reactPlugin from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import prettier from 'eslint-config-prettier';
import globals from 'globals';

export default [
  {
    ignores: ['dist/**', '.astro/**', 'node_modules/**', 'public/**'],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...tseslint.configs.stylistic,
  reactPlugin.configs.flat.recommended,
  reactHooks.configs['recommended-latest'],
  // jsx-a11y se aplica SOLO a archivos .jsx/.tsx en el override de
  // abajo. Aplicarlo globalmente rompe con archivos .astro por un
  // bug del plugin (issue upstream: el parser de Astro no es
  // compatible con jsx-ast-utils).
  prettier,
  {
    languageOptions: {
      ecmaVersion: 2024,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        astro: 'readonly',
      },
    },
    settings: {
      react: { version: 'detect' },
    },
    rules: {
      // Tipado estricto
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' },
      ],
      '@typescript-eslint/explicit-function-return-type': 'off',
      '@typescript-eslint/consistent-type-imports': [
        'error',
        { prefer: 'type-imports', fixStyle: 'inline-type-imports' },
      ],

      // General
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-debugger': 'error',
      'no-magic-numbers': 'off',
      'prefer-const': 'error',
      'no-var': 'error',
      'eqeqeq': ['error', 'always', { null: 'ignore' }],

      // Tamaño de archivos (regla frontend §1)
      'max-lines-per-function': ['warn', { max: 40, skipComments: true, skipBlankLines: true }],
      'max-depth': ['error', 3],
      'complexity': ['warn', 10],
      'max-params': ['warn', 5],

      // React
      'react/prop-types': 'off',
      'react/react-in-jsx-scope': 'off',
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',

      // Accesibilidad (regla frontend §17). Las reglas del plugin
      // jsx-a11y se aplican via el override de abajo para que no
      // rompan en archivos .astro.
    },
  },
  ...astro.configs.recommended,
  {
    files: ['**/*.astro'],
    rules: {
      'no-unused-vars': 'off',
    },
  },
  {
    // jsx-a11y SOLO aplica a .jsx/.tsx. Aplicarlo en .astro causa
    // `The prop must be a JSXAttribute collected by the AST parser`
    // porque el parser de Astro no es compatible con jsx-ast-utils.
    // Workaround hasta que el plugin agregue soporte oficial.
    files: ['**/*.jsx', '**/*.tsx'],
    ...jsxA11y.flatConfigs.recommended,
    rules: {
      // Accesibilidad (regla frontend §17). Sobreescribimos las
      // defaults del plugin para mantener las reglas que el repo
      // declara como 'error' o 'warn' en su config original.
      'jsx-a11y/label-has-associated-control': 'error',
      'jsx-a11y/no-static-element-interactions': 'warn',
      'jsx-a11y/click-events-have-key-events': 'warn',
      'jsx-a11y/alt-text': 'error',
      'jsx-a11y/anchor-has-content': 'error',
    },
  },
  {
    files: ['**/*.test.ts', '**/*.test.tsx', '**/*.spec.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
      'no-console': 'off',
    },
  },
];
