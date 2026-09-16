// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockApiFetch } = vi.hoisted(() => ({
  mockApiFetch: vi.fn(),
}));

vi.mock('../../../../../src/lib/api', () => ({
  apiFetch: mockApiFetch,
}));

vi.mock('../../../../../src/lib/endpoints', () => ({
  API_ENDPOINTS: {
    admin: {
      customers: '/admin/customers',
      customer: (id: number) => `/admin/customers/${id}`,
      customerSuspend: (id: number) => `/admin/customers/${id}/suspend`,
      customerReactivate: (id: number) => `/admin/customers/${id}/reactivate`,
    },
  },
}));

import CustomersAdmin from '../../../../../src/components/admin/customers/CustomersAdmin';

describe('CustomersAdmin', (): void => {
  beforeEach((): void => {
    mockApiFetch.mockReset();
    window.confirm = vi.fn(() => true);
  });

  it('renders loading state initially', (): void => {
    mockApiFetch.mockReturnValue(new Promise(() => {}));
    render(<CustomersAdmin />);
    expect(screen.getByText('Cargando clientes...')).toBeTruthy();
  });

  it('lists customers in a table with filters', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({
      data: [
        {
          id: 1,
          customer_type: 'retail',
          first_name: 'Ada',
          last_name: 'Lovelace',
          full_name: 'Ada Lovelace',
          email: 'ada@example.com',
          is_active: true,
        },
      ],
      meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
    });

    render(<CustomersAdmin />);

    await waitFor(() => {
      expect(screen.getByTestId('customer-row-1')).toBeTruthy();
    });
    expect(screen.getByText('Ada Lovelace')).toBeTruthy();
    expect(screen.getByText('ada@example.com')).toBeTruthy();
  });

  it('shows empty state when no customers', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({
      data: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    });

    render(<CustomersAdmin />);

    await waitFor(() => {
      expect(screen.getByTestId('customers-empty')).toBeTruthy();
    });
  });

  it('applies filters on form submit', async (): Promise<void> => {
    mockApiFetch.mockResolvedValue({
      data: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    });

    const user = userEvent.setup();
    render(<CustomersAdmin />);

    await waitFor(() => {
      expect(screen.getByTestId('customers-filter-search')).toBeTruthy();
    });

    await user.type(screen.getByTestId('customers-filter-search'), 'panda');
    await user.click(screen.getByTestId('customers-filter-apply'));

    await waitFor(() => {
      const calls = mockApiFetch.mock.calls;
      const lastCall = calls[calls.length - 1]?.[0] as string;
      expect(lastCall).toContain('q=panda');
    });
  });

  it('opens detail modal on view click', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: [
          {
            id: 1,
            customer_type: 'wholesale',
            first_name: 'Ada',
            last_name: 'Lovelace',
            full_name: 'Ada Lovelace',
            email: 'ada@example.com',
            is_active: true,
          },
        ],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      })
      .mockResolvedValueOnce({
        data: {
          id: 1,
          customer_type: 'wholesale',
          first_name: 'Ada',
          last_name: 'Lovelace',
          full_name: 'Ada Lovelace',
          email: 'ada@example.com',
          is_active: true,
          phone: '+505 8888 9999',
          registered_at: '2024-01-15T10:00:00+00:00',
          is_anonymized: false,
          order_count: 5,
          lifetime_value_cents: 150000,
        },
      });

    const user = userEvent.setup();
    render(<CustomersAdmin />);

    await waitFor(() => {
      expect(screen.getByTestId('customer-view-1')).toBeTruthy();
    });
    await user.click(screen.getByTestId('customer-view-1'));

    await waitFor(() => {
      expect(screen.getByTestId('customer-detail-modal')).toBeTruthy();
    });

    expect(screen.getByText('+505 8888 9999')).toBeTruthy();
    expect(screen.getByText('5')).toBeTruthy();
    expect(screen.getByText('1500')).toBeTruthy();
  });

  it('suspends active customer on click', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: [
          {
            id: 1,
            customer_type: 'retail',
            first_name: 'Ada',
            last_name: 'Lovelace',
            full_name: 'Ada Lovelace',
            email: 'ada@example.com',
            is_active: true,
          },
        ],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      })
      .mockResolvedValueOnce({ data: { id: 1, is_active: false } })
      .mockResolvedValueOnce({
        data: [
          {
            id: 1,
            customer_type: 'retail',
            first_name: 'Ada',
            last_name: 'Lovelace',
            full_name: 'Ada Lovelace',
            email: 'ada@example.com',
            is_active: false,
          },
        ],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      });

    const user = userEvent.setup();
    render(<CustomersAdmin />);

    await waitFor(() => {
      expect(screen.getByTestId('customer-suspend-1')).toBeTruthy();
    });
    await user.click(screen.getByTestId('customer-suspend-1'));

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/admin/customers/1/suspend',
      expect.objectContaining({ method: 'POST' }),
    );
  });

  it('reactivates inactive customer on click', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: [
          {
            id: 2,
            customer_type: 'vip',
            first_name: 'Bob',
            last_name: 'Doe',
            full_name: 'Bob Doe',
            email: 'bob@example.com',
            is_active: false,
          },
        ],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      })
      .mockResolvedValueOnce({ data: { id: 2, is_active: true } })
      .mockResolvedValueOnce({
        data: [
          {
            id: 2,
            customer_type: 'vip',
            first_name: 'Bob',
            last_name: 'Doe',
            full_name: 'Bob Doe',
            email: 'bob@example.com',
            is_active: true,
          },
        ],
        meta: { current_page: 1, per_page: 20, total: 1, last_page: 1 },
      });

    const user = userEvent.setup();
    render(<CustomersAdmin />);

    await waitFor(() => {
      expect(screen.getByTestId('customer-reactivate-2')).toBeTruthy();
    });
    await user.click(screen.getByTestId('customer-reactivate-2'));

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/admin/customers/2/reactivate',
      expect.objectContaining({ method: 'POST' }),
    );
  });
});
