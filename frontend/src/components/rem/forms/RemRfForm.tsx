/**
 * RemRfForm.tsx
 *
 * Zod-backed form for creating / editing a functional requirement
 * (REQ-19). Uses react-hook-form + a 5-line manual zodResolver (see
 * `lib/rem/zod-resolver.ts`; `@hookform/resolvers/zod` is not in
 * `package.json` per design risk #9).
 *
 * Form fields mirror `rfCreateSchema` / `rfUpdateSchema`:
 *   - code  (string, /^RF-[A-Z]+-\d{3}$/)
 *   - title (string, min 1)
 *   - rationale (string, default "")
 *   - priority (H | M | L)
 *   - status (Status enum)
 *   - module (string, min 1)
 *   - verifiedBy (string[], default [])
 *   - fuentes (string[], default [])
 *   - dependencias (string[], default [])
 *
 * The form is `mode: 'onBlur'` so error messages surface after the
 * user leaves a field — matches the spec's "do not nag while typing"
 * UX requirement (REQ-19#1).
 */

import { useForm, type SubmitHandler } from "react-hook-form";
import type { ReactElement } from "react";

import { useTranslations } from "../../../i18n";
import {
  rfCreateSchema,
  rfUpdateSchema,
  type RfCreateInput,
  type RfUpdateInput,
} from "../../../lib/rem/rf.schema";
import { zodResolver } from "../../../lib/rem/zod-resolver";

export interface RemRfFormProps {
  mode: "create" | "edit";
  defaultValues?: Partial<RfUpdateInput>;
  onSubmit: (values: RfCreateInput | RfUpdateInput) => void;
  onCancel: () => void;
}

const PRIORITIES = ["H", "M", "L"] as const;
const STATUSES = [
  "pending",
  "in-analysis",
  "approved",
  "implemented",
  "verified",
] as const;

export function RemRfForm({
  mode,
  defaultValues,
  onSubmit,
  onCancel,
}: RemRfFormProps): ReactElement {
  const { t } = useTranslations();
  const schema = mode === "create" ? rfCreateSchema : rfUpdateSchema;
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RfCreateInput | RfUpdateInput>({
    resolver: zodResolver(schema),
    mode: "onBlur",
    defaultValues: {
      code: "",
      title: "",
      rationale: "",
      priority: "M",
      status: "pending",
      module: "",
      verifiedBy: [],
      fuentes: [],
      dependencias: [],
      ...defaultValues,
    } as RfCreateInput | RfUpdateInput,
  });

  const submit: SubmitHandler<RfCreateInput | RfUpdateInput> = (values) => {
    onSubmit(values);
  };

  return (
    <form
      onSubmit={handleSubmit(submit)}
      className="rem-rf-form flex flex-col gap-3"
      data-testid="rem-rf-form"
    >
      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{t("rem.form.code.label")}</span>
        <input
          type="text"
          data-testid="rem-rf-form-code"
          {...register("code")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
        {errors.code !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.code.invalid")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{t("rem.form.title.label")}</span>
        <input
          type="text"
          data-testid="rem-rf-form-title"
          {...register("title")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
        {errors.title !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.title.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{t("rem.form.priority.label")}</span>
        <select
          data-testid="rem-rf-form-priority"
          {...register("priority")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        >
          {PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {p}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{t("rem.form.status.label")}</span>
        <select
          data-testid="rem-rf-form-status"
          {...register("status")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        >
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`rem.detail.status.${s}` as Parameters<typeof t>[0])}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{t("rem.form.module.label")}</span>
        <input
          type="text"
          data-testid="rem-rf-form-module"
          {...register("module")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
        {errors.module !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.module.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">{t("rem.form.rationale.label")}</span>
        <textarea
          data-testid="rem-rf-form-rationale"
          {...register("rationale")}
          rows={3}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
      </label>

      <div className="-mx-8 -mb-8 mt-2 flex items-center justify-end gap-3 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-8 py-4">
        <button
          type="button"
          data-testid="rem-rf-form-cancel"
          onClick={onCancel}
          className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        >
          {t("rem.crud.cancel")}
        </button>
        <button
          type="submit"
          data-testid="rem-rf-form-submit"
          className="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-[#2D9D8E] to-[#258777] px-5 py-2 text-sm font-semibold text-white shadow-md transition-all hover:from-[#258777] hover:to-[#1E6F62] hover:shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white disabled:cursor-not-allowed disabled:from-slate-300 disabled:to-slate-300 disabled:shadow-none"
        >
          {mode === "create" ? (
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              <path d="M10 4 L10 16 M4 10 L16 10" />
            </svg>
          ) : null}
          {t(mode === "create" ? "rem.crud.create.submit" : "rem.crud.edit.submit")}
        </button>
      </div>
    </form>
  );
}

export default RemRfForm;
