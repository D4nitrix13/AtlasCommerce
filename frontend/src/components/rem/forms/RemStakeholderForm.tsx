/**
 * RemStakeholderForm.tsx
 *
 * Zod-backed form for creating / editing a stakeholder (REQ-19).
 * Smaller shape than RF/RNF: `name`, `title`, `role`, `organization`,
 * `notes`. No code/module/category.
 */

import { useForm, type SubmitHandler } from "react-hook-form";
import type { ReactElement } from "react";

import { useTranslations } from "../../../i18n";
import {
  stakeholderCreateSchema,
  stakeholderUpdateSchema,
  type StakeholderCreateInput,
  type StakeholderUpdateInput,
} from "../../../lib/rem/stakeholder.schema";
import { zodResolver } from "../../../lib/rem/zod-resolver";

export interface RemStakeholderFormProps {
  mode: "create" | "edit";
  defaultValues?: Partial<StakeholderUpdateInput>;
  onSubmit: (values: StakeholderCreateInput | StakeholderUpdateInput) => void;
  onCancel: () => void;
}

export function RemStakeholderForm({
  mode,
  defaultValues,
  onSubmit,
  onCancel,
}: RemStakeholderFormProps): ReactElement {
  const { t } = useTranslations();
  const schema = mode === "create" ? stakeholderCreateSchema : stakeholderUpdateSchema;
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<StakeholderCreateInput | StakeholderUpdateInput>({
    resolver: zodResolver(schema),
    mode: "onBlur",
    defaultValues: {
      name: "",
      title: "",
      role: "",
      organization: "",
      notes: "",
      ...defaultValues,
    } as StakeholderCreateInput | StakeholderUpdateInput,
  });

  const submit: SubmitHandler<StakeholderCreateInput | StakeholderUpdateInput> = (
    values
  ) => {
    onSubmit(values);
  };

  return (
    <form
      onSubmit={handleSubmit(submit)}
      className="rem-stakeholder-form flex flex-col gap-3"
      data-testid="rem-stakeholder-form"
    >
      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
          {t("rem.form.stakeholder.name.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-name"
          {...register("name")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
        {errors.name !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.stakeholder.name.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
          {t("rem.form.stakeholder.title.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-title"
          {...register("title")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
          {t("rem.form.stakeholder.role.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-role"
          {...register("role")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
          {t("rem.form.stakeholder.organization.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-organization"
          {...register("organization")}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
      </label>

      <label className="flex flex-col gap-1.5 text-sm">
        <span className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
          {t("rem.form.stakeholder.notes.label")}
        </span>
        <textarea
          data-testid="rem-stakeholder-form-notes"
          {...register("notes")}
          rows={3}
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors placeholder:text-slate-400 hover:border-slate-400 focus:border-[#2D9D8E] focus:outline-none focus:ring-2 focus:ring-[#2D9D8E]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500"
        />
      </label>

      <div className="-mx-8 -mb-8 mt-2 flex items-center justify-end gap-3 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-8 py-4">
        <button
          type="button"
          data-testid="rem-stakeholder-form-cancel"
          onClick={onCancel}
          className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        >
          {t("rem.crud.cancel")}
        </button>
        <button
          type="submit"
          data-testid="rem-stakeholder-form-submit"
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

export default RemStakeholderForm;
