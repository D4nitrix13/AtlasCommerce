import { useState, type CSSProperties, type ReactElement } from "react";
import { Eye, EyeOff } from "lucide-react";
import { useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";
import { apiFetch } from "../../../lib/api";
import type { LoginResponse, ValidationErrors } from "../../../types/auth";
import { DEFAULT_BRAND, type StoreBrand } from "../../../config/stores";
import LanguageSwitcher from "../../ui/LanguageSwitcher";

export type Tab = "login" | "register";

type LoginFormProps = {
  brand?: StoreBrand;
  initialTab?: Tab;
};

function readTabFromUrl(): Tab {
  if (typeof window === "undefined") {
    return "login";
  }
  const params = new URLSearchParams(window.location.search);
  return params.get("tab") === "register" ? "register" : "login";
}

function writeTabToUrl(next: Tab): void {
  if (typeof window === "undefined") {
    return;
  }
  const url = new URL(window.location.href);
  if (next === "login") {
    url.searchParams.delete("tab");
  } else {
    url.searchParams.set("tab", next);
  }
  window.history.replaceState({}, "", url);
}

export default function LoginForm({
  brand = DEFAULT_BRAND,
  initialTab,
}: LoginFormProps): ReactElement {
  const { login, setSession } = useAuth();
  const { t } = useTranslations();

  const accent = brand.accentColor;
  const accentHover = brand.accentHoverColor;
  const palette = brand.loginTheme;
  const themeVars = {
    "--login-bg": palette.bg,
    "--login-surface": palette.surface,
    "--login-surface-hover": palette.surfaceHover,
    "--login-border": palette.border,
    "--login-border-strong": palette.borderStrong,
    "--login-text": palette.text,
    "--login-text-muted": palette.textMuted,
    "--login-text-subtle": palette.textSubtle,
    "--login-input-bg": palette.inputBg,
    "--login-input-bg-focus": palette.inputBgFocus,
    "--login-danger": palette.danger,
    "--login-danger-text": palette.dangerText,
    "--login-danger-bg": palette.dangerBg,
    "--login-divider": palette.divider,
  } as CSSProperties;

  const [tab, setTab] = useState<Tab>(initialTab ?? readTabFromUrl());
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [marketingOptIn, setMarketingOptIn] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});

  const resetFields = (): void => {
    setFirstName("");
    setLastName("");
    setEmail("");
    setPassword("");
    setMarketingOptIn(false);
    setFieldErrors({});
    setErrorMessage(null);
  };

  const handleTabChange = (next: Tab): void => {
    if (next === tab) {
      return;
    }
    setTab(next);
    writeTabToUrl(next);
    resetFields();
  };

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    setFieldErrors({});
    setIsSubmitting(true);

    try {
      let response: LoginResponse;

      if (tab === "login") {
        response = await login({ email, password });
      } else {
        response = await apiFetch<LoginResponse>("/auth/register", {
          method: "POST",
          body: {
            first_name: firstName,
            last_name: lastName,
            email,
            password,
            device_name: "web-admin",
          },
        });
        setSession(response);
      }

      const params = new URLSearchParams(window.location.search);
      // Mantenerse en el mismo subdominio admin. La URL del dashboard
      // se construye SIN locale prefix cuando el usuario está en admin.
      const fallback = `${window.location.origin}/dashboard`;
      const target = params.get("redirect") ?? fallback;

      window.location.href = target;
    } catch (error) {
      const apiError = error as {
        status?: number;
        message?: string;
        errors?: ValidationErrors;
      };

      if (apiError.errors !== null && apiError.errors !== undefined) {
        setFieldErrors(apiError.errors);
      }

      setErrorMessage(
        apiError.message ?? t("auth.login.error.invalid"),
      );
      setIsSubmitting(false);
    }
  };

  const isLogin = tab === "login";
  const isRegister = tab === "register";

  const emailErrorKey = fieldErrors.email?.[0];
  const passwordErrorKey = fieldErrors.password?.[0];

  return (
    <div
      className="flex w-full max-w-3xl flex-col gap-5 text-[var(--login-text)]"
      style={themeVars}
    >
      <header className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <img
            src={brand.logoPath}
            alt={brand.name}
            className="h-12 w-12 rounded-md bg-white object-contain p-1"
          />
          <span className="text-2xl font-bold italic tracking-tight">
            {t("auth.brand.name")}
          </span>
        </div>
        <LanguageSwitcher
          theme={{
            surface: palette.surface,
            text: palette.text,
            activeText: accent,
            border: palette.border,
          }}
        />
      </header>

      <h1 className="text-sm font-normal text-[var(--login-text-muted)]">
        {tab === "login"
          ? t("auth.login.subtitle")
          : t("auth.login.createAccount.heading")}
      </h1>

      <div
        role="tablist"
        className="relative grid grid-cols-2 items-center overflow-hidden rounded-full border text-base font-semibold tracking-wide"
        style={{ borderColor: accent }}
      >
        <span
          aria-hidden="true"
          className="pointer-events-none absolute top-0 bottom-0 w-1/2 rounded-full"
          style={{
            backgroundColor: accent,
            transform: tab === "register" ? "translateX(100%)" : "translateX(0%)",
          }}
        />
        <button
          type="button"
          role="tab"
          aria-selected={tab === "login"}
          onClick={() => {
            handleTabChange("login");
          }}
          className="relative z-10 px-4 py-3 focus:outline-none"
          style={{ color: tab === "login" ? "#fff" : accent }}
        >
          {t("auth.login.tab.signin")}
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={tab === "register"}
          onClick={() => {
            handleTabChange("register");
          }}
          className="relative z-10 px-4 py-3 focus:outline-none"
          style={{ color: tab === "register" ? "#fff" : accent }}
        >
          {t("auth.login.tab.signup")}
        </button>
      </div>

      <form
        onSubmit={handleSubmit}
        className="flex flex-col gap-4"
        noValidate
        aria-label={t("auth.login.title")}
      >
        {errorMessage !== null && (
          <div
            role="alert"
            className="rounded-md border px-3 py-2 text-sm"
            style={{
              borderColor: "var(--login-danger)",
              backgroundColor: "var(--login-danger-bg)",
              color: "var(--login-danger-text)",
            }}
          >
            {errorMessage}
          </div>
        )}

        {!isLogin && (
          <div className="grid grid-cols-2 gap-3">
            <div className="flex flex-col gap-1">
              <input
                id="first_name"
                name="first_name"
                type="text"
                autoComplete="given-name"
                required
                placeholder={t("auth.login.firstName.placeholder")}
                value={firstName}
                onChange={(event) => {
                  setFirstName(event.target.value);
                }}
                className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-4 text-base text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
                style={{ borderColor: "var(--login-border)" }}
                onFocus={(event) => {
                  event.currentTarget.style.borderColor =
                    "var(--login-border-strong)";
                }}
                onBlur={(event) => {
                  event.currentTarget.style.borderColor = "var(--login-border)";
                }}
              />
              {fieldErrors.first_name?.[0] !== undefined && (
                <p className="px-1 text-xs text-[var(--login-danger-text)]">
                  {fieldErrors.first_name[0]}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-1">
              <input
                id="last_name"
                name="last_name"
                type="text"
                autoComplete="family-name"
                required
                placeholder={t("auth.login.lastName.placeholder")}
                value={lastName}
                onChange={(event) => {
                  setLastName(event.target.value);
                }}
                className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-4 text-base text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
                style={{ borderColor: "var(--login-border)" }}
                onFocus={(event) => {
                  event.currentTarget.style.borderColor =
                    "var(--login-border-strong)";
                }}
                onBlur={(event) => {
                  event.currentTarget.style.borderColor = "var(--login-border)";
                }}
              />
              {fieldErrors.last_name?.[0] !== undefined && (
                <p className="px-1 text-xs text-[var(--login-danger-text)]">
                  {fieldErrors.last_name[0]}
                </p>
              )}
            </div>
          </div>
        )}

        <div className="flex flex-col gap-1">
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            placeholder={t("auth.login.email.placeholder")}
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
            className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-4 text-base text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
            style={{ borderColor: "var(--login-border)" }}
            onFocus={(event) => {
              event.currentTarget.style.borderColor =
                "var(--login-border-strong)";
            }}
            onBlur={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border)";
            }}
          />
          {emailErrorKey !== undefined && (
            <p className="px-1 text-xs text-[var(--login-danger-text)]">
              {emailErrorKey}
            </p>
          )}
        </div>

        <div className="flex flex-col gap-1">
          <div className="relative">
            <input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              required
              minLength={12}
              placeholder={t("auth.login.password.placeholder")}
              value={password}
              onChange={(event) => {
                setPassword(event.target.value);
              }}
              className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-4 pr-12 text-base text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
              style={{ borderColor: "var(--login-border)" }}
              onFocus={(event) => {
                event.currentTarget.style.borderColor =
                  "var(--login-border-strong)";
              }}
              onBlur={(event) => {
                event.currentTarget.style.borderColor = "var(--login-border)";
              }}
            />
            <button
              type="button"
              onClick={() => {
                setShowPassword(!showPassword);
              }}
              aria-label={
                showPassword
                  ? t("auth.login.password.toggle.hide")
                  : t("auth.login.password.toggle.show")
              }
              className="absolute inset-y-0 right-0 flex items-center px-4 text-[var(--login-text-muted)] transition hover:text-[var(--login-text)]"
            >
              {showPassword ? (
                <EyeOff size={20} strokeWidth={1.75} />
              ) : (
                <Eye size={20} strokeWidth={1.75} />
              )}
            </button>
          </div>
          {passwordErrorKey !== undefined && (
            <p className="px-1 text-xs text-[var(--login-danger-text)]">
              {passwordErrorKey}
            </p>
          )}
          {isRegister && (
            <p className="mt-1 px-1 text-xs leading-relaxed text-[var(--login-text-subtle)]">
              {t("auth.login.passwordHint")}
            </p>
          )}
        </div>

        {isRegister && (
          <label className="mt-1 flex cursor-pointer items-start gap-3 text-xs leading-relaxed text-[var(--login-text-muted)]">
            <span className="relative mt-0.5 inline-block shrink-0">
              <input
                type="checkbox"
                checked={marketingOptIn}
                onChange={(event) => {
                  setMarketingOptIn(event.target.checked);
                }}
                className="peer sr-only"
              />
              <span
                aria-hidden="true"
                className="block h-5 w-9 rounded-full border transition peer-checked:border-[var(--login-danger)] peer-checked:bg-[var(--login-danger)]"
                style={{
                  borderColor: "var(--login-border)",
                  backgroundColor: "var(--login-surface)",
                }}
              />
              <span
                aria-hidden="true"
                className="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-[var(--login-text)] shadow transition peer-checked:translate-x-4"
              />
            </span>
            <span>{t("auth.login.marketing.optIn.label")}</span>
          </label>
        )}

        <button
          type="submit"
          disabled={isSubmitting}
          className="mt-1 rounded-md px-4 py-4 text-lg font-semibold tracking-wide text-white transition disabled:cursor-not-allowed disabled:opacity-60"
          style={{
            backgroundColor: isSubmitting ? accentHover : accent,
          }}
          onMouseEnter={(event) => {
            event.currentTarget.style.backgroundColor = accentHover;
          }}
          onMouseLeave={(event) => {
            event.currentTarget.style.backgroundColor = accent;
          }}
        >
          {isSubmitting
            ? t("auth.login.submit.loading")
            : isLogin
              ? t("auth.login.submit.idle")
              : t("auth.login.submit.create")}
        </button>
      </form>

      <div className="flex items-center gap-3 py-1">
        <div className="h-px flex-1 bg-[var(--login-divider)]" />
        <span className="text-xs text-[var(--login-text-muted)]">
          {t("auth.login.divider")}
        </span>
        <div className="h-px flex-1 bg-[var(--login-divider)]" />
      </div>

      <div className="flex flex-col gap-3">
        <SocialButton provider="apple">{t("auth.login.social.apple")}</SocialButton>
        <SocialButton provider="facebook">{t("auth.login.social.facebook")}</SocialButton>
        <SocialButton provider="google">{t("auth.login.social.google")}</SocialButton>
      </div>

      <div className="mt-2 flex flex-col items-center gap-3 pt-2 text-xs text-[var(--login-text-muted)]">
        <a
          href={`mailto:${brand.supportEmail}`}
          className="font-semibold transition hover:opacity-80"
          style={{ color: accent }}
        >
          {t("auth.login.help")}
        </a>
        <p className="text-center">
          {t("auth.login.termsPrefix")}{" "}
          <a
            href="#"
            className="underline transition"
            style={{ color: "var(--login-text)" }}
          >
            {t("auth.login.termsLink")}
          </a>
          .
        </p>
      </div>
    </div>
  );
}

