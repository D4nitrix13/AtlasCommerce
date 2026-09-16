// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockApiFetch } = vi.hoisted(() => ({
  mockApiFetch: vi.fn(),
}));

vi.mock('../../../../../src/lib/api', () => ({
  apiFetch: mockApiFetch,
  ApiError: class ApiError extends Error {
    status: number;
    constructor(status: number) {
      super(`HTTP ${status}`);
      this.status = status;
    }
  },
}));

vi.mock('../../../../../src/lib/endpoints', () => ({
  API_ENDPOINTS: {
    customers: {
      accountAddresses: '/account/addresses',
      accountAddress: (id: number) => `/account/addresses/${id}`,
      accountAddressPrimary: (id: number) => `/account/addresses/${id}/primary`,
    },
  },
}));

import AddressesManager from '../../../../../src/components/account/addresses/AddressesManager';

describe('AddressesManager', (): void => {
  beforeEach((): void => {
    mockApiFetch.mockReset();
    window.confirm = vi.fn(() => true);
  });

  it('renders loading state initially', (): void => {
    mockApiFetch.mockReturnValue(new Promise(() => {}));
    render(<AddressesManager />);
    expect(screen.getByText('Cargando direcciones...')).toBeTruthy();
  });

  it('renders empty state when no addresses', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({ data: [] });

    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('addresses-empty')).toBeTruthy();
    });
  });

  it('lists addresses with primary badge', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({
      data: [
        {
          id: 1,
          address_type: 'shipping',
          country: 'NI',
          department: 'Managua',
          municipality: 'Managua',
          detailed_address: 'Calle 123',
          reference: null,
          is_primary: true,
        },
        {
          id: 2,
          address_type: 'billing',
          country: 'NI',
          department: 'León',
          municipality: 'León',
          detailed_address: 'Av 456',
          reference: null,
          is_primary: false,
        },
      ],
    });

    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('address-row-1')).toBeTruthy();
    });

    expect(screen.getByTestId('address-primary-1')).toBeTruthy();
    expect(screen.getByTestId('address-set-primary-2')).toBeTruthy();
    expect(screen.queryByTestId('address-set-primary-1')).toBeNull();
  });

  it('opens form modal when clicking add', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({ data: [] });
    const user = userEvent.setup();
    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('addresses-add')).toBeTruthy();
    });

    await user.click(screen.getByTestId('addresses-add'));

    await waitFor(() => {
      expect(screen.getByTestId('address-form-modal')).toBeTruthy();
    });
  });

  it('shows error when submitting without required fields', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({ data: [] });
    const user = userEvent.setup();
    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('addresses-add')).toBeTruthy();
    });
    await user.click(screen.getByTestId('addresses-add'));

    await waitFor(() => {
      expect(screen.getByTestId('address-field-country')).toBeTruthy();
    });
    await user.click(screen.getByTestId('address-save'));

    await waitFor(() => {
      expect(screen.getByTestId('address-form-error')).toBeTruthy();
    });
    expect(mockApiFetch).not.toHaveBeenCalledWith(
      '/account/addresses',
      expect.objectContaining({ method: 'POST' }),
    );
  });

  it('creates new address on submit', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({ data: [] })
      .mockResolvedValueOnce({
        data: {
          id: 99,
          address_type: 'shipping',
          country: 'NI',
          department: 'Managua',
          municipality: 'Managua',
          detailed_address: 'Calle nueva',
          reference: null,
          is_primary: false,
        },
      })
      .mockResolvedValueOnce({
        data: [
          {
            id: 99,
            address_type: 'shipping',
            country: 'NI',
            department: 'Managua',
            municipality: 'Managua',
            detailed_address: 'Calle nueva',
            reference: null,
            is_primary: false,
          },
        ],
      });

    const user = userEvent.setup();
    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('addresses-add')).toBeTruthy();
    });
    await user.click(screen.getByTestId('addresses-add'));

    await waitFor(() => {
      expect(screen.getByTestId('address-field-country')).toBeTruthy();
    });
    await user.type(screen.getByTestId('address-field-country'), 'NI');
    await user.type(screen.getByTestId('address-field-detailed'), 'Calle nueva');
    await user.click(screen.getByTestId('address-save'));

    await waitFor(() => {
      expect(screen.getByTestId('address-row-99')).toBeTruthy();
    });

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/account/addresses',
      expect.objectContaining({
        method: 'POST',
        body: expect.objectContaining({
          country: 'NI',
          detailed_address: 'Calle nueva',
        }),
      }),
    );
  });

  it('sets primary address via dedicated endpoint', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: [
          {
            id: 1,
            address_type: 'shipping',
            country: 'NI',
            department: null,
            municipality: null,
            detailed_address: 'Calle A',
            reference: null,
            is_primary: true,
          },
          {
            id: 2,
            address_type: 'shipping',
            country: 'NI',
            department: null,
            municipality: null,
            detailed_address: 'Calle B',
            reference: null,
            is_primary: false,
          },
        ],
      })
      .mockResolvedValueOnce({ data: { id: 2, is_primary: true } })
      .mockResolvedValueOnce({
        data: [
          {
            id: 1,
            address_type: 'shipping',
            country: 'NI',
            department: null,
            municipality: null,
            detailed_address: 'Calle A',
            reference: null,
            is_primary: false,
          },
          {
            id: 2,
            address_type: 'shipping',
            country: 'NI',
            department: null,
            municipality: null,
            detailed_address: 'Calle B',
            reference: null,
            is_primary: true,
          },
        ],
      });

    const user = userEvent.setup();
    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('address-set-primary-2')).toBeTruthy();
    });
    await user.click(screen.getByTestId('address-set-primary-2'));

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/account/addresses/2/primary',
      expect.objectContaining({ method: 'POST' }),
    );
  });

  it('deletes address on confirm', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: [
          {
            id: 1,
            address_type: 'shipping',
            country: 'NI',
            department: null,
            municipality: null,
            detailed_address: 'Calle A',
            reference: null,
            is_primary: true,
          },
        ],
      })
      .mockResolvedValueOnce({ data: [] });

    const user = userEvent.setup();
    render(<AddressesManager />);

    await waitFor(() => {
      expect(screen.getByTestId('address-delete-1')).toBeTruthy();
    });
    await user.click(screen.getByTestId('address-delete-1'));

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/account/addresses/1',
      expect.objectContaining({ method: 'DELETE' }),
    );
  });
});
