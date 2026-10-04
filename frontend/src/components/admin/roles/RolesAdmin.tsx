/**
 * RolesAdmin — Panel admin de roles y permisos (RF-AUTH-008).
 *
 * Lista roles del tenant con conteo de permisos y usuarios asignados.
 * Click en una card abre la matriz de permisos (drawer) con checks por
 * permiso, agrupados por módulo.
 *
 * Aislamiento: el backend evalúa permisos por rol vía
 * `CheckPermission` middleware. El frontend sólo renderiza.
 *
 * Por ahora consume `adminRoles`/`allPermissions` del mock local
 * (`roles-mock.ts`). Cuando el endpoint esté operativo, se intercambia
 * por `useEffect` con `apiFetch` (mismo approach que `CustomersAdmin`).
 */
import { useMemo, useState, type ReactElement } from 'react';
import {
  adminRoles,
  allPermissions,
  PERMISSION_MODULE_ORDER,
  type AdminRole,
  type Permission,
  type PermissionModule,
  type RoleStatus,
} from '../../../data/roles-mock';
import { useTranslations } from '../../../i18n';

const STATUS_BADGE: Record<RoleStatus, string> = {
  active: 'bg-emerald-100 text-emerald-700',
  inactive: 'bg-slate-100 text-slate-500',
};

interface DrawerState {
  role: AdminRole;
  /** Set de slugs tildados en el formulario (estado de edición). */
  draft: Set<string>;
  /** Set de slugs en el backend (referencia para detectar cambios). */
  original: Set<string>;
}

const EMPTY_SEARCH = '';

