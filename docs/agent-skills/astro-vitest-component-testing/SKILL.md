---
name: astro-vitest-component-testing
description: Unit tests with Vitest 4 + Testing Library + jsdom for React components. Store mocks, i18n, user interactions. Triggers: creating/modifying tests in frontend/tests/unit/, vitest debugging, adding mock.
---

# Astro Vitest Component Testing

Source: `frontend/vitest.config.ts` + `frontend/tests/setup.ts` + existing tests.

## Stack

- **Vitest 4** - test runner.
- **@testing-library/react** - render + queries.
- **@testing-library/user-event** - user interactions.
- **@testing-library/jest-dom** - extended matchers.
- **jsdom** - DOM environment.
- **Mocking via `vi.mock`** - mock ES modules.

## Config

`frontend/vitest.config.ts`:

```ts
{
 define: {
 "import.meta.env.PUBLIC_API_URL": JSON.stringify("http://localhost:8000/api"),
 },
 test: {
 globals: true,
 environment: "jsdom",
 setupFiles: ["./tests/setup.ts"],
 css: true,
 include: ["tests/unit/**/*.{test,spec}.{ts,tsx}"],
 exclude: ["node_modules", "dist", ".astro", "tests/e2e/**"],
 },
}
```

## Structure

```txt
frontend/tests/
├── setup.ts ← global setup, polyfills, cleanup
├── unit/
│ ├── components/
│ │ ├── admin/
│ │ │ ├── AdminDefaultLogin.test.tsx
│ │ │ └── ThemeForm.test.tsx
│ │ ├── LoginForm.test.tsx
│ │ ├── ForgotPasswordForm.test.tsx
│ │ └── ResetPasswordForm.test.tsx
└── e2e/ ← Playwright, not Vitest
```

## Commands

```bash
# Full suite
pnpm vitest run

# Watch mode
pnpm vitest

# With coverage
pnpm vitest run --coverage

# Specific test
pnpm vitest run tests/unit/components/LoginForm.test.tsx
```

## Anatomy of a test

```tsx
// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

// 1. Hoisted mocks: available inside vi.mock() callbacks
const { mockLogin } = vi.hoisted(() => ({
 mockLogin: vi.fn(),
}));

// 2. Store mocks (must go before component imports)
vi.mock("../../../src/stores/auth", () => ({
 useAuth: () => ({
 login: mockLogin,
 }),
 authStore: { login: mockLogin },
}));

// 3. i18n mock - preserves the rest of the API
vi.mock("../../../src/i18n", async (importOriginal) => {
 const actual = await importOriginal<typeof import("../../../src/i18n")>();
 return {
 ...actual,
 useTranslations: () => ({
 t: (key: string) => key, // identity: t("auth.foo") → "auth.foo"
 locale: "es",
 }),
 };
});

// 4. Component import goes AFTER mocks
import LoginForm from "../../../src/components/admin/auth/LoginForm";

describe("components/admin/auth/LoginForm", () => {
 beforeEach(() => {
 mockLogin.mockReset();
 window.history.replaceState({}, "", "/es/admin/auth/login");
 });

 it("renders the form", () => {
 render(<LoginForm />);
 expect(screen.getByLabelText("auth.login.shell.emailLabel")).toBeInTheDocument();
 });

 it("successful login", async () => {
 const user = userEvent.setup();
 mockLogin.mockResolvedValue({ token: "tok", user: {/* ... */} });

 render(<LoginForm />);

 await user.type(screen.getByLabelText("..."), "admin@example.com");
 await user.type(screen.getByLabelText("..."), "password");
 await user.click(screen.getByRole("button", { name: "Submit" }));

 await waitFor(() => {
 expect(mockLogin).toHaveBeenCalledWith({
 email: "admin@example.com",
 password: "password",
 remember: false,
 });
 });
 });
});
```

## Patterns

### 1. Store mocks

```tsx
const { mockLogin, mockSetSession } = vi.hoisted(() => ({
 mockLogin: vi.fn(),
 mockSetSession: vi.fn(),
}));

vi.mock("../../../src/stores/auth", () => ({
 useAuth: () => ({
 login: mockLogin,
 setSession: mockSetSession,
 }),
 authStore: { login: mockLogin, setSession: mockSetSession },
}));
```

### 2. i18n mock (identity)

```tsx
vi.mock("../../../src/i18n", async (importOriginal) => {
 const actual = await importOriginal<typeof import("../../../src/i18n")>();
 return {
 ...actual,
 useTranslations: () => ({
 t: (key: string) => key,
 locale: "es",
 }),
 };
});
```

> If the component uses `t("auth.foo.bar")`, in the test you'll see
> `"auth.foo.bar"` in the DOM. Useful for verifying the key without
> depending on the current text.

