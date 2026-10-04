/**
 * UsersAdmin — Panel admin de usuarios internos (RF-AUTH-009).
 *
 * Lista usuarios del tenant activo con:
 *  - Filtros: búsqueda libre, role_id, status.
 *  - Modal "Nuevo usuario" (formulario básico + select de rol).
 *  - Modal "Detalle" con datos clave + acciones suspender/reactivar.
 *  - Acciones inline por fila (suspend / reactivate).
 *
 * Aislamiento por tenant: el backend filtra via `user_stores`
 * (`accessibleStoreIds`). El frontend NO aplica su propio filtro.
 *
 * Por ahora consume `adminUsers`/`roleOptions` del mock local
 * (`users-mock.ts`). Cuando el endpoint esté operativo, se intercambia
 * por un `useEffect` con `apiFetch` — la firma del componente no se
 * modifica (siguiendo el approach de `CustomersAdmin.tsx`).
 */
import { useMemo, useState, type ReactElement } from 'react';
import {
  adminUsers,
  roleOptions,
  type AdminUser,
  type UserStatus,
  type RoleOption,
} from '../../../data/users-mock';
import { useTranslations } from '../../../i18n';

const STATUS_BADGE: Record<UserStatus, string> = {
  active: 'bg-emerald-100 text-emerald-700',
  suspended: 'bg-rose-100 text-rose-700',
};

const AVATAR_BG: Record<string, string> = {
  sky: 'bg-sky-100 text-sky-700',
  amber: 'bg-amber-100 text-amber-700',
  emerald: 'bg-emerald-100 text-emerald-700',
  rose: 'bg-rose-100 text-rose-700',
  violet: 'bg-violet-100 text-violet-700',
  slate: 'bg-slate-200 text-slate-700',
};

interface Filters {
  search: string;
  roleId: number | 'all';
  status: '' | UserStatus;
}

const EMPTY_FILTERS: Filters = { search: '', roleId: 'all', status: '' };

interface CreateForm {
  first_name: string;
  last_name: string;
  email: string;
  role_id: number;
}

const EMPTY_CREATE: CreateForm = {
  first_name: '',
  last_name: '',
  email: '',
  role_id: roleOptions[0]?.id ?? 2,
};

const RELATIVE_THRESHOLDS = {
  today: 24 * 60 * 60 * 1000,
  yesterday: 48 * 60 * 60 * 1000,
} as const;

