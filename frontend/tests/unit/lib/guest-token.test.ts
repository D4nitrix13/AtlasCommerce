/**
 * Tests de la lógica pura del guest token del rail "Visto recientemente".
 *
 * Casos cubiertos:
 *   - `createGuestToken`: UUID v4 válido y no repetido.
 *   - `resolveGuestToken`: reutiliza el token válido de la cookie,
 *     genera + setea cuando falta, y reemplaza valores corruptos.
 */
import { describe, expect, it } from 'vitest';
import {
  GUEST_TOKEN_COOKIE,
  GUEST_TOKEN_MAX_AGE_SECONDS,
  createGuestToken,
  resolveGuestToken,
  type GuestTokenCookies,
} from '../../../src/lib/guest-token';

const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

interface SetCall {
  name: string;
  value: string;
  options: { maxAge: number; sameSite: 'lax'; path: string };
}

/** Cookie jar fake: mismo contrato estructural que `Astro.cookies`. */
function fakeJar(initial?: string): GuestTokenCookies & { sets: SetCall[] } {
  const sets: SetCall[] = [];
  return {
    sets,
    get(name: string) {
      if (name !== GUEST_TOKEN_COOKIE || initial === undefined) return undefined;
      return { value: initial };
    },
    set(name: string, value: string, options): void {
      sets.push({ name, value, options });
    },
  };
}

describe('createGuestToken', () => {
  it('devuelve un UUID v4', () => {
    expect(createGuestToken()).toMatch(UUID_V4);
  });

  it('no repite tokens entre llamadas', () => {
    const first = createGuestToken();
    const second = createGuestToken();
    expect(first).not.toBe(second);
  });
});

describe('resolveGuestToken', () => {
  it('reutiliza el token válido ya seteado en la cookie', () => {
    const existing = '123e4567-e89b-42d3-a456-426614174000';
    const jar = fakeJar(existing);

    expect(resolveGuestToken(jar)).toBe(existing);
    expect(jar.sets).toEqual([]);
  });

  it('genera y setea el token cuando la cookie no existe', () => {
    const jar = fakeJar();

    const token = resolveGuestToken(jar);

    expect(token).toMatch(UUID_V4);
    expect(jar.sets).toHaveLength(1);
    expect(jar.sets[0]?.name).toBe(GUEST_TOKEN_COOKIE);
    expect(jar.sets[0]?.value).toBe(token);
    expect(jar.sets[0]?.options).toEqual({
      maxAge: GUEST_TOKEN_MAX_AGE_SECONDS,
      sameSite: 'lax',
      path: '/',
    });
  });

  it('reemplaza un valor corrupto con un token nuevo', () => {
    const jar = fakeJar('no-es-un-uuid');

    const token = resolveGuestToken(jar);

    expect(token).toMatch(UUID_V4);
    expect(token).not.toBe('no-es-un-uuid');
    expect(jar.sets).toHaveLength(1);
    expect(jar.sets[0]?.value).toBe(token);
  });
});