### 3. apiFetch mock

```tsx
const { mockApiFetch } = vi.hoisted(() => ({
 mockApiFetch: vi.fn(),
}));

vi.mock("../../../src/lib/api", () => ({
 apiFetch: mockApiFetch,
}));
```

### 4. Query methods (preference order)

```tsx
// 1. Role + name
screen.getByRole("button", { name: "Submit" });

// 2. Label
screen.getByLabelText("Email");

// 3. Placeholder
screen.getByPlaceholderText("tu@correo.com");

// 4. Text
screen.getByText("Iniciar sesión");

// 5. Test ID (add data-testid to the component)
screen.getByTestId("submit-button");
```

### 5. User interactions

```tsx
const user = userEvent.setup();

await user.type(input, "text");
await user.click(button);
await user.clear(input);
await user.selectOption(select, "value");
await user.tab();
```

> ⚠️ `userEvent` uses `setup()` (not `default`).

### 6. Async testing

```tsx
await waitFor(() => {
 expect(screen.getByRole("alert")).toHaveTextContent("Error message");
});

await waitFor(() => {
 expect(mockFn).toHaveBeenCalledWith({ ... });
});
```

### 7. window.location.href (redirects)

```tsx
// Stub window.location.href so setTimeout doesn't fail
const originalLocation = window.location;
Object.defineProperty(window, "location", {
 value: { ...originalLocation, href: "" },
 writable: true,
 configurable: true,
});

try {
 // ... test
} finally {
 Object.defineProperty(window, "location", {
 value: originalLocation,
 writable: true,
 configurable: true,
 });
}
```

### 8. window.history (URL state)

```tsx
beforeEach(() => {
 window.history.replaceState({}, "", "/es/admin/auth/login");
});
```

### 9. localStorage (polyfilled in setup.ts)

The setup.ts already polyfills `localStorage` (jsdom 30 doesn't expose it in Node 22+).

```tsx
// In the test
window.localStorage.setItem("token", "abc123");
expect(window.localStorage.getItem("token")).toBe("abc123");
```

## Querying patterns

```tsx
// Single match (throws if no match)
screen.getByText("text");
screen.getByRole("button");

// Single match (returns null if no match)
screen.queryByText("text");

// Multiple matches
screen.getAllByRole("button");

// Wait for async
await screen.findByText("text");

// Exists check
expect(screen.queryByText("error")).toBeInTheDocument();
expect(screen.queryByText("error")).not.toBeInTheDocument();
```

## Assertions

```tsx
expect(element).toBeInTheDocument();
expect(element).toHaveTextContent("text");
expect(element).toHaveAttribute("type", "email");
expect(element).toHaveClass("btn-primary");
expect(element).toBeDisabled();
expect(element).toBeVisible();
expect(input).toHaveValue("text");
expect(fn).toHaveBeenCalledWith({ ... });
expect(fn).toHaveBeenCalledTimes(1);
```

## Helper per test environment

```tsx
// @vitest-environment jsdom
// (at the file start if you want to override the default)
```

By default `vitest.config.ts` already sets `environment: jsdom`. The
comment is not necessary, but it doesn't hurt.

## Trigger patterns

Activate when:

- Creating a unit test for a React component.
- Debugging a failing test.
- Mocking a store or module.
- Adding a new flow to test.
- Tests with i18n / auth / API.

## Anti-patterns

- ❌ Tests without `beforeEach` reset (mock state leaks).
- ❌ Mocking the DOM directly instead of using Testing Library.
- ❌ `getByText("exact text")` with i18n strings (when mock is `t: key => key`, it fails).
- ❌ `await userEvent` without `setup()`.
- ❌ `await screen.findByText` without `waitFor` (can be flaky).
- ❌ Tests that assume execution order.
- ❌ Over-mocking (mocking implementation, not behavior).
- ❌ `import { ... } from "vitest"` and `vitest run` - use `pnpm vitest`.
- ❌ Snapshot testing without reviewing the diff.

## File map

```
frontend/vitest.config.ts
frontend/tests/setup.ts
frontend/tests/unit/components/admin/AdminDefaultLogin.test.tsx
frontend/tests/unit/components/admin/ThemeForm.test.tsx
frontend/tests/unit/components/LoginForm.test.tsx
frontend/tests/unit/components/ForgotPasswordForm.test.tsx
frontend/tests/unit/components/ResetPasswordForm.test.tsx
```

## Related skills

- `playwright-e2e-patterns` - for E2E flows (not unit tests).
- `astro-frontend-structure` - component conventions.
- `quality-gate-pre-commit` - runs `pnpm vitest run` before commit.
- `conventional-commits-and-prs` - commit messages for tests.
