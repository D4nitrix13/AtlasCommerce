// @ts-check
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import astro from 'eslint-plugin-astro';
import reactPlugin from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import prettier from 'eslint-config-prettier';
import globals from 'globals';

/**
 * ESLint flat config — modular por file type.
 *
 * Por qué modular:
 * - Las reglas de `react` y `jsx-a11y` llaman helpers de `jsx-ast-utils`
 *   que esperan `JSXAttribute` (formato de React JSX). El parser de
 *   Astro emite otro shape, entonces esas reglas CRASHEAN sobre .astro
 *   en vez de warn/error. Modularizar evita apagar reglas individuales
 *   (frágil) y deja la config auto-explicativa.
 *
 * Trade-off conocido: los .astro files NO corren reglas de a11y/React.
 * Mitigación: los componentes interactivos viven en .tsx (donde sí
 * corren las reglas). Los .astro son estáticos por convención del
 * proyecto (ver AGENTS.md §16).
 *
 * Estructura:
 * 1. Base global (TS, general, size rules)
 * 2. tsx-only (react + hooks + jsx-a11y)
 * 3. astro-only (astro plugin + overrides)
 * 4. test files (overrides para tests)
 */
export default [
  // ── Ignores ─────────────────────────────────────────────────────────────
  {
    ignores: ['dist/**', '.astro/**', 'node_modules/**', 'public/**'],
  },

  // ── Base global ────────────────────────────────────────────────────────
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...tseslint.configs.stylistic,
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
    },
  },

  // ── tsx-only: react + hooks + a11y ──────────────────────────────────────
  // Registramos los plugins explícitamente porque las configs pre-built
  // (`*.configs.recommended`) están diseñadas para aplicarse en el root,
  // no en bloques con `files`.
  {
    files: ['**/*.tsx'],
    plugins: {
      react: reactPlugin,
      'react-hooks': reactHooks,
      'jsx-a11y': jsxA11y,
    },
    settings: {
      react: { version: 'detect' },
    },
    rules: {
      // Reglas mínimas para validar a11y/interacción en .tsx.
      // Para más reglas, agregar acá — NO se aplican a .astro.
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
      'jsx-a11y/label-has-associated-control': 'error',
      'jsx-a11y/alt-text': 'error',
      'jsx-a11y/anchor-has-content': 'error',
      'jsx-a11y/no-static-element-interactions': 'warn',
      'jsx-a11y/click-events-have-key-events': 'warn',
    },
  },

  // ── astro-only: astro plugin (sin react/a11y) ──────────────────────────
  ...astro.configs.recommended,
  {
    files: ['**/*.astro'],
    rules: {
      'no-unused-vars': 'off',
    },
  },

  // ── Test files: relaja reglas que no aplican en test ───────────────────
  {
    files: ['**/*.test.ts', '**/*.test.tsx', '**/*.spec.ts'],
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
      'no-console': 'off',
    },
  },
];
