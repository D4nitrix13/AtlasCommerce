// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { GoogleIcon, FacebookIcon } from '../../../../src/components/ui/SocialIcons';

describe('components/ui/SocialIcons (Task 2.16)', () => {
  it('GoogleIcon renderiza un <svg> con viewBox correcto', () => {
    const { container } = render(<GoogleIcon />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
    expect(svg?.getAttribute('viewBox')).toBe('0 0 24 24');
    // Google icon usa 4 colores oficiales.
    expect(svg?.querySelectorAll('path').length).toBe(4);
  });

  it('FacebookIcon renderiza un <svg> con viewBox correcto', () => {
    const { container } = render(<FacebookIcon />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
    expect(svg?.getAttribute('viewBox')).toBe('0 0 24 24');
    expect(svg?.querySelectorAll('path').length).toBe(1);
  });

  it('GoogleIcon y FacebookIcon son aria-hidden', () => {
    const { container: c1 } = render(<GoogleIcon />);
    const { container: c2 } = render(<FacebookIcon />);
    expect(c1.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
    expect(c2.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
  });

  it('Ambos exponen un size modificable via width/height', () => {
    const { container: c1 } = render(<GoogleIcon />);
    const { container: c2 } = render(<FacebookIcon />);
    // Por defecto width/height = "18".
    expect(c1.querySelector('svg')?.getAttribute('width')).toBe('18');
    expect(c2.querySelector('svg')?.getAttribute('width')).toBe('18');
  });
});
