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
      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">
          {t("rem.form.stakeholder.name.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-name"
          {...register("name")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
        {errors.name !== undefined ? (
          <span role="alert" className="text-xs text-red-700">
            {t("rem.form.stakeholder.name.required")}
          </span>
        ) : null}
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">
          {t("rem.form.stakeholder.title.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-title"
          {...register("title")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">
          {t("rem.form.stakeholder.role.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-role"
          {...register("role")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">
          {t("rem.form.stakeholder.organization.label")}
        </span>
        <input
          type="text"
          data-testid="rem-stakeholder-form-organization"
          {...register("organization")}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
      </label>

      <label className="flex flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">
          {t("rem.form.stakeholder.notes.label")}
        </span>
        <textarea
          data-testid="rem-stakeholder-form-notes"
          {...register("notes")}
          rows={3}
          className="rounded-md border border-slate-300 px-2 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
        />
      </label>

      <div className="flex justify-end gap-2 pt-2">
        <button
          type="button"
          data-testid="rem-stakeholder-form-cancel"
          onClick={onCancel}
          className="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
        >
          {t("rem.crud.cancel")}
        </button>
        <button
          type="submit"
          data-testid="rem-stakeholder-form-submit"
          className="rounded-md border border-[#2D9D8E] bg-[#2D9D8E] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#24897B]"
        >
          {t(mode === "create" ? "rem.crud.create.submit" : "rem.crud.edit.submit")}
        </button>
      </div>
    </form>
  );
}

export default RemStakeholderForm;
