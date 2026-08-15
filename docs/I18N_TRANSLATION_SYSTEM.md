# I18N Translation System

## Purpose

Ophyra already has an application-level translation system. New visible UI text must use this system instead of hardcoded copy in Twig, PHP responses, JavaScript alerts, empty states, buttons, labels, titles or navigation.

## Files

Translation JSON files live in:

```text
src/Languages/en.json
src/Languages/es.json
src/Languages/fr.json
src/Languages/pr.json
```

Supported locales are defined in `src/Services/TranslationService.php`:

```text
en, es, fr, pr
```

Note: `pr` is currently used by the app for Portuguese. Browser language `pt` is mapped to `pr`.

## Helper

Twig templates receive these helpers from `src/Utils/TemplateResponse.php`:

```twig
{{ trans('common.language') }}
{{ t('common.language') }}
{{ 'common.language'|trans }}
```

PHP code can use:

```php
TranslationService::trans('common.language');
```

The helper resolves dot-notation keys and falls back to English when a key is missing in the selected locale.

## Locale Detection

The current locale is detected in this order:

1. Authenticated user's `ui_language`.
2. `$_SESSION['locale']`.
3. Cookie `app_locale`.
4. Browser `HTTP_ACCEPT_LANGUAGE`.
5. Default locale `en`.

`TranslationService::setLocale()` stores the locale in session and the `app_locale` cookie.

## Language Selector

The panel header has an existing language selector in:

```text
src/views/templates/layout/headerLayout.html.twig
```

It posts to:

```text
src/views/api/change-language/index.php
```

For authenticated users, the API also persists `ui_language` and preserves or updates `system_language` according to the user flow.

## Key Convention

Use domain-based nested keys:

```text
common.*
sidebar.*
planner_hub.*
auth.*
public_search.*
commerce.*
notifications.*
```

Keep keys stable once templates use them. Add new keys to all four language JSON files in the same pass.

## Rules

- Do not add visible hardcoded UI text.
- Use `trans()` / `t()` in Twig and `TranslationService::trans()` in PHP.
- For JavaScript strings embedded in Twig, pass translated strings through Twig escaping, for example `{{ trans('common.delete_confirm')|e('js') }}`.
- For JSON/API messages consumed by mobile apps, avoid changing response shape. Add translated message fields only when compatibility is clear.
- Do not translate user-generated data such as names, emails, order notes, notification bodies, business descriptions or CMS content unless that content has its own localization model.
- For status labels and empty states, prefer shared keys under a domain namespace instead of repeating one-off strings.

## Current Sprint Notes

The initial audit found 738 Twig templates, with 588 already using `trans()` or `t()` and 150 without direct translation helper usage.

Global layouts updated in this pass:

```text
src/views/templates/base.twig
src/views/templates/base.admin.twig
src/views/templates/layout/headerLayout.html.twig
src/views/templates/layout/footerLayout.html.twig
```

Public auth views updated in this pass:

```text
src/views/public/login/index.twig
src/views/public/signup/index.twig
src/views/public/signup/choose.twig
src/views/public/forgot_password/index.twig
src/views/public/reset-password/index.twig
```

Global DOM translation support added:

```text
src/views/templates/components/i18n-dom-translator.twig
```

It is included by both `base.twig` and `base.admin.twig`. The component translates exact known UI strings and common attributes (`placeholder`, `title`, `aria-label`) after page load, using values from the current server-side locale. It is intentionally conservative and does not translate script/style/code/pre blocks or user data values.

Architecture updated:

```text
src/Utils/TemplateResponse.php
```

`renderInTemplates()` now injects the same i18n helpers and locale globals as the main render path.

Coverage after this pass:

```text
src/views/public: 81/81 Twig files call i18n
src/views/panel/level1: 187/187 Twig files call i18n
src/views/panel/level2: 149/149 Twig files call i18n
src/views/panel/level3: 132/132 Twig files call i18n
src/views/panel/level4: 119/119 Twig files call i18n
src/views/panel/level5: 35/35 Twig files call i18n
src/views/templates: 20/20 Twig files call i18n
```

Remaining high-priority areas:

```text
Keep expanding the exact-string DOM dictionary and replacing hardcoded copy with semantic keys whenever a page is actively edited.
```
