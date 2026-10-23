# CoFund Filament administration

The internal panel is available at `/admin` for verified staff whose active
role includes `filament.access`.

## Current foundation

- One panel serves all organisation staff roles.
- Organisation, branch, and acting role reuse CoFund's verified session context.
- A context selector lists only active assignments available to the user.
- The top bar persistently displays the verified active organisation, branch,
  and role, and links to the context selector.
- Navigation is grouped by CoFund domain.
- Dashboard cards reuse `BuildOrganisationDashboard`.
- The branch directory is read-only, uses public UUID routes, and is restricted
  to the active organisation and selected branch.
- The organisation profile, organisation-access directory, and role-assignment
  directory are read-only and use public UUID routes.
- Branch contexts expose only people and assignments relevant to the active
  branch, including organisation-wide assignments that apply to every branch.
- Platform-administrator assignments are excluded from organisation role
  directories and cannot be created through this panel stage.
- Organisation administrators can grant active or invited organisation access
  by exact user public UUID. CoFund does not expose a platform-wide user
  directory, and email addresses are not exposed on these administration
  surfaces.
- Organisation administrators can activate invitations, suspend active access,
  reactivate suspended access, and revoke access. Suspension and revocation
  require a reason; revoked access is terminal. Every transition records the
  actor, acting role, timestamp, prior status, new status, and reason in
  append-only history. Administrators cannot change their own active access.
- Access suspension and revocation remove the user's available contexts without
  deleting role assignments, preserving role history and allowing a suspended
  access record to be reactivated safely.
- Organisation administrators can assign organisation-approved roles and
  revoke assignments with a required audit reason. These workflows delegate to
  domain actions, retain assignment history, enforce active branch context, and
  prevent the acting context from revoking itself.
- Member administration now includes tenant/branch-scoped directories,
  public-UUID detail pages, masked contact information, relationship-count
  summaries, append-only status history, and permission-gated activation,
  suspension, reinstatement, inactivity, exit, and deceased transitions. Every
  transition delegates to the existing membership domain action and records
  the actor and acting role.
- Staff with `members.create` can register pending members through a
  context-bound, responsive slide-over. The form delegates to `CreateMember`,
  derives organisation, branch, member number, actor, and initial history from
  verified context, and treats national ID input as write-only in the panel.
- Permission-gated member administration also covers profile edits, masked
  contacts and beneficiaries, group membership, private document upload and
  review, secure download, and account linking. Member detail shows read-only
  wallet balances, contribution positions, and loan summaries under their
  separate financial permissions.
- Financial-transaction tables are tenant and branch scoped.
- Notification template and delivery tables mask destinations and expose no
  provider payloads.
- Financial transactions and notification history are read-only.
- Complaint cases have scoped intake, status and assignee filtering, case detail
  and correspondence, and permission-gated assignment, resolution, closure,
  and reopening. Every mutation calls the complaint domain action.
- Scoped tenant and role context remains available throughout Livewire's
  persistent middleware lifecycle and is reset at the request boundary.

## Implementation rule

Filament resources present and orchestrate existing capabilities. Workflow and
financial actions must call the corresponding domain action, refresh their
record, and show a safe UI notification. They must never create ledger entries,
alter calculated balances, or reproduce eligibility calculations.

## Deferred surfaces

Resources requiring workflow mutation are deferred until each form can reuse
the relevant domain validation and action without duplicating API-controller
logic. Notification retry, digest, reminder, and reporting screens remain
dependent on unfinished Phase 13 services.

Personal contact details remain masked unless the owner approves an explicit
unmasked-data permission and audit policy. Further member financial drill-down
screens can follow the dedicated domain resources.

Organisation creation remains an owner-approval boundary. Branch configuration
mutations remain deferred until the owner decides whether configuration changes
require maker-checker approval.
