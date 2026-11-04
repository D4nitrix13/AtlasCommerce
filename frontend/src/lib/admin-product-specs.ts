/**
 * Lógica pura de la ficha técnica admin (tallas + valores de atributo).
 *
 * Separada en dos capas:
 *  - `*PayloadFromForm` valida el input crudo del form y arma el payload
 *    de dominio (camelCase, como los resources del backend).
 *  - `*ToApiBody` / `*FromApi` adaptan al casing real de la API: los
 *    FormRequests del backend validan `product_id`/`attribute_id`
 *    (snake_case) aunque las respuestas vuelvan en camelCase.
 *
 * Sin i18n: los errores son códigos estables que el caller traduce.
 */
import type {
  AttributeValueMock,
  AttributeValueType,
  ProductSizeMock,
} from '../data/catalog-mock';

export const SPECS_SIZE_UNITS = ['oz', 'lb', 'ml', 'kg', 'cm', 'un'] as const;
export type SizeUnit = (typeof SPECS_SIZE_UNITS)[number];

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const MAX_SLUG_LENGTH = 64;
const MAX_LABEL_LENGTH = 64;
const MAX_ATTRIBUTE_VALUE_LENGTH = 255;

export const ATTRIBUTE_DICTIONARY_TYPES = ['text', 'number', 'boolean', 'select'] as const;
export type AttributeDictionaryType = (typeof ATTRIBUTE_DICTIONARY_TYPES)[number];

const MIN_ATTRIBUTE_NAME_LENGTH = 2;
const MAX_ATTRIBUTE_NAME_LENGTH = 100;
const MIN_ATTRIBUTE_SLUG_LENGTH = 2;
const MAX_ATTRIBUTE_SLUG_LENGTH = 120;
const MAX_ATTRIBUTE_OPTIONS = 50;
const MAX_ATTRIBUTE_OPTION_LENGTH = 120;
const MAX_ATTRIBUTE_DESCRIPTION_LENGTH = 5000;

export type SpecsErrorCode =
  | 'slugRequired'
  | 'slugInvalid'
  | 'slugTooLong'
  | 'labelRequired'
  | 'labelTooLong'
  | 'valueRequired'
  | 'valueNotNumeric'
  | 'valueNegative'
  | 'valueTooLong'
  | 'unitInvalid'
  | 'positionInvalid'
  | 'attributeRequired';

export type AttributeDictionaryErrorCode =
  | 'nameRequired'
  | 'nameTooShort'
  | 'nameTooLong'
  | 'slugRequired'
  | 'slugInvalid'
  | 'slugTooShort'
  | 'slugTooLong'
  | 'typeInvalid'
  | 'unitInvalid'
  | 'optionsTooMany'
  | 'optionEmpty'
  | 'optionTooLong'
  | 'optionDuplicated'
  | 'descriptionTooLong';

export interface SpecsFormError<Code extends string = SpecsErrorCode> {
  field: string;
  code: Code;
}

export type SpecsResult<T, Code extends string = SpecsErrorCode> =
  | { ok: true; payload: T }
  | { ok: false; errors: SpecsFormError<Code>[] };

export interface SizeFormInput {
  productId: number;
  slug: string;
  label: string;
  /** Crudo del input: '' → null; si hay contenido debe ser numérico ≥ 0. */
  value: string;
  /** Crudo del select: '' → null; si no, debe estar en SPECS_SIZE_UNITS. */
  unit: string;
  /** Crudo del input: '' → 0; si no, entero ≥ 0. */
  position: string;
}

export interface SizePayload {
  productId: number;
  slug: string;
  label: string;
  value: number | null;
  unit: SizeUnit | null;
  position: number;
}

export interface AttributeValueFormInput {
  productId: number;
  /** Id del atributo del diccionario; '' → error attributeRequired. */
  attributeId: number | '';
  type: AttributeValueType;
  /** Crudo del campo de valor según type ('true'/'false' para boolean). */
  rawValue: string;
}

export interface AttributeValuePayload {
  productId: number;
  attributeId: number;
  value: string | null;
  valueNumber: number | null;
  valueBoolean: boolean | null;
}

export interface SizeApiBody {
  product_id: number;
  slug: string;
  label: string;
  value: number | null;
  unit: string | null;
  position: number;
}

