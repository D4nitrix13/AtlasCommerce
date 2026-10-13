/**
 * scroll-fx.ts — Fallback de IntersectionObserver para scroll-driven animations.
 *
 * Chrome/Edge 115+ soportan `animation-timeline: scroll()` nativamente
 * (definido en scroll-fx.css). Safari actual y Firefox estable NO lo
 * soportan todavia. Este script detecta si CSS animations con scroll
 * timeline corren (via `getComputedStyle().animationName`) y, si no,
 * usa IntersectionObserver para togglear las clases equivalentes.
 *
 * Output: ~1KB minificado, zero deps. Carga en <script type="module">
 * desde StorefrontLayout.astro, defer por default.
 */

type WithScrollTimeline = typeof window & {
  CSS?: {
    supports?: (condition: string) => boolean;
  };
};

const supportsScrollTimeline = (): boolean => {
  if (typeof window === 'undefined') return false;
  const w = window as WithScrollTimeline;
  if (w.CSS?.supports) {
    return w.CSS.supports('animation-timeline: view()');
  }
  // Fallback heuristico: solo Chrome 115+/Edge soporta.
  return false;
};

const init = (): void => {
  const useNativeScroll = supportsScrollTimeline();

  if (useNativeScroll) {
    // CSS scroll-timeline funciona — no necesitamos hacer nada. Los
    // elementos con data-reveal ya se animan via animation-timeline.
    return;
  }

  // Fallback IntersectionObserver: cuando un elemento [data-reveal]
  // o [data-reveal-stagger] > * entra al viewport, agrega data-revealed.
  // Las clases CSS[data-revealed] tienen specificity alta y ganan sobre
  // los estados de inicio (opacity:0, translateY).
  if (typeof IntersectionObserver === 'undefined') return;

  const revealObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          (entry.target as HTMLElement).setAttribute('data-revealed', '');
          revealObserver.unobserve(entry.target);
        }
      }
    },
    { threshold: 0.15, rootMargin: '0px 0px -10% 0px' },
  );

  document
    .querySelectorAll('[data-reveal], [data-reveal-stagger] > *')
    .forEach((el) => revealObserver.observe(el));

  // Parallax manual: en browsers sin scroll-timeline, animamos
  // transform translateY segun la posicion del scroll. Menos porcentaje
  // = mas sutil. Usar requestAnimationFrame para suavidad.
  const parallaxTargets = Array.from(
    document.querySelectorAll<HTMLElement>('[data-parallax-slow], [data-parallax-fast]'),
  );
  if (parallaxTargets.length === 0) return;

  let ticking = false;
  const updateParallax = (): void => {
    for (const el of parallaxTargets) {
      const rect = el.getBoundingClientRect();
      const viewportHeight = window.innerHeight;
      if (rect.bottom < 0 || rect.top > viewportHeight) continue;
      // -15% (slow) o -30% (fast) del alto del viewport recorrido.
      const scrolled = (viewportHeight - rect.top) / viewportHeight;
      const isFast = el.hasAttribute('data-parallax-fast');
      const offset = scrolled * viewportHeight * (isFast ? -0.3 : -0.15);
      // Combinar con el rotate pre-existente del elemento (de los
      // mockups). El rotate viene como class Tailwind rotate-[-Ndeg].
      // Para no romper el rotate, sobrescribimos solo el translate.
      el.style.translate = `0 ${offset}px`;
    }
    ticking = false;
  };
  const onScroll = (): void => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(updateParallax);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  updateParallax();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
