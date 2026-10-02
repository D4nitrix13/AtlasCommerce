/**
 * Ambient types para `@storybook/astro`.
 *
 * Contexto: las stories del proyecto importan `Meta` y `StoryObj`
 * desde `@storybook/astro` siguiendo la convención histórica de
 * Storybook 8+. En Storybook 10 + `@storybook-astro/framework@1.x`
 * esos tipos NO se re-exportan bajo ese path — el package real
 * del framework es `@storybook-astro/framework`, y los tipos
 * equivalentes viven en `storybook/internal/csf`.
 *
 * Para no reescribir 22 archivos de stories solo para cambiar el
 * path del import, declaramos `Meta` y `StoryObj` con la
 * signature mínima necesaria para que el typecheck compile. La
 * firma es deliberadamente estructural (no genérica con el
 * renderer de Astro) porque en typecheck solo necesitamos que
 * `Meta<typeof X>` acepte los campos que las stories usan
 * (`title`, `component`, `args`, `argTypes`, `parameters`,
 * `tags`, `decorators`) y que `StoryObj<typeof meta>` acepte
 * `args`/`argTypes`/`parameters`/`decorators`.
 *
 * Storybook runtime usa su propio typecheck via `storybook`
 * CLI (que sí carga los types reales del framework), asi que
 * este shim no afecta el dev experience de las stories.
 */
declare module '@storybook/astro' {
  // `type` (no `interface`) porque ambos tienen generics con
  // conditional types que no se pueden expresar como interface.
  // eslint-disable-next-line @typescript-eslint/consistent-type-definitions
  export type Meta<TArgs = Record<string, unknown>> = {
    title?: string;
    component?: unknown;
    tags?: string[];
    argTypes?: Record<string, unknown>;
    args?: Partial<TArgs>;
    parameters?: Record<string, unknown>;
    decorators?: unknown[];
    [key: string]: unknown;
  };

  // eslint-disable-next-line @typescript-eslint/consistent-type-definitions
  export type StoryObj<TMeta = Record<string, unknown>> = {
    args?: TMeta extends { args: infer A }
      ? Partial<A>
      : Partial<TMeta>;
    argTypes?: Record<string, unknown>;
    parameters?: Record<string, unknown>;
    decorators?: unknown[];
    [key: string]: unknown;
  };
}
