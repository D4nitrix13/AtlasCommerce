<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: laravel-architecture-decisions
description: When to use Controller vs Service vs Model vs Notification vs Command vs Job vs Resource vs FormRequest vs Migration in Laravel. Responsibility rules + artisan commands to create each. Triggers: creating Controller, Service, Model, Notification, Command, Job, Resource, FormRequest, Blade view.
---

# Laravel Architecture Decisions

Source: `docs/backend/laravel/componentes-arquitectura.md` (772 lines).

## Main idea

In Laravel, each file type has **one concrete responsibility**.
The base question is: **What responsibility does this code have?**

| If... | → Create |
|---|---|
| Expose an HTTP route for the frontend | **Controller** |
| Represent a database table or entity | **Model** |
| Execute reusable business logic | **Service** |
| Send notifications to the user via email or other channel | **Notification** |
| Execute a task from terminal | **Command** |
| Validate functionality automatically | **Test** |
| Transform JSON responses | **Resource** |
| Validate complex requests | **Form Request** |
| Execute heavy work in the background | **Job** |
| Create or modify tables | **Migration** |
| Insert initial or test data | **Seeder / Factory** |

## Controller - when and how

### When

Responds to an **HTTP request**. Examples:

- `GET /api/auth/2fa`
- `POST /api/auth/2fa/email/send`
- `POST /api/auth/2fa/email/verify`
- `POST /api/auth/2fa/disable`

### Responsibility

1. Receive the request.
2. Validate basic data (via FormRequest).
3. Call services.
4. Return HTTP or JSON response.

**NO** should contain heavy business logic.

### Create

```bash
php artisan make:controller Api/Auth/TwoFactorAuthController
php artisan make:controller Api/Auth/RecoveryCodeController
```

Pattern: `app/Http/Controllers/Api/{Modulo}/{Name}Controller.php`.

### Practical rule

> What endpoint does the frontend need to call?

## Model - when and how

### When

A **persistent entity** exists in the database. Examples from the 2FA module:

- `User`
- `TwoFactorSetting`
- `TwoFactorMethod`
- `OtpChallenge`
- `RecoveryCode`
- `PasskeyCredential`

### Responsibility

- One table.
- Assignable attributes.
- Casts.
- Relations.
- **Small methods** related to the record's state.

```php
// ✅ Logic allowed in model
public function isExpired(): bool
{
 return $this->expires_at->isPast();
}
```

### Create

```bash
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Auth/TwoFactorMethod
php artisan make:model Auth/OtpChallenge
```

Pattern: `app/Models/{Modulo}/{Name}.php`.

### Practical rule

> What database entity does this represent?

## Service - when and how

### When

Business logic that is **important, reusable, or complex** to live in a Controller.

It should be a service when the logic:

1. Has several business rules.
2. Is reused from multiple places.
3. Coordinates multiple models.
4. Has internal validations.
5. Handles security.
6. Handles important calculations.
7. Handles multi-step processes.
8. Would make the Controller long or hard to read.

### Examples of logic that goes in Services

**AtlasCommerce:**

- Calculate order total.
- Apply coupons.
- Validate stock.
- Process payments.
- Generate invoices.
- Update inventory.
- Calculate taxes.
- Validate order states.

**Auth:**

- Generate OTP.
- Validate OTP.
- Control attempts.
- Validate expiration.
- Generate recovery codes.
- Hash codes.
- Enable/disable 2FA.

### Create

Laravel **does NOT have** an official `make:service`. Create manually:

```bash
mkdir -p app/Services/Auth
touch app/Services/Auth/EmailOtpService.php
touch app/Services/Auth/RecoveryCodeService.php
```

Pattern: `app/Services/{Modulo}/{Name}Service.php`.

### Real case: why `EmailOtpService` should be a Service

Because it doesn't just send an email. It does:

1. Read OTP configuration.
2. Generate a secure code.
3. Hash the code.
4. Save the challenge in the database.
5. Record IP and user-agent.
6. Send the notification.
7. Validate the entered code.
8. Control expiration.
9. Control maximum attempts.
10. Mark the code as used.

→ That logic **should NOT** be in the Controller.

### Practical rule

> How is this business rule executed?

## Notification - when and how

### When

The system needs to **notify the user of something**.

Examples:

- Send OTP code.
- Notify password change.
- Send purchase confirmation.
- Notify order shipped.
- Send overdue invoice alert.
- Send security alert.

### Create

```bash
php artisan make:notification Auth/EmailOtpNotification
```

Pattern: `app/Notifications/{Modulo}/{Name}Notification.php`.

