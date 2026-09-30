import type { Meta, StoryObj } from '@storybook/astro';
import Pagination from './Pagination.astro';

/**
 * Pagination — primitive UI link-based.
 *
 * Genera links a cada página con query string `?page=N`. Página actual
 * con aria-current="page"; links prev/next con `rel="prev"/"next"`.
 */
const meta: Meta<typeof Pagination> = {
  title: 'UI/Pagination',
  component: Pagination,
  tags: ['autodocs'],
  argTypes: {
    currentPage: { control: { type: 'number', min: 1, max: 50 } },
    lastPage: { control: { type: 'number', min: 1, max: 50 } },
    baseUrl: { control: 'text' },
    pageParam: { control: 'text' },
    pageLabel: { control: 'text' },
    ofLabel: { control: 'text' },
    previousLabel: { control: 'text' },
    nextLabel: { control: 'text' },
    ariaLabel: { control: 'text' },
  },
  args: {
    currentPage: 3,
    lastPage: 10,
    baseUrl: '/products',
    pageLabel: 'Página',
    ofLabel: 'de',
    previousLabel: 'Anterior',
    nextLabel: 'Siguiente',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const FirstPage: Story = {
  args: { currentPage: 1, lastPage: 10 },
};

export const LastPage: Story = {
  args: { currentPage: 10, lastPage: 10 },
};

export const MiddlePage: Story = {
  args: { currentPage: 5, lastPage: 10 },
};

export const SmallPagination: Story = {
  args: { currentPage: 1, lastPage: 3 },
};

export const SinglePage: Story = {
  args: { currentPage: 1, lastPage: 1 },
};

export const EnglishLabels: Story = {
  args: {
    pageLabel: 'Page',
    ofLabel: 'of',
    previousLabel: 'Previous',
    nextLabel: 'Next',
    currentPage: 2,
    lastPage: 5,
  },
};

export const CustomQueryParam: Story = {
  args: {
    baseUrl: '/search',
    pageParam: 'p',
    currentPage: 2,
    lastPage: 8,
  },
};