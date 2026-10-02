import type { ReactElement } from 'react';
import { Check, Circle } from 'lucide-react';

interface SecurityChecklistItem {
  /**
   * Texto del item (ej. "Al menos 12 caracteres").
   * El caller debería pasarlo via i18n.
   */
  label: string;
  /**
   * Si el item está cumplido o no. Cumple → check verde, no cumple → círculo gris.
   */
  met: boolean;
}

interface SecurityChecklistProps {
  /**
   * Título opcional arriba del checklist (ej. "Tu contraseña debe tener:").
   */
  title?: string;
  items: SecurityChecklistItem[];
}

/**
 * Lista de checks de seguridad para mostrar al usuario qué requisitos
 * cumple/no cumple su contraseña.
 *
 * Extraído de `ResetPasswordForm.tsx` (Task 2.14 de sdd-apply PR 2).
 * El componente es puramente presentacional: el caller calcula `met`
 * para cada item y los pasa como prop.
 *
 * Visualmente:
 *   - Card gris claro (border + bg-slate-50)
 *   - Lista vertical con icono (Check verde si met, Circle gris si no)
 *   - Texto cambia de color según met (teal vs slate-400)
 */
export function SecurityChecklist({ title, items }: SecurityChecklistProps): ReactElement {
  return (
    <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
      {title !== undefined && title !== '' && (
        <p className="mb-2 text-xs font-semibold text-slate-700">{title}</p>
      )}
      <ul className="space-y-1.5 text-xs text-slate-500">
        {items.map((item, index) => (
          <ChecklistRow key={index} met={item.met} label={item.label} />
        ))}
      </ul>
    </div>
  );
}

function ChecklistRow({ met, label }: { met: boolean; label: string }): ReactElement {
  return (
    <li className="flex items-center gap-2">
      {met ? (
        <Check
          size={14}
          strokeWidth={2.5}
          className="text-[var(--brand-accent,#2D9D8E)]"
          aria-hidden="true"
        />
      ) : (
        <Circle size={14} strokeWidth={2} className="text-slate-400" aria-hidden="true" />
      )}
      <span className={met ? 'text-[var(--brand-accent,#2D9D8E)]' : 'text-slate-400'}>{label}</span>
    </li>
  );
}
