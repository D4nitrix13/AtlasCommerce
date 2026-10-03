/**
 * AddressesManager — UI de libro de direcciones del cliente (RF-CUST-041).
 *
 * Lista direcciones del cliente autenticado (GET /api/account/addresses)
 * y permite:
 *  - Crear nueva (modal con form).
 *  - Editar existente (modal con form precargado).
 *  - Marcar como principal (POST /api/account/addresses/{id}/primary).
 *  - Eliminar (DELETE /api/account/addresses/{id}, soft-delete).
 *
 * Aislamiento: el backend garantiza que el cliente sólo ve sus propias
 * direcciones (cross-customer → 404). El frontend no filtra por tenant
 * porque las direcciones pertenecen al Customer (cross-tenant).
 */
import { useEffect, useState, type FormEvent, type ReactElement } from 'react';
import { apiFetch } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations } from '../../../i18n';
import type { TranslationKey } from '../../../i18n/types';

function resolveFormErrorKey(error: string): TranslationKey {
  switch (error) {
    case 'required':
      return 'account.addresses.error.required';
    case 'generic':
      return 'account.addresses.error.generic';
    default:
      return 'account.addresses.error.generic';
  }
}

interface Address {
  id: number;
  address_type: 'shipping' | 'billing' | 'other';
  country: string;
  department: string | null;
  municipality: string | null;
  detailed_address: string;
  reference: string | null;
  is_primary: boolean;
}

interface FormState {
  address_type: Address['address_type'];
  country: string;
  department: string;
  municipality: string;
  detailed_address: string;
  reference: string;
  is_primary: boolean;
}

const EMPTY_FORM: FormState = {
  address_type: 'shipping',
  country: '',
  department: '',
  municipality: '',
  detailed_address: '',
  reference: '',
  is_primary: false,
};

function addressToForm(a: Address): FormState {
  return {
    address_type: a.address_type,
    country: a.country,
    department: a.department ?? '',
    municipality: a.municipality ?? '',
    detailed_address: a.detailed_address,
    reference: a.reference ?? '',
    is_primary: a.is_primary,
  };
}

