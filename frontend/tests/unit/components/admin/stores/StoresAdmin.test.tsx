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
    admin: {
      stores: '/admin/stores',
      storeMaintenance: (id: number) => `/admin/stores/${id}/maintenance`,
    },
  },
}));

import StoresAdmin from '../../../../../src/components/admin/stores/StoresAdmin';

describe('StoresAdmin', (): void => {
  beforeEach((): void => {
    mockApiFetch.mockReset();
  });

  it('renders loading state initially', (): void => {
    mockApiFetch.mockReturnValue(new Promise(() => {}));
    render(<StoresAdmin locale="es" />);
    expect(screen.getByText('Cargando tiendas...')).toBeTruthy();
  });

  it('lists stores and shows maintenance status', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({
      data: [
        { id: 1, name: 'Panda', slug: 'panda', is_active: true, maintenance_mode: false },
        { id: 2, name: 'Kitsune', slug: 'kitsune', is_active: true, maintenance_mode: true },
      ],
      meta: { current_page: 1, per_page: 20, total: 2 },
    });

    render(<StoresAdmin locale="es" />);

    await waitFor(() => {
      expect(screen.getByTestId('store-row-1')).toBeTruthy();
    });

    expect(screen.getByText('Panda')).toBeTruthy();
    expect(screen.getByText('Kitsune')).toBeTruthy();
    expect(screen.getByTestId('store-status-1').textContent).toContain('Activa');
    expect(screen.getByTestId('store-status-2').textContent).toContain('En mantenimiento');
  });

  it('toggles maintenance on click', async (): Promise<void> => {
    mockApiFetch
      .mockResolvedValueOnce({
        data: [{ id: 1, name: 'Panda', slug: 'panda', is_active: true, maintenance_mode: false }],
        meta: { current_page: 1, per_page: 20, total: 1 },
      })
      .mockResolvedValueOnce({
        data: { id: 1, name: 'Panda', slug: 'panda', is_active: true, maintenance_mode: true },
      });

    const user = userEvent.setup();
    render(<StoresAdmin locale="es" />);

    await waitFor(() => {
      expect(screen.getByTestId('store-toggle-1')).toBeTruthy();
    });

    await user.click(screen.getByTestId('store-toggle-1'));

    await waitFor(() => {
      expect(screen.getByTestId('store-status-1').textContent).toContain('En mantenimiento');
    });

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/admin/stores/1/maintenance',
      expect.objectContaining({ method: 'POST', body: { enabled: true } }),
    );
  });

  it('disables toggle when store is inactive', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({
      data: [{ id: 1, name: 'Panda', slug: 'panda', is_active: false, maintenance_mode: false }],
      meta: { current_page: 1, per_page: 20, total: 1 },
    });

    render(<StoresAdmin locale="es" />);

    await waitFor(() => {
      const toggle = screen.getByTestId('store-toggle-1') as HTMLButtonElement;
      expect(toggle.disabled).toBe(true);
    });
  });

  it('shows empty state when no stores', async (): Promise<void> => {
    mockApiFetch.mockResolvedValueOnce({
      data: [],
      meta: { current_page: 1, per_page: 20, total: 0 },
    });

    render(<StoresAdmin locale="es" />);

    await waitFor(() => {
      expect(screen.getByText('No hay tiendas accesibles.')).toBeTruthy();
    });
  });

  it('shows error state on fetch failure', async (): Promise<void> => {
    mockApiFetch.mockRejectedValueOnce(new Error('network'));

    render(<StoresAdmin locale="es" />);

    await waitFor(() => {
      expect(screen.getByText('No se pudieron cargar las tiendas.')).toBeTruthy();
    });
  });
});
