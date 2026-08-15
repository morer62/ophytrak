# I18n QA Phase 1 Report

Generated technical audit for public views, private level 2, level 4, level 5, and shared templates.

## Summary

- Dictionary entries available to the DOM translator: `2245`
- Technical findings: `607`
- Translation language signals for manual review: `19`

## Scope stats

| Scope | Files | Unmapped visible/attributes | English defaults | Possible JS visible text |
| --- | ---: | ---: | ---: | ---: |
| `public` | 84 | 3 | 9 | 97 |
| `level2` | 153 | 1 | 94 | 103 |
| `level4` | 121 | 0 | 46 | 94 |
| `level5` | 35 | 2 | 3 | 30 |
| `shared_templates` | 31 | 109 | 15 | 1 |

## Findings by type

- `possible_js_visible_text`: 325
- `english_default_filter`: 167
- `unmapped_visible_text`: 108
- `unmapped_attribute`: 7

## Highest-impact files

- `C:\xampp\htdocs\ophyra/src/views/templates\components\business-profile-builder.twig`: 52
- `C:\xampp\htdocs\ophyra/src/views/templates\layout\sidebars\1.twig`: 34
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\settings\payment-providers\index.twig`: 27
- `C:\xampp\htdocs\ophyra/src/views/panel/level4\planner-hub\settings\payment-providers\index.twig`: 27
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig`: 24
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\team_comunication\menu\index.twig`: 22
- `C:\xampp\htdocs\ophyra/src/views/panel/level4\planner-hub\management\orders\orders\team_comunication\menu\index.twig`: 22
- `C:\xampp\htdocs\ophyra/src/views/public\search\venue\details\index.twig`: 21
- `C:\xampp\htdocs\ophyra/src/views/templates\layout\sidebars\mobile.twig`: 20
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig`: 16
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig`: 12
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\users\create\index.twig`: 12
- `C:\xampp\htdocs\ophyra/src/views/panel/level4\planner-hub\management\orders\orders\closure-report\index.twig`: 12
- `C:\xampp\htdocs\ophyra/src/views/panel/level4\planner-hub\management\users\create\index.twig`: 12
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig`: 9
- `C:\xampp\htdocs\ophyra/src/views/panel/level5\store\nutrition-advisor\index.twig`: 8
- `C:\xampp\htdocs\ophyra/src/views/public\commerce\order-access\index.twig`: 7
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\previous\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/panel/level4\event-invitations\guests\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/panel/level4\planner-hub\management\orders\orders\previous\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/panel/level5\event-invitations\guests\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/public\commerce\meal-plans\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/public\commerce\order-access\success\index.twig`: 6
- `C:\xampp\htdocs\ophyra/src/views/public\order-access\index.twig`: 6

## First 80 findings

- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\afiliate-hub\index.twig:340 - Copied
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\create\index.twig:356 - <span class=
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig:34 - success
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig:456 - Please select at least one guest
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig:460 - Send invitations to ${selected.length} guest(s)?
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig:484 - Are you sure you want to delete this guest?
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig:520 - Guest not found
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\guests\index.twig:658 - <i class=
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\index.twig:20 - success
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\manage\index.twig:21 - success
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\manage\index.twig:410 - Delete functionality coming soon!
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\manage\index.twig:426 - ¿Estás seguro de que deseas eliminar la imagen de portada?
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\event-invitations\manage\index.twig:428 - <i class=
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\events\tickets\index.twig:282 - No description
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\events\tickets\index.twig:302 - Are you sure you want to delete this ticket type?
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\events\tickets\index.twig:361 - No description
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\events\tickets\index.twig:390 - Are you sure you want to delete this sales stage?
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\events\tickets\index.twig:869 - <div class=
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:7 - Billing & Modules
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:8 - Base Profile & Paid Modules
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:9 - Base Profile is free. Paid modules unlock only after confirmed payment or approved manual activation.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:75 - Payment method
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:75 - card ending in
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:131 - Paid Modules
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:183 - Active
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:185 - Pending payment
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:187 - Past due
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:189 - Locked
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:213 - Due today
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:227 - Manage
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:231 - Continue payment
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:233 - Review
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:266 - Coming Soon
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\manage\index.twig:282 - Renewal Date
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:7 - Module checkout
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:9 - Review the module, billing currency and renewal details before payment.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:11 - Back
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:21 - This module unlocks only after confirmed payment.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:23 - Pending until paid
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:26 - What unlocks
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:34 - You will be charged monthly until cancelled.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:41 - Payment currency
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:45 - Select currency
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:53 - Update quote
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:58 - No active exchange rate is available for that currency, so checkout safely falls back to USD.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:68 - Review summary
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:72 - Amount due today
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:76 - Charged currency
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:80 - Frequency
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:84 - Next renewal
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:85 - Not available
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:91 - Payment method
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:92 - Card
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:92 - ending in
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:100 - Confirm and pay
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:103 - Add a payment method before confirming payment.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:104 - Add payment method
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\review\index.twig:111 - Cancel / Back to Marketplace
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:8 - Payment confirmed
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:9 - module activated
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:10 - Your module is active and ready to use in your business workspace.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:14 - Amount charged
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:18 - Payment date
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:22 - Next renewal
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:26 - Module status
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:32 - Go to workspace
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\membership\modules\success\index.twig:33 - Back to Marketplace
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\chatia\index.twig:87 - No se pudo cargar el consumo.
- `possible_js_visible_text` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\commissions\pending\details\index.twig:250 - Payment processing error. Please try again.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\crm\lead\edit\index.twig:42 - english
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:88 - Fotos del equipo
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:93 - Agregar fotos
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:98 - Fotos subidas por el equipo en órdenes aceptadas.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:105 - Unknown
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:115 - No hay fotos subidas aún.
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:117 - Subir fotos
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:129 - Fotos del equipo
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:138 - Subir fotos
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:140 - Formatos: JPEG, PNG, GIF, WebP
- `english_default_filter` C:\xampp\htdocs\ophyra/src/views/panel/level2\planner-hub\management\orders\orders\closure-report\index.twig:150 - Unknown

## Language signal review

These are not all defects. Some are valid words in the target language, such as `Status` in Portuguese or `Client` in French.

- `pt` `d08bf1e9905b43ab` [Status] source: "Current Status" value: "Status atual"
- `pt` `159438236a61a848` [Status] source: "Invoice Status" value: "Status da fatura"
- `pt` `245bead13809b2b9` [Status] source: "🪪 Membership Status" value: "🪪 Status da assinatura"
- `pt` `d5c35999c8f928db` [Cart] source: "Go to My Cards" value: "Vá para Meus Cartões"
- `pt` `1531d463f69d937d` [Add] source: "Add-on for store operations, delivery, fulfillment and tracking." value: "Add-on para operações de loja, entrega, atendimento e rastreamento."
- `pt` `2a2fac0ae410164a` [Cart] source: "Saved cards" value: "Cartões salvos"
- `pt` `4bc1d395acca610c` [Status] source: "External status" value: "Status externo"
- `pt` `d3f29ba9e2612545` [Status] source: "Ophyra status" value: "Status da Ophyra"
- `pt` `584e977886e7b1e6` [Status] source: "Payment Status" value: "Status do pagamento"
- `pt` `a15b0b10d14c2e81` [Status] source: "Order Status" value: "Status do pedido"
- `pt` `9dfea4047acfeeca` [Status] source: "Payment status" value: "Status do pagamento"
- `pt` `22edd6c1ad2ba007` [Status] source: "Order status" value: "Status do pedido"
- `fr` `0e85749a6f40d461` [Client] source: "Customer" value: "Client"
- `fr` `f3079fcd5b9bd77e` [Message] source: "Custom Welcome Message" value: "Message de bienvenue personnalisé"
- `fr` `1bdd79b12628d8c4` [Client] source: "Client" value: "Client"
- `fr` `ec08641d50b462ef` [Client] source: "👤 Client" value: "👤 Client"
- `fr` `329cb8b6ba8c427b` [Service] source: "Service" value: "Service"
- `fr` `595fd70bde412541` [Client] source: "Active customer" value: "Client actif"
- `fr` `d3a10ec5df2d49e3` [Contact] source: "Step 2: Contact and Social Links" value: "Étape 2 : Contact et liens sociaux"

## Recommended next action

Prioritize converting `english_default_filter` and `possible_js_visible_text` findings in critical flows to explicit translation keys. The DOM translator covers rendered static text, but server-side defaults and JavaScript messages should be translated at source for reliability.
