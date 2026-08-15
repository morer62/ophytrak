# Ophyra i18n translation runbook

This document explains how the latest Ophyra translation pass was done, how the runtime translation layer works, what changed in the core, and how to reuse the same approach for Avomeal.

## Goal

The sprint goal was to close translation gaps across:

- Public views.
- Private panel level 2.
- Private panel level 4.
- Private panel level 5.
- Shared templates, layouts, partials and components.
- Legal pages.
- Public support page.
- Landing pages and linked public industry pages.

The target languages were:

- `en`
- `es`
- `pt`
- `fr`

The requirement was not to prioritize one language. Every generated or added translation key was populated in all four languages.

## Files changed

Core runtime translation layer:

- `src/views/templates/components/i18n-dom-translator.twig`

Language dictionaries:

- `src/Languages/en.json`
- `src/Languages/es.json`
- `src/Languages/pt.json`
- `src/Languages/fr.json`

Locale detection and language switching:

- `src/Services/TranslationService.php`
- `src/views/templates/components/public-language-selector.twig`
- `src/views/templates/layout/headerLayout.html.twig`
- `src/views/api/change-language/index.php`

Public support page:

- `src/views/public/support/index.twig`

Public/legal pages:

- `src/views/public/terms_and_conditions/index.twig`
- `src/views/public/privacy-policy/index.twig`
- `src/views/public/cookie-policy/index.twig`
- `src/views/public/data-processing-notice/index.twig`

Landing page translation support:

- `src/Services/OphyraLandingPageService.php`
- `src/views/public/ophyra-landing/index.twig`
- `src/views/public/planner-hub/index.twig`

QA artifact:

- `docs/i18n-phase1-qa-report.md`

## High-level approach

The translation pass used two layers:

1. Explicit source-level translations for important pages and structured content.
2. A global DOM/runtime translator for legacy views that still contain hardcoded visible text.

This hybrid approach was chosen because the repo has many legacy Twig files with hardcoded text. Converting every string manually in one sprint would be risky and slow. The runtime translator closes visible gaps safely while allowing future gradual refactors to explicit `trans()` keys.

## Language selection and default browser language

Ophyra has two related mechanisms:

- Manual language switching through UI buttons.
- Automatic default language detection from the browser request.

### Public language button

Public pages include:

```text
src/views/templates/components/public-language-selector.twig
```

This component renders the fixed public language button shown on public pages.

It uses:

```twig
supported_locales
locale
getLocaleName(loc)
```

If `supported_locales` is not provided, it falls back to:

```twig
['en', 'es', 'pt', 'fr']
```

When the user chooses a language, the component:

- Stores the selected locale in `localStorage` as `app_locale`.
- Stores the selected locale in a browser cookie named `app_locale`.
- Sends the selected locale to `/api/change-language`.
- Reloads the page so Twig and the translation service render the selected locale.

The relevant frontend persistence code is:

```javascript
localStorage.setItem('app_locale', locale);
document.cookie = 'app_locale=' + locale + '; path=/; max-age=' + (365 * 24 * 60 * 60) + '; SameSite=Lax';
```

### Private dashboard language button

Private/admin pages use the language dropdown in:

```text
src/views/templates/layout/headerLayout.html.twig
```

It follows the same pattern:

- Reads available locales from `supported_locales`.
- Shows the current `locale`.
- Persists `app_locale`.
- Calls `/api/change-language`.
- Reloads the page.

### Language change endpoint

Manual changes go through:

```text
src/views/api/change-language/index.php
```

That endpoint normalizes and validates the locale through `TranslationService`.

For authenticated users, it also updates user language preferences when available.

### Default browser language detection

Yes, Ophyra detects the browser's default language.

This does not happen through `navigator.language` in the public button. It happens server-side in:

```text
src/Services/TranslationService.php
```

The method:

```php
TranslationService::detectBrowserLocale()
```

reads:

```php
$_SERVER['HTTP_ACCEPT_LANGUAGE']
```

It parses the browser's `Accept-Language` header, respects quality values like `q=0.9`, normalizes locale variants, and only accepts supported locales.

Examples:

```text
es-US,es;q=0.9,en;q=0.8
```

resolves to:

```text
es
```

```text
pt-BR,pt;q=0.9,en;q=0.8
```

resolves to:

```text
pt
```

```text
fr-CA,fr;q=0.9,en;q=0.8
```

resolves to:

```text
fr
```

### Locale priority order

The effective locale is selected in this order:

1. Authenticated user's saved UI language.
2. `$_SESSION['locale']`.
3. Cookie `app_locale`.
4. Browser `Accept-Language` header.
5. Fallback locale `en`.

