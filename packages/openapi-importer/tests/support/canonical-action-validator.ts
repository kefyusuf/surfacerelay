import { readFileSync } from 'node:fs';

import { Ajv2020, type ErrorObject } from 'ajv/dist/2020.js';

const schema = JSON.parse(
  readFileSync(
    new URL('../../../../spec/0.1/action-definition.schema.json', import.meta.url),
    'utf8',
  ),
) as object;

const ajv = new Ajv2020({ allErrors: true, strict: true });
const validate = ajv.compile(schema);

export interface CanonicalValidationResult {
  valid: boolean;
  errors: string[];
}

export function validateCanonicalActionDefinition(
  value: unknown,
): CanonicalValidationResult {
  const valid = validate(value);

  return {
    valid: valid === true,
    errors: (validate.errors ?? []).map(
      (error: ErrorObject) => `${error.instancePath || '/'} ${error.message ?? 'invalid'}`,
    ),
  };
}