export interface AttributeValueApiBody {
  product_id: number;
  attribute_id: number;
  value: string;
  value_number: number | null;
  value_boolean: boolean | null;
}

/** Form del diccionario de atributos (POST/PUT /admin/attributes). */
export interface AttributeDictionaryFormInput {
  name: string;
  /** '' → se deriva del name vía slugify; el builder valida el resultado. */
  slug: string;
  type: string;
  /** Crudo del select: '' → null; si no, debe estar en SPECS_SIZE_UNITS. */
  unit: string;
  options: readonly string[];
  description: string;
  isFilterable: boolean;
  isRequired: boolean;
}

export interface AttributeDictionaryPayload {
  name: string;
  slug: string;
  type: AttributeDictionaryType;
  unit: SizeUnit | null;
  options: string[] | null;
  description: string | null;
  isFilterable: boolean;
  isRequired: boolean;
}

export interface AttributeDictionaryApiBody {
  name: string;
  slug: string;
  type: string;
  unit: string | null;
  options: string[] | null;
  description: string | null;
  is_filterable: boolean;
  is_required: boolean;
}

/** Fila del GET /admin/catalog/product-sizes (AdminProductSizeResource). */
export interface SizeApiRow {
  id: number;
  productId: number;
  slug: string;
  label: string;
  value: number | string | null;
  unit: string | null;
  position: number | string;
}

/** Fila del GET /admin/attributes (AdminAttributeResource). */
export interface AttributeApiRow {
  id: number;
  name: string;
  slug: string;
  type: string;
  unit?: string | null;
  options?: string[] | null;
  applies_to?: string[] | null;
  description?: string | null;
  is_filterable?: boolean;
  is_required?: boolean;
}

/** Fila del GET /admin/catalog/product-attribute-values (snake_case). */
export interface AttributeValueApiRow {
  id: number;
  product_id: number;
  attribute_id: number;
  value: string | null;
  value_number?: number | string | null;
  value_boolean?: boolean | null;
}

function fail<Code extends string>(field: string, code: Code): SpecsFormError<Code>[] {
  return [{ field, code }];
}

function parseNumeric(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === '') return null;
  const parsed = Number(trimmed);
  return Number.isFinite(parsed) ? parsed : Number.NaN;
}

export function sizePayloadFromForm(input: SizeFormInput): SpecsResult<SizePayload> {
  const errors: SpecsFormError[] = [];

  const slug = input.slug.trim();
  if (slug === '') {
    errors.push(...fail('slug', 'slugRequired'));
  } else if (!SLUG_PATTERN.test(slug)) {
    errors.push(...fail('slug', 'slugInvalid'));
  } else if (slug.length > MAX_SLUG_LENGTH) {
    errors.push(...fail('slug', 'slugTooLong'));
  }

  const label = input.label.trim();
  if (label === '') {
    errors.push(...fail('label', 'labelRequired'));
  } else if (label.length > MAX_LABEL_LENGTH) {
    errors.push(...fail('label', 'labelTooLong'));
  }

  let value: number | null = null;
  const rawValue = input.value.trim();
  if (rawValue !== '') {
    const parsed = parseNumeric(rawValue);
    if (parsed === null || Number.isNaN(parsed)) {
      errors.push(...fail('value', 'valueNotNumeric'));
    } else if (parsed < 0) {
      errors.push(...fail('value', 'valueNegative'));
    } else {
      value = parsed;
    }
  }

  let unit: SizeUnit | null = null;
  const rawUnit = input.unit.trim();
  if (rawUnit !== '') {
    if ((SPECS_SIZE_UNITS as readonly string[]).includes(rawUnit)) {
      unit = rawUnit as SizeUnit;
    } else {
      errors.push(...fail('unit', 'unitInvalid'));
    }
  }

  let position = 0;
  const rawPosition = input.position.trim();
  if (rawPosition !== '') {
    const parsed = parseNumeric(rawPosition);
    if (parsed === null || Number.isNaN(parsed) || !Number.isInteger(parsed) || parsed < 0) {
      errors.push(...fail('position', 'positionInvalid'));
    } else {
      position = parsed;
    }
  }

  if (errors.length > 0) return { ok: false, errors };

  return {
    ok: true,
    payload: {
      productId: input.productId,
      slug,
      label,
      value,
      unit,
      position,
    },
  };
}

