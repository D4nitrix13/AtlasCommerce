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
      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.code.label")}</span>
        <input
          type="text"
          data-testid="rem-rf-form-code"
          {...register("code")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
        {errors.code !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.code.invalid")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.title.label")}</span>
        <input
          type="text"
          data-testid="rem-rf-form-title"
          {...register("title")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
        {errors.title !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.title.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.priority.label")}</span>
        <select
          data-testid="rem-rf-form-priority"
          {...register("priority")}
          className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        >
          {PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {p}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.status.label")}</span>
        <select
          data-testid="rem-rf-form-status"
          {...register("status")}
          className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        >
          {STATUSES.map((s) => (
            <option key={s} value={s}>
              {t(`rem.detail.status.${s}` as Parameters<typeof t>[0])}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.module.label")}</span>
        <input
          type="text"
          data-testid="rem-rf-form-module"
          {...register("module")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
        {errors.module !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.module.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.rationale.label")}</span>
        <textarea
          data-testid="rem-rf-form-rationale"
          {...register("rationale")}
          rows={3}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
      </label>

      <div className="flex justify-end gap-2 pt-2">
        <button
          type="button"
          data-testid="rem-rf-form-cancel"
          onClick={onCancel}
          className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
        >
          {t("rem.crud.cancel")}
        </button>
        <button
          type="submit"
          data-testid="rem-rf-form-submit"
          className="rounded-md border border-[#2D9D8E] bg-[#2D9D8E] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#24897B]"
        >
          {t(mode === "create" ? "rem.crud.create.submit" : "rem.crud.edit.submit")}
        </button>
      </div>
    </form>
  );
}

export default RemRfForm;