export default function AddressesManager(): ReactElement {
  const { t } = useTranslations();
  const [addresses, setAddresses] = useState<Address[] | null>(null);
  const [loadError, setLoadError] = useState<boolean>(false);
  const [editing, setEditing] = useState<Address | null>(null);
  const [isCreating, setIsCreating] = useState<boolean>(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState<boolean>(false);
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void (async (): Promise<void> => {
      try {
        const response = await apiFetch<{ data: Address[] }>(
          API_ENDPOINTS.customers.accountAddresses
        );
        if (!cancelled) {
          setAddresses(response.data);
        }
      } catch {
        if (!cancelled) {
          setLoadError(true);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const openCreate = (): void => {
    setForm(EMPTY_FORM);
    setFormError(null);
    setEditing(null);
    setIsCreating(true);
  };

  const openEdit = (address: Address): void => {
    setForm(addressToForm(address));
    setFormError(null);
    setEditing(address);
    setIsCreating(false);
  };

  const closeModal = (): void => {
    setIsCreating(false);
    setEditing(null);
    setFormError(null);
  };

  const refresh = async (): Promise<void> => {
    const response = await apiFetch<{ data: Address[] }>(API_ENDPOINTS.customers.accountAddresses);
    setAddresses(response.data);
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    if (form.country.trim() === '' || form.detailed_address.trim() === '') {
      setFormError('required');
      return;
    }

    setSaving(true);
    setFormError(null);
    try {
      const payload = {
        address_type: form.address_type,
        country: form.country.trim(),
        department: form.department.trim() === '' ? null : form.department.trim(),
        municipality: form.municipality.trim() === '' ? null : form.municipality.trim(),
        detailed_address: form.detailed_address.trim(),
        reference: form.reference.trim() === '' ? null : form.reference.trim(),
        is_primary: form.is_primary,
      };

      if (editing !== null) {
        await apiFetch(API_ENDPOINTS.customers.accountAddress(editing.id), {
          method: 'PUT',
          body: payload,
        });
      } else {
        await apiFetch(API_ENDPOINTS.customers.accountAddresses, {
          method: 'POST',
          body: payload,
        });
      }

      await refresh();
      closeModal();
    } catch {
      setFormError('generic');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (address: Address): Promise<void> => {
    const confirmed = window.confirm(t('account.addresses.confirmDelete'));
    if (!confirmed) return;

    try {
      await apiFetch(API_ENDPOINTS.customers.accountAddress(address.id), {
        method: 'DELETE',
      });
      await refresh();
    } catch {
      setLoadError(true);
    }
  };

  const handleSetPrimary = async (address: Address): Promise<void> => {
    try {
      await apiFetch(API_ENDPOINTS.customers.accountAddressPrimary(address.id), {
        method: 'POST',
      });
      await refresh();
    } catch {
      setLoadError(true);
    }
  };

  if (loadError) {
    return (
      <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        {t('account.addresses.error.load')}
      </div>
    );
  }

  if (addresses === null) {
    return (
      <p className="text-sm text-neutral-500" data-testid="addresses-loading">
        {t('account.addresses.loading')}
      </p>
    );
  }

  const showFormModal = isCreating || editing !== null;

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <button
          type="button"
          onClick={openCreate}
          className="rounded-md bg-[var(--brand-bg-emphasis,#171717)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--brand-bg-emphasis-hover,#262626)]"
          data-testid="addresses-add"
        >
          {t('account.addresses.add')}
        </button>
      </div>

      {addresses.length === 0 ? (
        <p
          className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center text-sm text-neutral-600"
          data-testid="addresses-empty"
        >
          {t('account.addresses.empty')}
        </p>
      ) : (
        <ul className="space-y-3">
          {addresses.map((address) => (
            <li
              key={address.id}
              className="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm"
              data-testid={`address-row-${address.id}`}
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="rounded bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-700">
                      {t(`account.addresses.type.${address.address_type}`)}
                    </span>
                    {address.is_primary && (
                      <span
                        className="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
                        data-testid={`address-primary-${address.id}`}
                      >
                        {t('account.addresses.primaryBadge')}
                      </span>
                    )}
                  </div>
                  <p className="mt-2 text-sm text-neutral-900">{address.detailed_address}</p>
                  <p className="text-xs text-neutral-500">
                    {[address.municipality, address.department, address.country]
                      .filter(Boolean)
                      .join(', ')}
                  </p>
                  {address.reference !== null && address.reference !== '' && (
                    <p className="mt-1 text-xs italic text-neutral-500">{address.reference}</p>
                  )}
                </div>
                <div className="flex flex-col gap-1 text-right">
                  {!address.is_primary && (
                    <button
                      type="button"
                      onClick={() => void handleSetPrimary(address)}
                      className="text-xs font-medium text-sky-700 hover:text-sky-800"
                      data-testid={`address-set-primary-${address.id}`}
                    >
                      {t('account.addresses.setPrimary')}
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => openEdit(address)}
                    className="text-xs font-medium text-neutral-700 hover:text-neutral-900"
                    data-testid={`address-edit-${address.id}`}
                  >
                    {t('account.addresses.edit')}
                  </button>
                  <button
                    type="button"
                    onClick={() => void handleDelete(address)}
                    className="text-xs font-medium text-rose-600 hover:text-rose-700"
                    data-testid={`address-delete-${address.id}`}
                  >
                    {t('account.addresses.delete')}
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      {showFormModal && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          data-testid="address-form-modal"
        >
          <form
            onSubmit={handleSubmit}
            className="w-full max-w-md space-y-3 rounded-lg bg-white p-6 shadow-lg"
          >
            <h2 className="text-lg font-semibold text-neutral-900">
              {editing !== null ? t('account.addresses.edit') : t('account.addresses.add')}
            </h2>

            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('account.addresses.fields.country')}
              </span>
              <select
                value={form.address_type}
                onChange={(event) =>
                  setForm({ ...form, address_type: event.target.value as Address['address_type'] })
                }
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="address-field-type"
              >
                <option value="shipping">{t('account.addresses.type.shipping')}</option>
                <option value="billing">{t('account.addresses.type.billing')}</option>
                <option value="other">{t('account.addresses.type.other')}</option>
              </select>
            </label>

            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('account.addresses.fields.country')}
              </span>
              <input
                type="text"
                value={form.country}
                onChange={(event) => setForm({ ...form, country: event.target.value })}
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="address-field-country"
              />
            </label>

            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('account.addresses.fields.department')}
              </span>
              <input
                type="text"
                value={form.department}
                onChange={(event) => setForm({ ...form, department: event.target.value })}
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="address-field-department"
              />
            </label>

            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('account.addresses.fields.municipality')}
              </span>
              <input
                type="text"
                value={form.municipality}
                onChange={(event) => setForm({ ...form, municipality: event.target.value })}
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="address-field-municipality"
              />
            </label>

            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('account.addresses.fields.detailedAddress')}
              </span>
              <input
                type="text"
                value={form.detailed_address}
                onChange={(event) => setForm({ ...form, detailed_address: event.target.value })}
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="address-field-detailed"
              />
            </label>

            <label className="block">
              <span className="text-xs font-medium text-neutral-700">
                {t('account.addresses.fields.reference')}
              </span>
              <input
                type="text"
                value={form.reference}
                onChange={(event) => setForm({ ...form, reference: event.target.value })}
                className="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                data-testid="address-field-reference"
              />
            </label>

            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={form.is_primary}
                onChange={(event) => setForm({ ...form, is_primary: event.target.checked })}
                data-testid="address-field-primary"
              />
              <span className="text-sm text-neutral-700">
                {t('account.addresses.fields.isPrimary')}
              </span>
            </label>

            {formError !== null && (
              <p role="alert" className="text-sm text-rose-600" data-testid="address-form-error">
                {t(resolveFormErrorKey(formError))}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={closeModal}
                className="rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                data-testid="address-cancel"
              >
                {t('account.addresses.actions.cancel')}
              </button>
              <button
                type="submit"
                disabled={saving}
                className="rounded-md bg-[var(--brand-bg-emphasis,#171717)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--brand-bg-emphasis-hover,#262626)] disabled:bg-[var(--brand-bg-emphasis-disabled,#a3a3a3)]"
                data-testid="address-save"
              >
                {saving
                  ? t('account.addresses.actions.saving')
                  : t('account.addresses.actions.save')}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
