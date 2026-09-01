/**
 * RemResizeHandle.tsx
 *
 * Sidebar draggable resize handle (REQ-15).
 *
 * Implementation (DD2 lock): CSS `resize: horizontal` on the inner
 * `<aside>` handles all the drag geometry — the browser emits a
 * `resize` event when the user releases the pointer. We listen to
 * that event and persist the final width via
 * `SIDEBAR_WIDTH_STORAGE_KEY` (`rem.viewer.sidebar.width`).
 *
 * Clamp `[minPx, maxPx]` defaults to `[280, 560]`. Default width
 * `416` (matches v2's `lg:w-[26rem]` in `RemLayout.astro`). On mount,
 * the helper reads the stored value and clamps out-of-range values,
 * overwriting the stored value with the clamped one (REQ-15#2,
 * REQ-15#3).
 *
 * Hidden below the `lg` breakpoint (1024 px) via `hidden lg:block`
 * (REQ-15#5). When hidden, no `localStorage.setItem` fires — the
 * width falls back to `lg:w-80`.
 */

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactElement,
} from 'react';

import { SIDEBAR_WIDTH_STORAGE_KEY } from '../../lib/rem/types';

// ─── Constants ───────────────────────────────────────────────────────

/** Matches `RemLayout.astro`'s `lg:w-[26rem]`. */
const DEFAULT_WIDTH_PX = 416;
/** REQ-15#1 lock — clamp range. */
const MIN_WIDTH_PX = 280;
/** REQ-15#1 lock — clamp range. */
const MAX_WIDTH_PX = 560;

// ─── Helpers ─────────────────────────────────────────────────────────

/**
 * Parse a stored value as a number. Returns `null` for any
 * non-numeric / missing / NaN value — the caller falls back to the
 * default and never persists the ( value.
 */
function readStoredWidth(raw: string | null): number | null {
  if (raw === null) return null;
  const n = Number(raw);
  if (!Number.isFinite(n)) return null;
  return n;
}

/** Clamp + default. Out-of-range values are clamped AND the caller
 * overwrites the stored value on first render (REQ-15#2/#3). */
function clamp(width: number, min: number, max: number): number {
  if (width < min) return min;
  if (width > max) return max;
  return width;
}

/** Safe localStorage write — swallows quota / disabled errors. */
function writeStoredWidth(width: number): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(SIDEBAR_WIDTH_STORAGE_KEY, String(width));
  } catch {
    // ignore — never crash the sidebar over a persist
  }
}

// ─── Component ───────────────────────────────────────────────────────

export interface RemResizeHandleProps {
  /** Optional override for the clamp floor (default 240). */
  minPx?: number;
  /** Optional override for the clamp ceiling (default 480). */
  maxPx?: number;
  /** Optional override for the default width (default 320). */
  defaultPx?: number;
  /**
   * The inner `<aside>` element this handle resizes. The parent
   * sidebar supplies this so the handle can apply `resize: horizontal`
   * + the min/max-width CSS clamp directly on the rendered node.
   *
   * Falls back to a `data-rem-resize-target` CSS selector when
   * omitted (the value used by the unit tests).
   */
  targetSelector?: string;
}

/**
 * Draggable resize handle. Mounts a `<div data-rem-resize-handle>`
 * that decorates the inner `<aside>` with CSS `resize: horizontal` so
 * the browser handles drag geometry; we only persist on `resize`.
 *
 * The component is purely declarative — the actual width is applied
 * via a CSS custom property (`--rem-sidebar-width`) that the parent
 * sidebar reads when it sets `style={{ width: 'var(...)' }}`.
 */
