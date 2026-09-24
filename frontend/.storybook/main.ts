import type { StorybookConfig } from '@storybook-astro/framework';

const config: StorybookConfig = {
  stories: [
    '../src/**/*.stories.@(ts|tsx|js|jsx|astro|mdx)',
    '../src/**/*.mdx',
  ],
  addons: [
    '@storybook/addon-links',
    '@storybook/addon-essentials',
    '@storybook/addon-a11y',
    '@storybook/addon-interactions',
  ],
  framework: {
    name: '@storybook-astro/framework',
    options: {},
  },
  docs: {},
};

export default config;
