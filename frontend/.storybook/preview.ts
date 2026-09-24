import type { Preview } from '@storybook/astro';
import '../src/styles/global.css';

/**
 * Storybook global decorators + parameters.
 *
 * Cargamos global.css antes de cualquier story para que Tailwind +
 * tokens del tema estén disponibles en el iframe.
 */
const preview: Preview = {
  parameters: {
    backgrounds: {
      default: 'light',
      values: [
        { name: 'light', value: '#ffffff' },
        { name: 'dark', value: '#1f2937' },
        { name: 'gray', value: '#f3f4f6' },
      ],
    },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    a11y: {
      // Reglas WCAG 2.2 AA. El addon-a11y reporta violations automáticamente.
      config: {},
      options: {
        rules: {
          // Deshabilitar reglas que no aplican al entorno Storybook aislado
          'color-contrast': { enabled: true },
          'landmark-one-main': { enabled: false },
        },
      },
    },
  },
};

export default preview;