export function RemResizeHandle({
  minPx = MIN_WIDTH_PX,
  maxPx = MAX_WIDTH_PX,
  defaultPx = DEFAULT_WIDTH_PX,
  targetSelector = 'aside[data-rem-resize-target]',
}: RemResizeHandleProps): ReactElement {
  const min = minPx;
  const max = maxPx;
  const fallback = defaultPx;

  // Initial state: read storage → clamp → fall back to default.
  // Out-of-range values are clamped AND we overwrite storage with the
  // clamped value on first render (REQ-15#2, REQ-15#3).
  const initial = useMemo<number>(() => {
    if (typeof window === 'undefined') return fallback;
    const stored = readStoredWidth(window.localStorage.getItem(SIDEBAR_WIDTH_STORAGE_KEY));
    if (stored === null) return fallback;
    const clamped = clamp(stored, min, max);
    if (clamped !== stored) {
      writeStoredWidth(clamped);
    }
    return clamped;
  }, [min, max, fallback]);

  const [width, setWidth] = useState<number>(initial);
  const targetRef = useRef<HTMLElement | null>(null);

  // Apply the width as a CSS custom property on the inner `<aside>`
  // and attach the `resize: horizontal` affordance via inline style.
  // We touch the DOM directly because the parent sidebar's `<aside>`
  // is rendered as part of `RemLayout.astro` (not inside this React
  // subtree) — CSS custom property is the cheapest cross-boundary
  // signal that satisfies both the handle and the layout.
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const aside = document.querySelector<HTMLElement>(targetSelector);
    if (aside === null) return;
    targetRef.current = aside;
    aside.style.resize = 'horizontal';
    aside.style.minWidth = `${min}px`;
    aside.style.maxWidth = `${max}px`;
    aside.style.width = `${width}px`;
    aside.style.setProperty('--rem-sidebar-width', `${width}px`);
  }, [width, min, max, targetSelector]);

  // Listen to the `resize` event on the inner `<aside>`. The browser
  // fires this once per pointer release, satisfying REQ-15#4
  // ("persist on pointerup, not on every pixel"). We also dedupe via
  // a ref so the initial mount's style application doesn't trigger a
  // redundant `setItem`.
  const lastWrittenRef = useRef<number>(initial);
  useEffect(() => {
    const aside = targetRef.current;
    if (aside === null) return;

    const handleResize = () => {
      const measured = aside.offsetWidth;
      if (measured === 0) return;
      const clamped = clamp(measured, min, max);
      setWidth(clamped);
      if (clamped !== lastWrittenRef.current) {
        writeStoredWidth(clamped);
        lastWrittenRef.current = clamped;
      }
    };

    aside.addEventListener('resize', handleResize);
    return () => {
      aside.removeEventListener('resize', handleResize);
    };
  }, [min, max]);

  // Re-clamp if the prop changes (defensive — the handle does not
  // re-mount when these props flip in practice).
  useEffect(() => {
    if (width !== clamp(width, min, max)) {
      const next = clamp(width, min, max);
      setWidth(next);
      writeStoredWidth(next);
    }
    // We deliberately do NOT include `width` in deps — this effect is
    // a prop-change guard.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [min, max]);

  // Track whether we're below the `lg` breakpoint (1024 px). When
  // hidden, we MUST NOT persist a width (REQ-15#5).
  const [isLgUp, setIsLgUp] = useState<boolean>(() => {
    if (typeof window === 'undefined') return true;
    return window.matchMedia('(min-width: 1024px)').matches;
  });
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const mql = window.matchMedia('(min-width: 1024px)');
    const onChange = (event: MediaQueryListEvent) => {
      setIsLgUp(event.matches);
    };
    mql.addEventListener('change', onChange);
    return () => {
      mql.removeEventListener('change', onChange);
    };
  }, []);

  // No-op handler reserved for a future click-to-reset feature
  // (currently the spec only requires drag). Keeping it as a stable
  // callback keeps the component interface stable for the parent.
  const handleDoubleClick = useCallback(() => {
    if (!isLgUp) return;
    setWidth(fallback);
    writeStoredWidth(fallback);
  }, [fallback, isLgUp]);

  return (
    <div
      data-rem-resize-handle
      data-testid="rem-resize-handle"
      aria-hidden={isLgUp ? 'false' : 'true'}
      onDoubleClick={handleDoubleClick}
      className={
        isLgUp
          ? 'rem-resize-handle block h-full w-1 cursor-ew-resize bg-slate-200 transition-colors hover:bg-slate-300'
          : 'rem-resize-handle hidden'
      }
    />
  );
}

export default RemResizeHandle;