export default function UsersAdmin(): ReactElement {
  const { t } = useTranslations();

  // Estado derivado del mock. Cuando el endpoint esté listo, esto
  // pasa a `useState<AdminUser[] | null>(null)` + fetch en `useEffect`.
  const [users, setUsers] = useState<readonly AdminUser[]>(adminUsers);
  const [roles] = useState<readonly RoleOption[]>(roleOptions);
  const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS);
  const [applied, setApplied] = useState<Filters>(EMPTY_FILTERS);
  const [showCreate, setShowCreate] = useState<boolean>(false);
  const [showDetail, setShowDetail] = useState<AdminUser | null>(null);
  const [createForm, setCreateForm] = useState<CreateForm>(EMPTY_CREATE);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const filteredUsers = useMemo(() => {
    const needle = applied.search.trim().toLowerCase();
    return users.filter((user) => {
      if (needle !== '') {
        const haystack = `${user.full_name} ${user.email}`.toLowerCase();
        if (!haystack.includes(needle)) return false;
      }
      if (applied.roleId !== 'all' && user.role_id !== applied.roleId) return false;
      if (applied.status !== '' && user.status !== applied.status) return false;
      return true;
    });
  }, [users, applied]);

  const handleApplyFilters = (event: React.FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    setApplied(filters);
  };

  const handleClearFilters = (): void => {
    setFilters(EMPTY_FILTERS);
    setApplied(EMPTY_FILTERS);
  };

  const handleSuspend = (user: AdminUser): void => {
    if (!window.confirm(t('admin.users.confirmSuspend'))) return;
    setPendingId(user.id);
    setUsers((prev) =>
      prev.map((u) => (u.id === user.id ? { ...u, status: 'suspended' as const } : u))
    );
    setPendingId(null);
  };

  const handleReactivate = (user: AdminUser): void => {
    if (!window.confirm(t('admin.users.confirmReactivate'))) return;
    setPendingId(user.id);
    setUsers((prev) =>
      prev.map((u) => (u.id === user.id ? { ...u, status: 'active' as const } : u))
    );
    setPendingId(null);
  };

  const handleOpenCreate = (): void => {
    setCreateForm(EMPTY_CREATE);
    setShowCreate(true);
  };

  const handleCloseCreate = (): void => {
    setShowCreate(false);
  };

  const handleSubmitCreate = (event: React.FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    if (
      createForm.first_name.trim() === '' ||
      createForm.last_name.trim() === '' ||
      createForm.email.trim() === ''
    ) {
      return;
    }
    const selectedRole = roles.find((r) => r.id === createForm.role_id);
    const newUser: AdminUser = {
      id: Math.max(...users.map((u) => u.id)) + 1,
      first_name: createForm.first_name.trim(),
      last_name: createForm.last_name.trim(),
      full_name: `${createForm.first_name.trim()} ${createForm.last_name.trim()}`,
      email: createForm.email.trim(),
      avatar_color: 'sky',
      role_id: createForm.role_id,
      role_name: selectedRole?.name ?? '—',
      role_slug: selectedRole?.slug ?? '',
      status: 'active',
      last_login_at: null,
      created_at: new Date().toISOString(),
    };
    setUsers([newUser, ...users]);
    setShowCreate(false);
  };

  const getInitials = (fullName: string): string => {
    const parts = fullName.split(' ').filter((part) => part !== '');
    if (parts.length === 0) return '?';
    const first = parts[0]?.[0] ?? '';
    const second = parts[1]?.[0] ?? '';
    return `${first}${second}`.toUpperCase();
  };

  const formatLastLogin = (iso: string | null): string => {
    if (iso === null) return t('admin.users.lastLogin.never');
    const diff = Date.now() - new Date(iso).getTime();
    if (diff < RELATIVE_THRESHOLDS.today) return t('admin.users.lastLogin.today');
    if (diff < RELATIVE_THRESHOLDS.yesterday) return t('admin.users.lastLogin.yesterday');
    const days = Math.floor(diff / (24 * 60 * 60 * 1000));
    return t('admin.users.lastLogin.daysAgo').replace('{days}', String(days));
  };

  const hasUsers = users.length > 0;
  const hasResults = filteredUsers.length > 0;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm text-neutral-500" data-testid="users-count">
          {filteredUsers.length} de {users.length}
        </p>
        <button
          type="button"
          onClick={handleOpenCreate}
          className="inline-flex items-center gap-1.5 rounded-lg bg-slate-950 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500"
          data-testid="users-create-button"
        >
          <span aria-hidden="true">+</span>
          <span>{t('admin.users.create.submit')}</span>
        </button>
      </div>

      <form
        onSubmit={handleApplyFilters}
        className="grid gap-2 rounded-lg border border-neutral-200 bg-white p-3 sm:grid-cols-4"
      >
        <input
          type="search"
          value={filters.search}
          onChange={(event) => setFilters({ ...filters, search: event.target.value })}
          placeholder={t('admin.users.filters.searchPlaceholder')}
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
          data-testid="users-filter-search"
        />
        <select
          value={filters.roleId === 'all' ? 'all' : String(filters.roleId)}
          onChange={(event) => {
            const value = event.target.value;
            setFilters({
              ...filters,
              roleId: value === 'all' ? 'all' : Number(value),
            });
          }}
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
          data-testid="users-filter-role"
        >
          <option value="all">{t('admin.users.filters.allRoles')}</option>
          {roles.map((role) => (
            <option key={role.id} value={String(role.id)}>
              {role.name}
            </option>
          ))}
        </select>
        <select
          value={filters.status}
          onChange={(event) =>
            setFilters({ ...filters, status: event.target.value as Filters['status'] })
          }
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
          data-testid="users-filter-status"
        >
          <option value="">{t('admin.users.filters.allStatuses')}</option>
          <option value="active">{t('admin.users.status.active')}</option>
          <option value="suspended">{t('admin.users.status.suspended')}</option>
        </select>
        <div className="flex gap-2">
          <button
            type="submit"
            className="flex-1 rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
            data-testid="users-filter-apply"
          >
            {t('admin.users.filters.search')}
          </button>
          <button
            type="button"
            onClick={handleClearFilters}
            className="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
            data-testid="users-filter-clear"
          >
            ×
          </button>
        </div>
      </form>

      {!hasUsers ? (
        <p
          className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center text-sm text-neutral-600"
          data-testid="users-empty"
        >
          {t('admin.users.empty')}
        </p>
      ) : !hasResults ? (
        <p
          className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center text-sm text-neutral-600"
          data-testid="users-empty-filters"
        >
          {t('admin.users.empty')}
        </p>
      ) : (
        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
              <tr>
                <th className="px-3 py-2">{t('admin.users.column.user')}</th>
                <th className="px-3 py-2">{t('admin.users.column.email')}</th>
                <th className="px-3 py-2">{t('admin.users.column.role')}</th>
                <th className="px-3 py-2">{t('admin.users.column.lastLogin')}</th>
                <th className="px-3 py-2">{t('admin.users.column.status')}</th>
                <th className="px-3 py-2 text-right">{t('admin.users.column.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {filteredUsers.map((user) => {
                const avatarClass = AVATAR_BG[user.avatar_color] ?? AVATAR_BG.slate;
                return (
                  <tr
                    key={user.id}
                    className="border-t border-neutral-100"
                    data-testid={`user-row-${user.id}`}
                  >
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-3">
                        <span
                          aria-hidden="true"
                          className={`grid h-9 w-9 place-items-center rounded-full text-xs font-semibold ${avatarClass}`}
                        >
                          {getInitials(user.full_name)}
                        </span>
                        <button
                          type="button"
                          onClick={() => setShowDetail(user)}
                          className="text-left text-sm font-medium text-neutral-900 hover:text-sky-700 hover:underline"
                          data-testid={`user-name-${user.id}`}
                        >
                          {user.full_name}
                        </button>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-neutral-700">{user.email}</td>
                    <td className="px-3 py-2 text-neutral-700">{user.role_name}</td>
                    <td className="px-3 py-2 text-xs text-neutral-500">
                      {formatLastLogin(user.last_login_at)}
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`rounded px-2 py-0.5 text-xs font-medium ${STATUS_BADGE[user.status]}`}
                      >
                        {t(
                          user.status === 'active'
                            ? 'admin.users.status.active'
                            : 'admin.users.status.suspended'
                        )}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-right">
                      {user.status === 'active' ? (
                        <button
                          type="button"
                          onClick={() => handleSuspend(user)}
                          disabled={pendingId === user.id}
                          className="text-xs font-medium text-rose-600 hover:text-rose-700 disabled:text-neutral-400"
                          data-testid={`user-suspend-${user.id}`}
                        >
                          {t('admin.users.actions.suspend')}
                        </button>
                      ) : (
                        <button
                          type="button"
                          onClick={() => handleReactivate(user)}
                          disabled={pendingId === user.id}
                          className="text-xs font-medium text-emerald-700 hover:text-emerald-800 disabled:text-neutral-400"
                          data-testid={`user-reactivate-${user.id}`}
                        >
                          {t('admin.users.actions.reactivate')}
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {showDetail !== null && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          data-testid="user-detail-modal"
        >
          <div className="w-full max-w-md space-y-3 rounded-lg bg-white p-6 shadow-lg">
            <h2 className="text-lg font-semibold text-neutral-900">{showDetail.full_name}</h2>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between">
                <dt className="text-neutral-500">{t('admin.users.detail.email')}</dt>
                <dd className="text-neutral-900">{showDetail.email}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-neutral-500">{t('admin.users.detail.role')}</dt>
                <dd className="text-neutral-900">{showDetail.role_name}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-neutral-500">{t('admin.users.detail.createdAt')}</dt>
                <dd className="text-neutral-900">
                  {new Date(showDetail.created_at).toLocaleDateString()}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-neutral-500">{t('admin.users.detail.lastLogin')}</dt>
                <dd className="text-neutral-900">{formatLastLogin(showDetail.last_login_at)}</dd>
              </div>
            </dl>
            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={() => setShowDetail(null)}
                className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                data-testid="user-detail-close"
              >
                {t('admin.users.close')}
              </button>
            </div>
          </div>
        </div>
      )}

      {showCreate && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          data-testid="user-create-modal"
        >
          <form
            onSubmit={handleSubmitCreate}
            className="w-full max-w-md space-y-3 rounded-lg bg-white p-6 shadow-lg"
          >
            <div>
              <h2 className="text-lg font-semibold text-neutral-900">
                {t('admin.users.create.title')}
              </h2>
              <p className="mt-1 text-sm text-neutral-500">{t('admin.users.create.subtitle')}</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block">
                <span className="text-xs font-medium text-neutral-700">
                  {t('admin.users.create.firstName')}
                </span>
                <input
                  type="text"
                  required
                  value={createForm.first_name}
                  onChange={(event) =>
                    setCreateForm({ ...createForm, first_name: event.target.value })
                  }
                  className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                  data-testid="user-create-first-name"
                />
              </label>
              <label className="block">
                <span className="text-xs font-medium text-neutral-700">
                  {t('admin.users.create.lastName')}
                </span>
                <input
                  type="text"
                  required
                  value={createForm.last_name}
                  onChange={(event) =>
                    setCreateForm({ ...createForm, last_name: event.target.value })
                  }
                  className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                  data-testid="user-create-last-name"
                />
              </label>
            </div>
            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('admin.users.create.email')}
              </span>
              <input
                type="email"
                required
                value={createForm.email}
                onChange={(event) => setCreateForm({ ...createForm, email: event.target.value })}
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="user-create-email"
              />
            </label>
            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('admin.users.create.role')}
              </span>
              <select
                value={String(createForm.role_id)}
                onChange={(event) =>
                  setCreateForm({ ...createForm, role_id: Number(event.target.value) })
                }
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="user-create-role"
              >
                {roles.map((role) => (
                  <option key={role.id} value={String(role.id)}>
                    {role.name}
                  </option>
                ))}
              </select>
            </label>
            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={handleCloseCreate}
                className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                data-testid="user-create-cancel"
              >
                {t('admin.users.create.cancel')}
              </button>
              <button
                type="submit"
                className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                data-testid="user-create-submit"
              >
                {t('admin.users.create.submit')}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
