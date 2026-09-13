/// <reference types="vitest/globals" />

/**
 * Ambient types para tests Vitest.
 *
 * Habilita `vi`, `describe`, `it`, `afterEach`, `beforeEach`, etc.
 * como globals (que es lo que hace vitest.config.ts con `globals: true`).
 *
 * Tambien declara `otplib` como modulo externamente tipado para que TS
 * no se queje cuando los tests hacen `await import("otplib")`.
 */
declare module "otplib" {
  export const authenticator: {
    generate: (secret: string) => string;
    verify: (params: { token: string; secret: string }) => boolean;
  };
}

export {};