### Responsibility

1. Define the sending channel.
2. Prepare the email.
3. Define subject.
4. Render a view.
5. Send the message to the user.

### Anti-responsibility

❌ Should NOT do:

- Generate the OTP.
- Validate attempts.
- Save complex records.
- Activate 2FA.
- Contain business rules.

→ **That belongs to the Service.**

### Practical rule

> How do I notify this to the user?

## Command - when and how

### When

Task from terminal with `php artisan`.

Examples:

- Preview an email.
- Clean expired records.
- Generate reports.
- Sync data.
- Create test data.
- Recalculate statistics.

### Create

```bash
php artisan make:command MailPreviewOtpCommand --command=mail:preview-otp
```

Pattern: `app/Console/Commands/{Modulo}/{Name}Command.php`.

### Real case: `mail:preview-otp`

Sends a test OTP email to Mailpit to review the visual design. **Does
not replace tests** - it facilitates visual development without
executing the full auth flow.

### Practical rule

> What task do I want to run from terminal?

## Service vs Notification vs Command

These three are often confused. To distinguish:

- **Service** - Business logic. E.g. generate OTP, hash it, validate it.
- **Notification** - Communication to the user. E.g. send the OTP code.
- **Command** - Task from terminal. E.g. send a test OTP to Mailpit.

## Test - when and how

See `laravel-feature-testing` (sibling skill). Summary:

```bash
php artisan make:test Auth/EmailOtpTwoFactorTest
```

> How do I know this functionality didn't break?

## Migration - when and how

Create or modify database structure.

```bash
php artisan make:migration create_two_factor_settings_table \
 --path=database/migrations/auth
```

Pattern: `database/migrations/{modulo}/`.

> What structure does the database need?

## Blade View - when and how

Render HTML from Laravel (emails, invoices, emails).

```bash
mkdir -p resources/views/components/email
mkdir -p resources/views/emails/auth
touch resources/views/components/email/layout.blade.php
touch resources/views/emails/auth/email-otp.blade.php
```

> What HTML should Laravel render?

## Config - when and how

Configurable values that **should NOT** be hardcoded.

```bash
touch config/two_factor.php
touch config/brand.php
```

Examples: OTP duration, max attempts, recovery codes, logo URLs.

> What values can change per environment or project?

## Quick decision table

| What do you need? | → Create |
|---|---|
| Frontend needs to call a URL | Controller |
| This represents a table | Model |
| This creates or changes tables | Migration |
| There's complex business logic | Service |
| You need to notify the user | Notification |
| Execute from terminal | Command |
| Test that it doesn't break | Test |
| Return JSON with clean structure | Resource |
| Large input validations | Form Request |
| Reusable HTML or email | Blade View |
| Configurable values | Config |

## Senior rule

> **The Controller should not think too much.**

- Small Controllers.
- Important logic → Services.
- Notifications → communicate.
- Commands → execute from terminal.
- Models → data, relations, small rules.
- Tests → protect behavior.

## Flow applied to the 2FA module

```txt
Frontend
 ↓
Route
 ↓
TwoFactorAuthController ← receives the request
 ↓
EmailOtpService ← all business logic
 ↓
OtpChallenge / TwoFactorSetting / TwoFactorMethod ← models
 ↓
EmailOtpNotification ← sends to the user
 ↓
Mailpit or real email
```

For tests:

```txt
php artisan test --filter=EmailOtpTwoFactorTest
 ↓
Feature Tests
 ↓
API Routes
 ↓
Controllers
 ↓
Services
 ↓
Test DB
```

## Trigger patterns

Activate when:

- Creating a new route / endpoint.
- Creating Controller.
- Creating Service.
- Creating Model.
- Creating Notification.
- Creating Command.
- Creating Job.
- Creating Resource.
- Creating FormRequest.
- Creating Migration.
- Deciding where X logic lives.

## Anti-patterns

- ❌ Controller with 200 lines of business logic.
- ❌ Email logic in the Controller (should be in Notification + Service).
- ❌ Inline validation in Controller (should be in FormRequest).
- ❌ Complex queries in the Model (should be in Repository or Service).
- ❌ Notification that generates the code (should only send).
- ❌ Command with business rules (should delegate to Service).
- ❌ `php artisan make:service` - doesn't exist, create by hand.

## Related skills

- `laravel-feature-testing` - tests for each component.
- `laravel-multi-tenant-patterns` - tenant considerations in Services.
- `quality-gate-pre-commit` - Pint + PHPStan validate these conventions.