This means a logged-in user's saved language wins over the browser language.

It also means a visitor who manually changes language will keep that choice through the `app_locale` cookie.

### Locale normalization

Locales are normalized by:

```php
TranslationService::normalizeLocale()
```

The service:

- Lowercases the locale.
- Converts `_` to `-`.
- Uses the first two language characters.
- Maps legacy `pr` to `pt`.

Examples:

```text
es-US -> es
pt_BR -> pt
fr-CA -> fr
pr -> pt
```

### Supported locales

Supported locales are defined in:

```php
private const SUPPORTED_LOCALES = ['en', 'es', 'pt', 'fr'];
```

Default locale:

```php
private const DEFAULT_LOCALE = 'en';
```

If Avomeal supports a different set, update the equivalent constants and make sure every supported locale has a JSON file.

## Step-by-step process used

### 1. Identify target scopes

The audited scopes were:

```text
src/views/public
src/views/panel/level2
src/views/panel/level4
src/views/panel/level5
src/views/templates
```

These scopes cover the public surface, the main business owner/admin flows, team/staff flows, customer-facing level 5 flows, and shared UI components.

### 2. Detect visible strings

The audit looked for:

- Visible HTML text.
- `placeholder`.
- `title`.
- `aria-label`.
- `alt`.
- `default('English text')` fallback strings.
- JavaScript-visible strings used in `alert`, `confirm`, `prompt`, `textContent`, `innerText`, and `innerHTML`.

The audit intentionally ignored:

- `<script>` internals for normal DOM text extraction.
- `<style>`.
- `<textarea>`.
- `<code>`.
- `<pre>`.
- URLs.
- Twig expressions such as `{{ ... }}` and `{% ... %}`.
- Technical constants and short uppercase codes.

### 3. Create auto translation keys

For hardcoded strings that were not already mapped, the process created deterministic keys under:

```json
"auto_views": {
  "hash_here": "Translated text"
}
```

The hash is generated from the English source string:

```php
substr(sha1($text), 0, 16)
```

This makes the same source string reuse the same translation key across future runs.

Example:

```json
{
  "auto_views": {
    "9bd7b781f577634d": "Name or Email"
  }
}
```

In each language file, the same hash stores that language's translation:

```json
{
  "auto_views": {
    "9bd7b781f577634d": "Nombre o correo electrónico"
  }
}
```

### 4. Populate all languages

Every generated key was populated in:

- `en.json`
- `es.json`
- `pt.json`
- `fr.json`

English stores the source text. Spanish, Portuguese and French store translated text.

After each batch, JSON was validated with PowerShell:

```powershell
$langs = @('en','es','pt','fr')
foreach ($l in $langs) {
  Get-Content -Raw "src\Languages\$l.json" | ConvertFrom-Json | Out-Null
  Write-Output "${l}: OK"
}
```

### 5. Rebuild the global DOM translation map

The component:

```text
src/views/templates/components/i18n-dom-translator.twig
```

builds a JavaScript dictionary:

```javascript
window.OphyraI18nTextMap = {
  "Name or Email": "{{ trans('auto_views.9bd7b781f577634d')|e('js') }}"
};
```

At runtime, the browser sees the current locale translation and replaces matching hardcoded text.

### 6. Translate static DOM text

The translator walks the DOM after `DOMContentLoaded`.

It translates:

- Text nodes.
- `placeholder`.
- `title`.
- `aria-label`.
- `alt`.

It skips:

- `SCRIPT`.
- `STYLE`.
- `TEXTAREA`.
- `CODE`.
- `PRE`.

This prevents breaking code blocks, scripts, editable fields and technical snippets.

### 7. Translate JavaScript alerts and confirms

The core translator now wraps:

```javascript
window.alert
window.confirm
window.prompt
```

So calls like:

```javascript
confirm('Are you sure you want to delete this guest?')
```

are translated at runtime if that English source string exists in `window.OphyraI18nTextMap`.

### 8. Translate dynamic content inserted after page load

The translator also uses:

```javascript
MutationObserver
```

This means text inserted after page load can still be translated.

This helps with:

- Dynamic table rows.
- Modal content.
- JavaScript-rendered alerts.
- Inline validation messages.
- UI fragments appended with `innerHTML`.

### 9. Explicitly translate key public pages

Important public/legal pages were not left only to the DOM translator. They were converted to explicit translation keys.

Examples:

- Support page uses `support.*`.
- Terms page uses `legal_terms.*`.
- Privacy page uses `legal_privacy.*`.
- Cookie page uses `legal_cookie.*`.
- Data processing page uses `legal_data.*`.
- Landing pages use `ophyra_landing.*`.

