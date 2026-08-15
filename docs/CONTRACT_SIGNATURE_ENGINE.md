# Contract Signature Engine

## Current Client Contract Flow

Ophyra already has an internal contract signing engine for client orders. Do not replace it with a parallel system.

Main public routes:

```text
src/views/public/order-access/index.php
src/views/public/order-access/suborder/index.php
src/views/public/order-access/accept-receipt/index.php
src/views/public/commerce/order-access/index.php
src/views/public/commerce/order-access/suborder/index.php
```

Main services:

```text
src/Services/ContractPdfGenerator.php
src/Services/OrderAcceptancePdfGenerator.php
src/Services/DocuSignService.php
```

Main repositories and tables:

```text
OrdersContractRepository                  -> orders_contracts
OrdersAcceptanceContractsRepository       -> orders_acceptance_contracts
OrdersAcceptanceContractTemplateRepository -> orders_acceptance_contract_template
DocumentsLogsRepository                   -> document_logs
```

## How Signing Works

Panel order creation/status pages generate a public token with:

```text
order_id or suborder_id
user_id
exp
hash
```

The hash is an HMAC SHA-256 generated with `VNV_SECRET_KEY`. The token format is preserved for existing links.

The signer opens `/order-access?token=...` or `/order-access/suborder?token=...`, reviews the assigned contract, provides either a signature image or typed initials, confirms electronic signature consent, and submits the form.

The system then:

- validates token payload, HMAC and expiry;
- blocks duplicate signing if `document_logs` already has `doc_type = contract_signed`;
- generates a signed PDF through `ContractPdfGenerator`;
- uploads the PDF through `FileUtils::saveFileFromContent`;
- stores the resulting file URL and SHA-256 PDF hash in `document_logs`;
- records IP, user agent, document id and signature method in audit metadata;
- moves the order workflow to `INVOICE_READY`;
- sends owner/client notifications.

Receipt acceptance uses `OrderAcceptancePdfGenerator` and stores the result in `orders_acceptance_contracts`.

## PDF Storage

Signed PDFs are uploaded through the existing file utility, currently backed by Cloudinary. Existing contracts and URLs must remain compatible.

Important security note: if Cloudinary assets are public, signed contracts should eventually move behind a protected download route or a private/authenticated Cloudinary delivery model.

## Compatibility Rules

- Do not change the existing token JSON shape.
- Do not invalidate already generated links except when expired or already signed.
- Do not overwrite existing signed PDFs.
- Do not remove `document_logs` records.
- Do not change existing `doc_type` values without a backfill plan.
- Do not create a separate contract engine for team members; extend this model.

## Current Hardening

The current sprint added compatible improvements:

- POST signing now validates token structure, HMAC and expiry.
- HMAC comparisons use `hash_equals`.
- Client and suborder contracts cannot be signed twice through the same order record.
- Server-side electronic signature consent is required.
- Commerce mirror routes were aligned with the current PDF generator return shape.
- Commerce mirror routes now store the generated PDF hash instead of attempting to hash a remote URL.
- Receipt acceptance blocks duplicate acceptance records.

## Team Member Contract Flow

The operational team member flow now uses the same signing/audit pattern:

Admin routes:

```text
src/views/panel/level1/planner-hub/management/users/contracts/index.php
src/views/panel/level2/planner-hub/management/users/contracts/index.php
```

Team member route:

```text
src/views/panel/level4/planner-hub/team/contracts/index.php
```

Supporting code:

```text
src/Repositories/TeamMemberContractsRepository.php
src/Services/TeamMemberContractService.php
src/Services/TeamMemberContractPdfGenerator.php
```

Admin can:

- create/select an employee-only template from `team_member_contract_templates`;
- assign it to a team member;
- store a snapshot in `team_member_contracts.contract_snapshot_html`;
- upload a manually signed PDF;
- validate or reject a signed contract;
- view/download the stored PDF URL.

Team member can:

- open `panel/planner-hub/team/contracts`;
- see pending/current contract status;
- sign electronically with uploaded signature image or typed initials;
- confirm electronic signature consent;
- view/download the signed or manually uploaded PDF.

Clock-in is blocked by `TeamMemberContractService::isClockInAllowed()` unless the latest scoped contract is:

```text
SIGNED
VALIDATED
MANUALLY_UPLOADED
```

## Known Gaps

- Tokens are stateless; they do not yet have `used_at` or persisted status.
- Signature image upload is not embedded into the main client contract PDF; receipt acceptance can embed the image.
- There is no first-class signature hash field for main client contracts.
- Contract snapshot/versioning is implicit in the generated PDF, but not stored as queryable structured data.
- Signed PDF access depends on the storage URL security model.
- Team member contract PDFs still use storage URLs directly; protected download routes are recommended for a future hardening pass.
