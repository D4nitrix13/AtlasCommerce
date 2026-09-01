/**
 * RemRnfForm.tsx
 *
 * Zod-backed form for creating / editing a non-functional requirement
 * (REQ-19). Mirror of `RemRfForm.tsx` with `category` instead of
 * `module` and no `verifiedBy` (RNFs are not verified by anyone in
 * REM 1.2.2).
 */

import { useForm, type SubmitHandler } from "react-hook-form";
import type { ReactElement } from "react";

import { useTranslations } from "../../../i18n";
import {
  rnfCreateSchema,
  rnfUpdateSchema,
  type RnfCreateInput,
  type RnfUpdateInput,
} from "../../../lib/rem/rnf.schema";
import { zodResolver } from "../../../lib/rem/zod-resolver";

export interface RemRnfFormProps {
  mode: "create" | "edit";
  defaultValues?: Partial<RnfUpdateInput>;
  onSubmit: (values: RnfCreateInput | RnfUpdateInput) => void;
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

export function RemRnfForm({
  mode,
  defaultValues,
  onSubmit,
  onCancel,
}: RemRnfFormProps): ReactElement {
  const { t } = useTranslations();
  const schema = mode === "create" ? rnfCreateSchema : rnfUpdateSchema;
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RnfCreateInput | RnfUpdateInput>({
    resolver: zodResolver(schema),
    mode: "onBlur",
    defaultValues: {
      code: "",
      title: "",
      rationale: "",
      priority: "M",
      status: "pending",
      category: "",
      fuentes: [],
      dependencias: [],
      ...defaultValues,
    } as RnfCreateInput | RnfUpdateInput,
  });

  const submit: SubmitHandler<RnfCreateInput | RnfUpdateInput> = (values) => {
    onSubmit(values);
  };

  return (
    <form
      onSubmit={handleSubmit(submit)}
      className="rem-rnf-form flex flex-col gap-3"
      data-testid="rem-rnf-form"
    >
      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.code.label")}</span>
        <input
          type="text"
          data-testid="rem-rnf-form-code"
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
          data-testid="rem-rnf-form-title"
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
          data-testid="rem-rnf-form-priority"
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
          data-testid="rem-rnf-form-status"
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
        <span className="font-medium text-slate-700">{t("rem.form.category.label")}</span>
        <input
          type="text"
          data-testid="rem-rnf-form-category"
          {...register("category")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
        {errors.category !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.category.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">{t("rem.form.rationale.label")}</span>
        <textarea
          data-testid="rem-rnf-form-rationale"
          {...register("rationale")}
          rows={3}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
      </label>

      <div className="flex justify-end gap-2 pt-2">
        <button
          type="button"
          data-testid="rem-rnf-form-cancel"
          onClick={onCancel}
          className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
        >
          {t("rem.crud.cancel")}
        </button>
        <button
          type="submit"
          data-testid="rem-rnf-form-submit"
          className="rounded-md border border-[#2D9D8E] bg-[#2D9D8E] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#24897B]"
        >
          {t(mode === "create" ? "rem.crud.create.submit" : "rem.crud.edit.submit")}
        </button>
      </div>
    </form>
  );
}

export default RemRnfForm;