export default function RolesAdmin(): ReactElement {
  const { t } = useTranslations();

  const [roles, setRoles] = useState<readonly AdminRole[]>(adminRoles);
  const [permissions] = useState<readonly Permission[]>(allPermissions);
  const [drawer, setDrawer] = useState<DrawerState | null>(null);
  const [search, setSearch] = useState<string>(EMPTY_SEARCH);
  const [saving, setSaving] = useState<boolean>(false);

  const moduleGroups = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return PERMISSION_MODULE_ORDER.map((moduleId) => ({
      moduleId,
      items: permissions.filter((permission) => {
        if (permission.module !== moduleId) return false;
        if (needle === '') return true;
        const haystack = `${permission.name} ${permission.slug}`.toLowerCase();
        return haystack.includes(needle);
      }),
    })).filter((group) => group.items.length > 0);
  }, [permissions, search]);

  const handleOpenDrawer = (role: AdminRole): void => {
    setDrawer({
      role,
      draft: new Set(role.permissions),
      original: new Set(role.permissions),
    });
    setSearch(EMPTY_SEARCH);
  };

  const handleCloseDrawer = (): void => {
    if (drawer === null) return;
    if (hasUnsavedChanges(drawer)) {
      if (!window.confirm(t('admin.roles.detail.unsavedChanges'))) return;
    }
    setDrawer(null);
  };

  const handleTogglePermission = (slug: string): void => {
    if (drawer === null) return;
    const next = new Set(drawer.draft);
    if (next.has(slug)) {
      next.delete(slug);
    } else {
      next.add(slug);
    }
    setDrawer({ ...drawer, draft: next });
  };

  const handleToggleModule = (moduleId: PermissionModule): void => {
    if (drawer === null) return;
    const moduleSlugs = permissions.filter((p) => p.module === moduleId).map((p) => p.slug);
    const allSelected = moduleSlugs.every((slug) => drawer.draft.has(slug));
    const next = new Set(drawer.draft);
    for (const slug of moduleSlugs) {
      if (allSelected) {
        next.delete(slug);
      } else {
        next.add(slug);
      }
    }
    setDrawer({ ...drawer, draft: next });
  };

  const handleSave = async (): Promise<void> => {
    if (drawer === null) return;
    setSaving(true);
    const nextPermissions = Array.from(drawer.draft);
    setRoles((prev) =>
      prev.map((role) =>
        role.id === drawer.role.id ? { ...role, permissions: nextPermissions } : role
      )
    );
    setDrawer({ ...drawer, original: new Set(drawer.draft) });
    setSaving(false);
  };

  const hasUnsavedChanges = (state: DrawerState): boolean => {
    if (state.draft.size !== state.original.size) return true;
    for (const slug of state.draft) {
      if (!state.original.has(slug)) return true;
    }
    return false;
  };

  const moduleLabel = (moduleId: PermissionModule): string => {
    return t(`admin.roles.detail.module.${moduleId}`);
  };

  const hasRoles = roles.length > 0;

  return (
    <div className="space-y-4">
      {!hasRoles ? (
        <p
          className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center text-sm text-neutral-600"
          data-testid="roles-empty"
        >
          {t('admin.roles.empty')}
        </p>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {roles.map((role) => (
            <article
              key={role.id}
              className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
              data-testid={`role-card-${role.id}`}
            >
              <header className="mb-3 flex items-start justify-between gap-2">
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold text-slate-900">{role.name}</h3>
                    {role.is_protected && (
                      <span
                        className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700"
                        data-testid={`role-protected-${role.id}`}
                      >
                        {t('admin.roles.protected.badge')}
                      </span>
                    )}
                  </div>
                  <p className="mt-0.5 font-mono text-xs text-slate-500">{role.slug}</p>
                </div>
                <span
                  className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_BADGE[role.status]}`}
                >
                  {t(
                    role.status === 'active'
                      ? 'admin.roles.status.active'
                      : 'admin.roles.status.inactive'
                  )}
                </span>
              </header>

              <p className="text-sm text-slate-600">{role.description}</p>

              <dl className="mt-4 grid grid-cols-2 gap-3 text-xs">
                <div>
                  <dt className="text-slate-500">{t('admin.roles.column.permissions')}</dt>
                  <dd className="mt-0.5 text-sm font-semibold text-slate-900">
                    {t('admin.roles.permissionsCount').replace(
                      '{count}',
                      String(role.permissions.length)
                    )}
                  </dd>
                </div>
                <div>
                  <dt className="text-slate-500">{t('admin.roles.column.users')}</dt>
                  <dd className="mt-0.5 text-sm font-semibold text-slate-900">
                    {t('admin.roles.usersCount').replace('{count}', String(role.users_count))}
                  </dd>
                </div>
              </dl>

              <div className="mt-4 flex items-center justify-end">
                <button
                  type="button"
                  onClick={() => handleOpenDrawer(role)}
                  className="text-xs font-medium text-sky-700 hover:text-sky-800"
                  data-testid={`role-edit-${role.id}`}
                >
                  {t('admin.roles.actions.edit')} →
                </button>
              </div>
            </article>
          ))}
        </div>
      )}

      {drawer !== null && (
        <div
          className="fixed inset-0 z-50 flex items-stretch justify-end bg-black/40"
          data-testid="role-drawer"
        >
          <div className="flex w-full max-w-2xl flex-col bg-white shadow-2xl">
            <header className="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
              <div>
                <h2 className="text-lg font-semibold text-slate-900">
                  {t('admin.roles.detail.heading')}
                </h2>
                <p className="mt-0.5 text-sm text-slate-500">{drawer.role.name}</p>
              </div>
              <button
                type="button"
                onClick={handleCloseDrawer}
                aria-label={t('admin.roles.detail.close')}
                className="rounded p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
                data-testid="role-drawer-close"
              >
                ✕
              </button>
            </header>

            <div className="border-b border-slate-200 px-6 py-3">
              <p className="text-xs text-slate-500">{t('admin.roles.detail.permissionsHelp')}</p>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={t('admin.roles.detail.permissionsSearch')}
                className="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                data-testid="role-drawer-search"
              />
            </div>

            <div className="flex-1 overflow-y-auto px-6 py-4">
              {moduleGroups.map(({ moduleId, items }) => {
                const moduleSlugs = items.map((item) => item.slug);
                const allSelected =
                  moduleSlugs.length > 0 && moduleSlugs.every((slug) => drawer.draft.has(slug));
                return (
                  <section key={moduleId} className="mb-5 last:mb-0">
                    <header className="mb-2 flex items-center justify-between">
                      <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                        {moduleLabel(moduleId)}
                      </h3>
                      <button
                        type="button"
                        onClick={() => handleToggleModule(moduleId)}
                        className="text-[11px] font-medium text-sky-700 hover:text-sky-800"
                        data-testid={`role-module-toggle-${moduleId}`}
                      >
                        {allSelected ? '—' : '+'}
                      </button>
                    </header>
                    <ul className="space-y-1.5">
                      {items.map((permission) => {
                        const isChecked = drawer.draft.has(permission.slug);
                        return (
                          <li key={permission.id}>
                            <label
                              htmlFor={`role-permission-${permission.slug}`}
                              className="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50"
                            >
                              <input
                                id={`role-permission-${permission.slug}`}
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => handleTogglePermission(permission.slug)}
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                data-testid={`role-permission-${permission.slug}`}
                              />
                              <span className="flex-1">
                                <span className="block font-medium text-slate-900">
                                  {permission.name}
                                </span>
                                <span className="block font-mono text-[11px] text-slate-500">
                                  {permission.slug}
                                </span>
                              </span>
                            </label>
                          </li>
                        );
                      })}
                    </ul>
                  </section>
                );
              })}
            </div>

            <footer className="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/50 px-6 py-3">
              <span className="text-xs text-slate-500">
                {drawer.draft.size} / {permissions.length}
              </span>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={handleCloseDrawer}
                  className="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                  data-testid="role-drawer-cancel"
                >
                  {t('admin.roles.detail.cancel')}
                </button>
                <button
                  type="button"
                  onClick={() => void handleSave()}
                  disabled={saving || !hasUnsavedChanges(drawer)}
                  className="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:bg-slate-400"
                  data-testid="role-drawer-save"
                >
                  {saving ? t('admin.roles.detail.saving') : t('admin.roles.detail.saveChanges')}
                </button>
              </div>
            </footer>
          </div>
        </div>
      )}
    </div>
  );
}