This is the preferred long-term style for core pages.

### 10. Validate coverage

After the phase 2 pass, verification showed:

```text
mapped=2757
public=0
level2=0
level4=0
level5=0
shared_templates=0
alert_patch=yes
mutation_observer=yes
```

Meaning:

- `2757` mapped runtime translation entries.
- No detectable missing strings in the audited scopes.
- Alert/confirm/prompt wrapping exists.
- MutationObserver exists.

## How the core runtime translator works

The translator has four main responsibilities.

### 1. Dictionary

It exposes:

```javascript
window.OphyraI18nTextMap
```

The keys are English source strings. The values are translated strings returned by Twig's `trans()` helper.

Example:

```javascript
"Send Message": "{{ trans('auto_views.some_hash')|e('js') }}"
```

### 2. Text normalization

Before matching, both source and target text are normalized:

```javascript
value.replace(/\s+/g, ' ').trim()
```

This prevents whitespace differences from blocking a match.

### 3. Exact and suffix matching

The translator first tries an exact match.

If no exact match exists, it tries suffix matching. This helps cases where an icon, symbol or prefix exists before the text.

Example:

```text
← Back
```

can still match:

```text
Back
```

when appropriate.

### 4. DOM and mutation translation

The translator runs once on page load and then watches the page for new content.

It translates:

- Existing DOM.
- Newly added nodes.
- Changed text nodes.
- Changed visible attributes.

## Why this was done in the core

The project has many Twig views, including older areas with hardcoded text. A pure manual migration would require editing hundreds of files and would be easy to miss.

The core runtime layer provides:

- Broad coverage.
- Low risk of breaking form names, routes or business logic.
- Immediate support for public and private views.
- A bridge while high-value pages are gradually converted to explicit `trans()` calls.

## What should still be done manually over time

The runtime translator is a bridge, not the ideal final state.

For critical or frequently edited pages, prefer explicit keys:

```twig
{{ trans('billing.payment_method') }}
```

instead of relying on:

```html
Payment method
```

being translated by the DOM translator.

Recommended future manual refactor targets:

- Billing and subscription flows.
- Checkout.
- Order access.
- Store checkout/cart/success.
- Admin settings.
- Legal pages.
- Signup/login/onboarding.

## QA phases used

### Phase 1: Technical audit

Generated:

```text
docs/i18n-phase1-qa-report.md
```

The phase 1 audit found:

```text
possible_js_visible_text: 325
english_default_filter: 167
unmapped_visible_text: 108
unmapped_attribute: 7
```

This showed that most remaining risk was not normal HTML text, but JS-visible strings and Twig `default()` fallbacks.

### Phase 2: Runtime closure

The phase 2 pass added missing strings and updated the translator to cover:

- Alerts.
- Confirms.
- Prompts.
- Dynamic JavaScript content.
- Shared templates.

Final phase 2 validation:

```text
public=0
level2=0
level4=0
level5=0
shared_templates=0
```

## Editorial tone QA

After the mass translation pass, a tone review was done for obvious issues.

Examples fixed:

- `Name or Email`
  - `es`: `Nombre o correo electrónico`
  - `pt`: `Nome ou e-mail`
  - `fr`: `Nom ou e-mail`

- `← Back to Order Access`
  - `fr`: `← Retour à l’accès à la commande`

- `Change Status`
  - `pt`: `Alterar status`

Some remaining language signals were treated as false positives:

- `Status` is acceptable in Portuguese UI.
- `Client`, `Service`, `Contact`, `Message` can be valid French words.
- `Cartões` contains `Cart` as a substring but is correct Portuguese.

## How to repeat this for Avomeal

Use the same structure, but adjust scopes and language files to Avomeal's project layout.

### 1. Identify Avomeal view roots

Find the equivalent folders for:

```text
public views
private/admin views
shared templates
layouts
partials
components
```

For example:

```text
src/views/public
src/views/admin
src/views/customer
src/views/templates
```

### 2. Confirm Avomeal language files

Confirm the equivalent of:

```text
src/Languages/en.json
src/Languages/es.json
src/Languages/pt.json
src/Languages/fr.json
```

If Avomeal supports different languages, create the same `auto_views` namespace in each supported language.

### 3. Add or port the DOM translator

Avomeal needs an equivalent of:

```text
src/views/templates/components/i18n-dom-translator.twig
```

Then include it in the base layouts used by public and private pages.

For Ophyra, it is included in:

```text
src/views/templates/base.twig
src/views/templates/base.admin.twig
```