export function attributeValuePayloadFromForm(
  input: AttributeValueFormInput
): SpecsResult<AttributeValuePayload> {
  const errors: SpecsFormError[] = [];

  if (input.attributeId === '' || !Number.isInteger(input.attributeId)) {
    errors.push(...fail('attributeId', 'attributeRequired'));
  }

  const raw = input.rawValue.trim();
  let value: string | null = null;
  let valueNumber: number | null = null;
  let valueBoolean: boolean | null = null;

  switch (input.type) {
    case 'number': {
      if (raw === '') {
        errors.push(...fail('rawValue', 'valueRequired'));
        break;
      }
      const parsed = parseNumeric(raw);
      if (parsed === null || Number.isNaN(parsed)) {
        errors.push(...fail('rawValue', 'valueNotNumeric'));
      } else {
        valueNumber = parsed;
      }
      break;
    }
    case 'boolean': {
      if (raw === 'true') {
        valueBoolean = true;
      } else if (raw === 'false') {
        valueBoolean = false;
      } else {
        errors.push(...fail('rawValue', 'valueRequired'));
      }
      break;
    }
    default: {
      if (raw === '') {
        errors.push(...fail('rawValue', 'valueRequired'));
      } else if (raw.length > MAX_ATTRIBUTE_VALUE_LENGTH) {
        errors.push(...fail('rawValue', 'valueTooLong'));
      } else {
        value = raw;
      }
    }
  }

  if (errors.length > 0) return { ok: false, errors };

  return {
    ok: true,
    payload: {
      productId: input.productId,
      attributeId: input.attributeId as number,
      value,
      valueNumber,
      valueBoolean,
    },
  };
}

export function sizeToApiBody(payload: SizePayload): SizeApiBody {
  return {
    product_id: payload.productId,
    slug: payload.slug,
    label: payload.label,
    value: payload.value,
    unit: payload.unit,
    position: payload.position,
  };
}

/**
 * `value` es NOT NULL string en el FormRequest actual: para los tipos
 * numérico/booleano se sintetiza su forma canónica en texto además de
 * exponer los campos tipados (`value_number`/`value_boolean`, columnas
 * reales de `attribute_product`).
 */
export function attributeValueToApiBody(payload: AttributeValuePayload): AttributeValueApiBody {
  const value =
    payload.value ??
    (payload.valueNumber !== null
      ? String(payload.valueNumber)
      : payload.valueBoolean !== null
        ? String(payload.valueBoolean)
        : '');
  return {
    product_id: payload.productId,
    attribute_id: payload.attributeId,
    value,
    value_number: payload.valueNumber,
    value_boolean: payload.valueBoolean,
  };
}

export function sizeFromApi(row: SizeApiRow): ProductSizeMock {
  return {
    id: row.id,
    productId: row.productId,
    slug: row.slug,
    label: row.label,
    value: row.value === null ? null : Number(row.value),
    unit: row.unit,
    position: Number(row.position),
  };
}

export function normalizeAttributeType(type: string): AttributeValueType {
  return type === 'number' || type === 'boolean' || type === 'select' ? type : 'text';
}

/**
 * Une la fila plana de `attribute_product` con su definición del
 * diccionario. El admin API devuelve el valor en `value` (string); si
 * los campos tipados no vienen, se derivan de `value` según el type.
 */
export function attributeValueFromApi(
  row: AttributeValueApiRow,
  attr: AttributeApiRow
): AttributeValueMock {
  const type = normalizeAttributeType(attr.type);
  const derivedNumber = row.value === null || row.value === '' ? null : Number(row.value);
  const valueNumber =
    row.value_number !== undefined && row.value_number !== null
      ? Number(row.value_number)
      : type === 'number' && derivedNumber !== null && Number.isFinite(derivedNumber)
        ? derivedNumber
        : null;
  const valueBoolean =
    row.value_boolean !== undefined && row.value_boolean !== null
      ? Boolean(row.value_boolean)
      : type === 'boolean' && (row.value === 'true' || row.value === 'false')
        ? row.value === 'true'
        : null;

  return {
    id: row.id,
    productId: row.product_id,
    slug: attr.slug,
    label: attr.name,
    type,
    unit: attr.unit ?? null,
    value: type === 'text' || type === 'select' ? row.value : null,
    valueNumber,
    valueBoolean,
  };
}