type Provider = "apple" | "facebook" | "google";

function SocialButton({
  provider,
  children,
}: {
  provider: Provider;
  children: string;
}): ReactElement {
  return (
    <button
      type="button"
      data-provider={provider}
      className="flex w-full items-center gap-5 rounded-xl bg-[var(--login-surface)] px-6 py-3 text-base font-medium text-[var(--login-text)] transition hover:bg-[var(--login-surface-hover)] focus:outline-none focus:ring-2 focus:ring-[var(--login-border-strong)]"
    >
      <span className="flex w-10 shrink-0 items-center justify-center">
        <SocialIcon provider={provider} />
      </span>
      <span className="flex-1 text-center">{children}</span>
    </button>
  );
}

function SocialIcon({ provider }: { provider: Provider }): ReactElement {
  if (provider === "apple") {
    return (
      <svg
        width="36"
        height="36"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
      >
        <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z" />
      </svg>
    );
  }

  if (provider === "facebook") {
    return (
      <svg
        width="36"
        height="36"
        viewBox="0 0 24 24"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
      >
        <path
          d="M9.19795 21.5H13.198V13.4901H16.8021L17.198 9.50977H13.198V7.5C13.198 6.94772 13.6457 6.5 14.198 6.5H17.198V2.5H14.198C11.4365 2.5 9.19795 4.73858 9.19795 7.5V9.50977H7.19795L6.80206 13.4901H9.19795V21.5Z"
          fill="currentColor"
        />
      </svg>
    );
  }

  return (
    <svg
      width="36"
      height="36"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
    >
      <path
        d="M6 12C6 15.3137 8.68629 18 12 18C14.6124 18 16.8349 16.3304 17.6586 14H12V10H21.8047V14H21.8C20.8734 18.5645 16.8379 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C15.445 2 18.4831 3.742 20.2815 6.39318L17.0039 8.68815C15.9296 7.06812 14.0895 6 12 6C8.68629 6 6 8.68629 6 12Z"
        fill="currentColor"
      />
    </svg>
  );
}