Avomeal should include it in its public and admin base layouts.

### 3.1 Add or port language switching

Avomeal should also include the language selector flow, not only the DOM translator.

Port or recreate the equivalent of:

```text
src/views/templates/components/public-language-selector.twig
src/views/templates/layout/headerLayout.html.twig
src/views/api/change-language/index.php
src/Services/TranslationService.php
```

Minimum requirements:

- A public language selector button.
- A private/admin language dropdown.
- A `/api/change-language` endpoint.
- A cookie named `app_locale` or Avomeal equivalent.
- Session locale persistence.
- Browser default detection through `Accept-Language`.
- A fallback locale.

Recommended priority order for Avomeal:

1. Authenticated user's saved UI language.
2. Session locale.
3. Locale cookie.
4. Browser `Accept-Language`.
5. Default locale.

If Avomeal has customer accounts and admin accounts, store language preference per account/user whenever possible.

### 4. Extract candidate strings

Use an extractor that scans:

- Visible text.
- `placeholder`.
- `title`.
- `aria-label`.
- `alt`.
- `default('...')`.
- `alert`, `confirm`, `prompt`.
- `textContent`, `innerText`, `innerHTML`.

Skip:

- Scripts when extracting normal DOM text.
- Styles.
- Textareas.
- Code blocks.
- URLs.
- Twig syntax.
- Technical constants.

### 5. Generate deterministic keys

Use the same key strategy:

```php
substr(sha1($text), 0, 16)
```

Store them under:

```json
"auto_views": {
  "hash": "translation"
}
```

### 6. Translate into every supported language

Populate all languages in the same run.

Do not only populate Spanish first. Every key should exist in every supported language before the phase is considered complete.

### 7. Rebuild the dictionary component

Generate:

```javascript
window.AvomealI18nTextMap = {
  "English source": "{{ trans('auto_views.hash')|e('js') }}"
}
```

Or reuse `window.OphyraI18nTextMap` if Avomeal shares the same platform code. If Avomeal is separate, use an Avomeal-specific name.

### 8. Add runtime hooks

Make sure the component supports:

- DOM text replacement.
- Visible attribute replacement.
- `alert`.
- `confirm`.
- `prompt`.
- MutationObserver.

### 9. Validate JSON

Run:

```powershell
$langs = @('en','es','pt','fr')
foreach ($l in $langs) {
  Get-Content -Raw "src\Languages\$l.json" | ConvertFrom-Json | Out-Null
  Write-Output "${l}: OK"
}
```

Adjust the language list for Avomeal if needed.

### 10. Validate coverage

Run an audit equivalent to:

```text
mapped=<count>
public=0
admin=0
shared_templates=0
alert_patch=yes
mutation_observer=yes
```

The exact scopes will depend on Avomeal.

### 11. Do editorial QA

After technical coverage is complete, run a tone pass.

Recommended Avomeal glossary examples:

- `meal plan`
- `subscription`
- `delivery`
- `checkout`
- `nutrition`
- `wellness`
- `order`
- `customer`
- `portion`
- `calories`
- `allergens`
- `ingredients`
- `pickup`
- `delivery window`

For Avomeal, pay special attention to:

- Food/nutrition terminology.
- Allergy and ingredient wording.
- Subscription and billing language.
- Delivery and fulfillment instructions.
- Health-adjacent claims. Avoid wording that sounds like medical advice unless legally reviewed.

## Recommended Avomeal implementation order

1. Add the runtime translator component.
2. Include it in public and private base layouts.
3. Extract visible strings.
4. Generate `auto_views` keys.
5. Populate all supported language files.
6. Validate JSON.
7. Validate coverage by scope.
8. Convert critical pages to explicit keys.
9. Run editorial QA.
10. Run browser QA on checkout, subscription, delivery and account flows.

## Important cautions

Do not blindly translate:

- Product names.
- Brand names.
- Legal entity names.
- Payment provider names.
- Route names.
- Form field `name` values.
- Database enum values.
- API payload keys.
- JavaScript identifiers.
- CSS class names.

The runtime translator should only translate user-visible text.

## Definition of done

For Ophyra, the translation phase was considered technically closed when:

- All target language JSON files parsed successfully.
- The global dictionary contained all detected source strings.
- Public views had zero detectable unmapped strings.
- Level 2 had zero detectable unmapped strings.
- Level 4 had zero detectable unmapped strings.
- Level 5 had zero detectable unmapped strings.
- Shared templates had zero detectable unmapped strings.
- Alert/confirm/prompt translation was active.
- MutationObserver translation was active.

For Avomeal, use the same criteria with Avomeal's actual scopes.