/**
 * Normaliza un nombre a slug kebab-case: minúsculas, sin acentos,
 * separadores colapsados. Devuelve '' si no queda nada usable.
 */
export function slugify(raw: string): string {
  return raw
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Valida el form del diccionario de atributos con las reglas del
 * FormRequest del backend (name/slug/type/unit/options/description)
 * más la unicidad y no-vaciedad de options, que el backend no exige
 * pero el editor sí garantiza.
 */
export function attributeDictionaryPayloadFromForm(
  input: AttributeDictionaryFormInput
): SpecsResult<AttributeDictionaryPayload, AttributeDictionaryErrorCode> {
  const errors: SpecsFormError<AttributeDictionaryErrorCode>[] = [];

  const name = input.name.trim();
  if (name === '') {
    errors.push(...fail('name', 'nameRequired'));
  } else if (name.length < MIN_ATTRIBUTE_NAME_LENGTH) {
    errors.push(...fail('name', 'nameTooShort'));
  } else if (name.length > MAX_ATTRIBUTE_NAME_LENGTH) {
    errors.push(...fail('name', 'nameTooLong'));
  }

  const slug = input.slug.trim() !== '' ? input.slug.trim() : slugify(input.name);
  if (slug === '') {
    errors.push(...fail('slug', 'slugRequired'));
  } else if (!SLUG_PATTERN.test(slug)) {
    errors.push(...fail('slug', 'slugInvalid'));
  } else if (slug.length < MIN_ATTRIBUTE_SLUG_LENGTH) {
    errors.push(...fail('slug', 'slugTooShort'));
  } else if (slug.length > MAX_ATTRIBUTE_SLUG_LENGTH) {
    errors.push(...fail('slug', 'slugTooLong'));
  }

  let type: AttributeDictionaryType = 'text';
  if ((ATTRIBUTE_DICTIONARY_TYPES as readonly string[]).includes(input.type)) {
    type = input.type as AttributeDictionaryType;
  } else {
    errors.push(...fail('type', 'typeInvalid'));
  }

  let unit: SizeUnit | null = null;
  const rawUnit = input.unit.trim();
  if (rawUnit !== '') {
    if ((SPECS_SIZE_UNITS as readonly string[]).includes(rawUnit)) {
      unit = rawUnit as SizeUnit;
    } else {
      errors.push(...fail('unit', 'unitInvalid'));
    }
  }

  if (input.options.length > MAX_ATTRIBUTE_OPTIONS) {
    errors.push(...fail('options', 'optionsTooMany'));
  }
  const normalizedOptions: string[] = [];
  const seenOptions = new Set<string>();
  input.options.forEach((rawOption, index) => {
    const option = rawOption.trim();
    if (option === '') {
      errors.push(...fail(`options.${index}`, 'optionEmpty'));
      return;
    }
    if (option.length > MAX_ATTRIBUTE_OPTION_LENGTH) {
      errors.push(...fail(`options.${index}`, 'optionTooLong'));
      return;
    }
    if (seenOptions.has(option)) {
      errors.push(...fail(`options.${index}`, 'optionDuplicated'));
      return;
    }
    seenOptions.add(option);
    normalizedOptions.push(option);
  });

  const trimmedDescription = input.description.trim();
  const description = trimmedDescription === '' ? null : trimmedDescription;
  if (description !== null && description.length > MAX_ATTRIBUTE_DESCRIPTION_LENGTH) {
    errors.push(...fail('description', 'descriptionTooLong'));
  }

  if (errors.length > 0) return { ok: false, errors };

  return {
    ok: true,
    payload: {
      name,
      slug,
      type,
      unit,
      options: normalizedOptions.length > 0 ? normalizedOptions : null,
      description,
      isFilterable: input.isFilterable,
      isRequired: input.isRequired,
    },
  };
}

export function attributeDictionaryToApiBody(
  payload: AttributeDictionaryPayload
): AttributeDictionaryApiBody {
  return {
    name: payload.name,
    slug: payload.slug,
    type: payload.type,
    unit: payload.unit,
    options: payload.options,
    description: payload.description,
    is_filterable: payload.isFilterable,
    is_required: payload.isRequired,
  };
}
