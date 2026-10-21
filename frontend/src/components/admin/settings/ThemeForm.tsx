import { useState, type ReactElement } from 'react';
import type { Theme } from '../../../lib/theme';

/**
 * ThemeForm — Editor de theme del tenant admin.
 *
 * Form controlado que permite editar el theme activo del store:
 * identity (short_name, logos), palette (colors), contact (email, phone),
 * tokens (CSS custom properties adicionales).
 *
 * **State machine** (discriminated union):
 *   - `idle`     → form listo para submit
 *   - `saving`   → request PATCH en curso (deshabilitar submit)
 *   - `success`  → guardado OK, mensaje verde + reset a idle tras 2s
 *   - `error`    → mensaje rojo, mantener form intacto para retry
 *
 * **Endpoint**: PATCH /api/v1/admin/theme con body Theme.
 * **Errors 4xx/5xx** se manejan via `error` state con mensaje del backend.
 *
 * @prop {Theme} initialTheme Estado actual del theme (del backend via GET /admin/theme)
 */
interface Props {
  initialTheme: Theme;
}

type Status =
  | { type: 'idle' }
  | { type: 'saving' }
  | { type: 'success'; message: string }
  | { type: 'error'; message: string };

export default function ThemeForm({ initialTheme }: Props): ReactElement {
  const [theme, setTheme] = useState<Theme>(initialTheme);
  const [status, setStatus] = useState<Status>({ type: 'idle' });

  const update = (field: keyof Theme, value: string): void => {
    setTheme((prev) => ({ ...prev, [field]: value || null }));
  };

  const updateToken = (key: string, value: string): void => {
    setTheme((prev) => ({
      ...prev,
      tokens: { ...(prev.tokens ?? {}), [key]: value },
    }));
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    setStatus({ type: 'saving' });

    try {
      const data: Record<string, string | null> = {};
      for (const [key, value] of Object.entries(theme)) {
        if (key === 'tokens') continue;
        data[key] = (value as string | null) ?? '';
      }

      const response = await fetch('/api/admin/theme', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        const body = (await response.json().catch(() => ({}))) as {
          message?: string;
        };
        setStatus({
          type: 'error',
          message: body.message ?? `Error ${response.status}`,
        });
        return;
      }

      const body = (await response.json()) as { data: Theme };
      setTheme(body.data);
      setStatus({ type: 'success', message: 'Theme guardado.' });
    } catch (error) {
      setStatus({
        type: 'error',
        message: error instanceof Error ? error.message : 'Error desconocido',
      });
    }
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          label="Nombre corto"
          value={theme.short_name ?? ''}
          onChange={(v) => update('short_name', v)}
          placeholder="Ej: Panda"
        />
        <Field
          label="Email de soporte"
          type="email"
          value={theme.support_email ?? ''}
          onChange={(v) => update('support_email', v)}
          placeholder="support@tu-tienda.com"
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field
          label="Logo (URL)"
          value={theme.logo_path ?? ''}
          onChange={(v) => update('logo_path', v)}
          placeholder="/api/v1/storefront/assets/tenants/panda-estampados/branding/logo.webp"
        />
        <Field
          label="Logo de login (URL)"
          value={theme.login_logo_path ?? ''}
          onChange={(v) => update('login_logo_path', v)}
          placeholder="/api/v1/storefront/assets/tenants/panda-estampados/branding/login-logo.webp"
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <ColorField
          label="Color principal"
          value={theme.primary_color ?? ''}
          onChange={(v) => update('primary_color', v)}
        />
        <ColorField
          label="Color de acento"
          value={theme.accent_color ?? ''}
          onChange={(v) => update('accent_color', v)}
        />
        <ColorField
          label="Color hover"
          value={theme.accent_hover_color ?? ''}
          onChange={(v) => update('accent_hover_color', v)}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Telefono" value={theme.phone ?? ''} onChange={(v) => update('phone', v)} />
        <Field
          label="WhatsApp"
          value={theme.whatsapp ?? ''}
          onChange={(v) => update('whatsapp', v)}
        />
      </div>

      <div className="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
        <h3 className="text-sm font-semibold text-slate-700">Tokens custom</h3>
        <p className="text-xs text-slate-500">
          Design tokens adicionales (fonts, spacing, etc). Vacío por default.
        </p>
        <div className="grid gap-3 sm:grid-cols-2">
          <TokenField
            label="font_family"
            value={String(theme.tokens?.font_family ?? '')}
            onChange={(v) => updateToken('font_family', v)}
            placeholder="Inter"
          />
          <TokenField
            label="font_size_base"
            value={String(theme.tokens?.font_size_base ?? '')}
            onChange={(v) => updateToken('font_size_base', v)}
            placeholder="16px"
          />
        </div>
      </div>

      <div className="flex items-center justify-between border-t border-slate-200 pt-4">
        <StatusMessage status={status} />
        <button
          type="submit"
          disabled={status.type === 'saving'}
          className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {status.type === 'saving' ? 'Guardando...' : 'Guardar theme'}
        </button>
      </div>
    </form>
  );
}

interface FieldProps {
  label: string;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  placeholder?: string;
}

function Field({ label, value, onChange, type = 'text', placeholder }: FieldProps): ReactElement {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
      <input
        type={type}
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-1 focus:ring-slate-500"
      />
    </label>
  );
}

interface ColorFieldProps {
  label: string;
  value: string;
  onChange: (v: string) => void;
}

function ColorField({ label, value, onChange }: ColorFieldProps): ReactElement {
  const displayValue = value && /^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#000000';

  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
      <div className="flex items-center gap-2">
        <input
          type="color"
          value={displayValue}
          onChange={(e) => onChange(e.target.value.toUpperCase())}
          className="h-10 w-12 cursor-pointer rounded border border-slate-300"
        />
        <input
          type="text"
          value={value}
          placeholder="#000000"
          onChange={(e) => onChange(e.target.value)}
          className="flex-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-1 focus:ring-slate-500"
        />
      </div>
    </label>
  );
}

interface TokenFieldProps {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
}

function TokenField({ label, value, onChange, placeholder }: TokenFieldProps): ReactElement {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-mono text-slate-600">{label}</span>
      <input
        type="text"
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 font-mono text-xs outline-none focus:border-slate-500 focus:ring-1 focus:ring-slate-500"
      />
    </label>
  );
}

function StatusMessage({ status }: { status: Status }): ReactElement {
  if (status.type === 'idle') {
    return <span className="text-sm text-slate-500">Sin cambios guardados.</span>;
  }
  if (status.type === 'saving') {
    return <span className="text-sm text-slate-500">Guardando...</span>;
  }
  if (status.type === 'success') {
    return (
      <span className="text-sm font-medium text-emerald-600" role="status">
        {status.message}
      </span>
    );
  }
  return (
    <span className="text-sm font-medium text-rose-600" role="alert">
      {status.message}
    </span>
  );
}
