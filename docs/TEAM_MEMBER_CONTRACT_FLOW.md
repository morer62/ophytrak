# Team Member Contract Flow

This document defines the current team member contract workflow for Ophyra, VNV Events and Avomeal operations.

## Business Rule

A team member cannot clock in until the business has a valid contract for that team member.

Clock-in is allowed only when the latest contract for the selected business owner has one of these statuses:

```text
SIGNED
VALIDATED
MANUALLY_UPLOADED
```

The runtime check lives in:

```text
src/Services/TeamMemberContractService.php
```

The clock-in POST checks this service before starting the time log:

```text
src/views/panel/level4/planner-hub/team/payroll/clock/index.php
```

## Data Storage

Team member contracts are stored in:

```text
team_member_contracts
```

Required schema file:

```text
db/team_member_contracts_required.sql
```

Repository:

```text
src/Repositories/TeamMemberContractsRepository.php
```

## Employee Contract Templates

Employee/team member contracts must not use order contracts or service acceptance templates.

Employee templates live in:

```text
team_member_contract_templates
db/team_member_contract_templates_required.sql
src/Repositories/TeamMemberContractTemplatesRepository.php
```

These templates are created from the same employee contract admin screen:

```text
panel/planner-hub/management/users/contracts?id={team_member_id}
```

The admin can create reusable employee templates such as:

- employee agreement;
- contractor agreement;
- staff policy acknowledgement;
- role-specific team terms.

## Admin Flow

Admin routes:

```text
src/views/panel/level1/planner-hub/management/users/contracts/index.php
src/views/panel/level2/planner-hub/management/users/contracts/index.php
```

Level 2 reuses the Level 1 implementation.

Admin entry point from the team member list:

```text
panel/planner-hub/management/users
```

Each team member row shows the latest contract status and a `Contracts` action.

### Create Employee Contract Template

The admin creates employee-only templates directly from:

```text
panel/planner-hub/management/users/contracts?id={team_member_id}
```

This writes to `team_member_contract_templates`, not `orders_contracts`.

### Assign Digital Contract

The admin selects an existing employee contract template and assigns it to the team member.

When assigned, the system creates a `team_member_contracts` row with:

```text
status = PENDING
source = digital_signature
contract_template_id
contract_snapshot_html
sign_token
sign_token_expires_at
assigned_by
```

The HTML snapshot is important: it preserves what the team member is signing even if the original template is edited later.

### Manual Upload

The admin can upload a signed PDF or image from:

```text
panel/planner-hub/management/users/contracts?id={team_member_id}
```

Manual upload sets:

```text
status = MANUALLY_UPLOADED
source = manual_upload
original_file_path
signed_file_path
generated_pdf_path
signed_pdf_hash
uploaded_at
validated_at
validated_by
```

This immediately unlocks clock-in because `MANUALLY_UPLOADED` is a valid status.

### Admin Approval

If the team member signs digitally, the contract becomes:

```text
status = SIGNED
```

The admin can then press `Validate Signed Contract`, which updates the row to:

```text
status = VALIDATED
validated_by
validated_at
```

Both `SIGNED` and `VALIDATED` currently unlock clock-in.

The admin can also reject a contract through the same controller action, setting:

```text
status = REJECTED
```

Rejected contracts do not unlock clock-in.

## Team Member Flow

Team member route:

```text
panel/planner-hub/team/contracts
src/views/panel/level4/planner-hub/team/contracts/index.php
```

The team member can:

- see the latest contract status;
- review the assigned contract snapshot;
- upload a signature image or type initials;
- accept the electronic signature consent;
- submit the signature;
- view/download the generated PDF.

When signed, the system generates a PDF through:

```text
src/Services/TeamMemberContractPdfGenerator.php
```

The signed row receives:

```text
status = SIGNED
signed_file_path
generated_pdf_path
signed_pdf_hash
signature_data
signature_hash
signed_ip
signed_user_agent
signed_by_user_id
signed_by_email
signed_at
sign_token_used_at
```

## Important Implementation Note

Team member signing is currently an authenticated panel flow, not a public token route. The admin assignment creates a `sign_token`, but the team member route verifies access by logged-in user, owner/business context and `team_member_id`.

Do not create a separate signature engine for team members. This flow intentionally reuses the existing contract template and PDF/audit model described in:

```text
docs/CONTRACT_SIGNATURE_ENGINE.md
docs/CONTRACT_SECURITY_MODEL.md
```

## QA Checklist

1. Apply `db/team_member_contract_templates_required.sql`.
2. Create or confirm an employee contract template in `panel/planner-hub/management/users/contracts?id=TEAM_MEMBER_ID`.
3. Open `panel/planner-hub/management/users`.
4. Choose a team member and click `Contracts`.
5. Assign a digital employee contract.
6. Log in as that team member and open `panel/planner-hub/team/contracts`.
7. Sign with initials or signature image and accept electronic signature consent.
8. Confirm the contract status becomes `SIGNED` and PDF/hash fields are stored.
9. Attempt clock-in from `panel/planner-hub/team/payroll/clock`.
10. Confirm clock-in is allowed.
11. Test manual upload from admin and confirm `MANUALLY_UPLOADED` also allows clock-in.
12. Test rejected/missing contract and confirm clock-in is blocked.
