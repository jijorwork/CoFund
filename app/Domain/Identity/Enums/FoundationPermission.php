<?php

namespace App\Domain\Identity\Enums;

enum FoundationPermission: string
{
    case OrganisationsView = 'organisations.view';
    case OrganisationsUpdate = 'organisations.update';
    case BranchesView = 'branches.view';
    case BranchesCreate = 'branches.create';
    case BranchesUpdate = 'branches.update';
    case OrganisationUsersView = 'organisation_users.view';
    case OrganisationUsersManage = 'organisation_users.manage';
    case RolesView = 'roles.view';
    case RolesAssign = 'roles.assign';
    case RolesRevoke = 'roles.revoke';
    case MembersView = 'members.view';
    case MembersCreate = 'members.create';
    case MembersUpdate = 'members.update';
    case MembersSuspend = 'members.suspend';
    case MembersViewDocuments = 'members.view_documents';
    case MembersActivate = 'members.activate';
    case MembersExit = 'members.exit';
    case MembersLinkUser = 'members.link_user';
    case MembersManageContacts = 'members.manage_contacts';
    case MembersVerifyContacts = 'members.verify_contacts';
    case MembersUploadDocuments = 'members.upload_documents';
    case MembersVerifyDocuments = 'members.verify_documents';
    case GroupsView = 'groups.view';
    case GroupsCreate = 'groups.create';
    case GroupsUpdate = 'groups.update';
    case GroupsManageMembers = 'groups.manage_members';
    case ContributionPlansView = 'contribution_plans.view';
    case ContributionPlansCreate = 'contribution_plans.create';
    case ContributionPlansUpdate = 'contribution_plans.update';
    case ContributionPlansActivate = 'contribution_plans.activate';
    case ContributionPlansSuspend = 'contribution_plans.suspend';
    case ContributionPlansClose = 'contribution_plans.close';
    case ContributionEnrolmentsView = 'contribution_enrolments.view';
    case ContributionEnrolmentsCreate = 'contribution_enrolments.create';
    case ContributionEnrolmentsApprove = 'contribution_enrolments.approve';
    case ContributionEnrolmentsSuspend = 'contribution_enrolments.suspend';
    case ContributionEnrolmentsEnd = 'contribution_enrolments.end';
    case ContributionSchedulesView = 'contribution_schedules.view';
    case ContributionSchedulesGenerate = 'contribution_schedules.generate';
}
