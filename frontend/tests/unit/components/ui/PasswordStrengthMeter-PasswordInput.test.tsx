// @vitest-environment jsdom
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { PasswordInput } from '../../../../src/components/ui/PasswordInput';
import { PasswordStrengthMeter } from '../../../../src/components/ui/PasswordStrengthMeter';

describe('components/ui/PasswordInput (Task 2.17)', () => {
  it('renderiza un input type=password por defecto', () => {
    render(<PasswordInput id="pwd" value="" onChange={() => {}} />);
    const input = document.getElementById('pwd') as HTMLInputElement;
    expect(input).not.toBeNull();
    expect(input.type).toBe('password');
  });

  it('el botón toggle cambia type a text y viceversa', async () => {
    const user = userEvent.setup();
    render(<PasswordInput id="pwd" value="" onChange={() => {}} />);
    const input = document.getElementById('pwd') as HTMLInputElement;
    expect(input.type).toBe('password');

    const toggle = screen.getByRole('button', { name: /mostrar contraseña/i });
    await user.click(toggle);
    expect(input.type).toBe('text');

    const toggleHide = screen.getByRole('button', { name: /ocultar contraseña/i });
    await user.click(toggleHide);
    expect(input.type).toBe('password');
  });

  it('re-emite onChange con el valor actual del input', () => {
    const handleChange = vi.fn();
    render(<PasswordInput id="pwd" value="" onChange={handleChange} />);
    const input = document.getElementById('pwd') as HTMLInputElement;
    // Use fireEvent.change para evitar problemas con controlled inputs en jsdom.
    fireEvent.change(input, { target: { value: 'secret' } });
    expect(handleChange).toHaveBeenCalledWith('secret');
  });
});

describe('components/ui/PasswordStrengthMeter (Task 2.17)', () => {
  it('renderiza 4 barras siempre', () => {
    const { container } = render(<PasswordStrengthMeter score={0} />);
    expect(container.querySelectorAll('[data-strength-bar]').length).toBe(4);
  });

  it('las barras rellenas son <= score', () => {
    const { container } = render(<PasswordStrengthMeter score={2} />);
    const bars = container.querySelectorAll('[data-strength-bar]');
    let filledCount = 0;
    bars.forEach((bar) => {
      if (bar.getAttribute('data-filled') === 'true') filledCount += 1;
    });
    expect(filledCount).toBe(2);
  });

  it('muestra label cuando score > 0', () => {
    render(<PasswordStrengthMeter score={1} label="Débil" />);
    expect(screen.getByText('Débil')).toBeInTheDocument();
  });

  it('no muestra label cuando score = 0', () => {
    const { container } = render(<PasswordStrengthMeter score={0} label="Débil" />);
    expect(container.textContent).not.toContain('Débil');
  });
});
