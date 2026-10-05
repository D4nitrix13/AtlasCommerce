// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import { getBrandBySlug, DEFAULT_BRAND, STORE_BRANDS } from '../../../src/config/stores';

describe('config/stores', () => {
  describe('getBrandBySlug', () => {
    it("retorna la marca panda cuando el slug es 'panda-estampados'", () => {
      const brand = getBrandBySlug('panda-estampados');
      expect(brand.slug).toBe('panda-estampados');
      expect(brand.name).toBe('Panda Estampados');
      expect(brand.accentColor).toBe('#2D9D8E');
      expect(brand.logoPath).toContain('panda-estampados');
    });

    it("retorna la marca kitsune cuando el slug es 'kitsune'", () => {
      const brand = getBrandBySlug('kitsune');
      expect(brand.slug).toBe('kitsune');
      expect(brand.name).toBe('Kitsune Store');
      expect(brand.accentColor).toBe('#7C3AED');
      expect(brand.logoPath).toContain('kitsune');
    });

    it('retorna DEFAULT_BRAND cuando el slug es null', () => {
      const brand = getBrandBySlug(null);
      expect(brand).toBe(DEFAULT_BRAND);
    });

    it('retorna DEFAULT_BRAND cuando el slug es desconocido', () => {
      const brand = getBrandBySlug('tienda-inexistente');
      expect(brand).toBe(DEFAULT_BRAND);
    });

    it('retorna DEFAULT_BRAND cuando el slug es string vacío', () => {
      const brand = getBrandBySlug('');
      expect(brand).toBe(DEFAULT_BRAND);
    });
  });

  describe('STORE_BRANDS', () => {
    it('incluye Panda y Kitsune', () => {
      expect(STORE_BRANDS['panda-estampados']).toBeDefined();
      expect(STORE_BRANDS.kitsune).toBeDefined();
    });

    it('incluye los cuatro paquetes de themes', () => {
      expect(STORE_BRANDS['panda-estampados']?.name).toBe('Panda Estampados');
      expect(STORE_BRANDS.kitsune?.name).toBe('Kitsune Store');
      expect(STORE_BRANDS['mm-boutique']?.name).toBe('MM Boutique');
      expect(STORE_BRANDS['morales-store']?.name).toBe('Morales Store');
    });

    it('cada brand tiene los campos requeridos', () => {
      const requiredFields: (keyof typeof DEFAULT_BRAND)[] = [
        'slug',
        'name',
        'shortName',
        'domains',
        'adminDomains',
        'logoPath',
        'loginLogoPath',
        'accentColor',
        'accentHoverColor',
        'supportEmail',
        'loginTheme',
      ];

      for (const brand of Object.values(STORE_BRANDS)) {
        for (const field of requiredFields) {
          expect(brand[field]).toBeDefined();
        }
        expect(brand.accentColor).toMatch(/^#[0-9A-F]{6}$/i);
        expect(brand.accentHoverColor).toMatch(/^#[0-9A-F]{6}$/i);
        expect(brand.domains.length).toBeGreaterThan(0);
        expect(brand.adminDomains.length).toBeGreaterThan(0);
      }
    });

    it('los adminDomains son hosts válidos (.com, .test o localhost)', () => {
      for (const brand of Object.values(STORE_BRANDS)) {
        for (const admin of brand.adminDomains) {
          const isValid =
            admin.endsWith('.com') ||
            admin.endsWith('.test') ||
            admin.includes('localhost') ||
            admin.includes('127.0.0.1');
          expect(isValid).toBe(true);
        }
      }
    });
  });

  describe('DEFAULT_BRAND', () => {
    it('es un fallback válido con todos los campos', () => {
      const requiredFields: (keyof typeof DEFAULT_BRAND)[] = [
        'slug',
        'name',
        'shortName',
        'domains',
        'adminDomains',
        'logoPath',
        'loginLogoPath',
        'accentColor',
        'accentHoverColor',
        'supportEmail',
        'loginTheme',
      ];

      for (const field of requiredFields) {
        expect(DEFAULT_BRAND[field]).toBeDefined();
      }
    });
  });
});
