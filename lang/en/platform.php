<?php

declare(strict_types=1);

/*
 * The platform area (SRS 3.1, 5, 5.4, 40).
 *
 * Read by the handful of people who run the business rather than by customers.
 * The tone is plainer than the rest of the product for that reason — and the
 * warnings are blunter, because everything here reaches inside somebody else's
 * company.
 */

return [
    'platform' => 'Platform',
    'tenants' => 'Customers',
    'tenants_intro' => 'Every company on this installation, their contracts and their size.',
    'no_data_access_note' => 'Counts and contracts only. Seeing a customer\'s machines, work orders or breakdowns requires an audited support grant, and the customer is told when one is opened.',

    'company' => 'Company',
    'company_name' => 'Company name',
    'company_code' => 'Code',
    'company_code_hint' => 'Appears inside every work order and breakdown number this customer will ever issue. Short, and hard to change afterwards.',
    'legal_name' => 'Legal name',
    'currency' => 'Currency',
    'timezone' => 'Timezone',
    'locale' => 'Language',
    'status' => 'Status',
    'company_active' => 'Active',
    'company_suspended' => 'Suspended',
    'factories' => 'Factories',
    'assets' => 'Machines',
    'users' => 'Users',
    'no_tenants' => 'No customers yet',
    'no_tenants_hint' => 'Create the first one to get started.',

    // -- Onboarding -------------------------------------------------------
    'new_tenant' => 'New customer',
    'create_tenant' => 'Create customer',
    'new_tenant_intro' => 'Creates the company, its first factory and its owner account together. All three are needed before anybody can sign in and do anything.',
    'first_factory' => 'First factory',
    'first_factory_hint' => 'Machines live in a factory, document numbers are issued per factory, and the working calendar hangs off it. A company without one cannot be used. More can be added by the customer.',
    'factory_name' => 'Factory name',
    'factory_code' => 'Code',
    'owner_account' => 'Owner account',
    'owner_hint' => 'The first person who can sign in. They get the Company Owner role, company-wide, and can invite everybody else.',
    'owner_name' => 'Name',
    'owner_email' => 'Email',
    'password' => 'Password',
    'tenant_created' => ':name has been created.',
    'owner_credentials_once' => 'Give these to the customer now. The password is shown once and cannot be retrieved again.',
    'code_taken' => 'That code is already in use, including by a deleted company.',
    'owner_email_taken' => 'An account with this email already exists. Adding an existing person to a new company is a different operation — do it from the company\'s own user screen after a support grant.',

    // -- Contract (SRS 40) ------------------------------------------------
    'contract' => 'Contract',
    'contract_number' => 'Number',
    'no_contract' => 'No contract',
    'no_contract_yet' => 'No contract has been recorded for this customer yet.',
    'new_contract' => 'New contract',
    'new_contract_hint' => 'Saving supersedes the current contract rather than editing it: an invoice already raised under the old terms has been sent to somebody, and changing what it was calculated from makes it unexplainable.',
    'save_contract' => 'Save contract',
    'contract_saved' => 'Contract saved. The previous one has been archived.',
    'term' => 'Term',
    'start_date' => 'Starts',
    'end_date' => 'Ends',
    'cycle' => 'Billing cycle',
    'cycle_monthly' => 'Monthly',
    'cycle_quarterly' => 'Quarterly',
    'cycle_yearly' => 'Yearly',
    'amount' => 'Amount',
    'trial_end' => 'Trial ends',
    'grace_days' => 'Grace days',
    'auto_renew' => 'Renew automatically at the end of the term',
    'included_factories' => 'Factories included',
    'included_assets' => 'Machines included',
    'included_users' => 'Users included',
    'overage' => 'Over the limit',
    'overage_warn_only' => 'Warn only',
    'overage_allow_and_bill' => 'Allow and bill',
    'overage_block' => 'Block',

    // -- Suspension -------------------------------------------------------
    'suspend' => 'Suspend',
    'reactivate' => 'Reactivate',
    'suspend_confirm' => 'Suspend this customer? Everybody is signed out and nobody can sign in until it is reversed. No data is deleted.',
    'reactivate_confirm' => 'Let this customer back in?',
    'suspended' => ':name has been suspended. Their data is untouched.',
    'reactivated' => ':name is active again.',

    // -- Support access (SRS 5.4) -----------------------------------------
    'support_access' => 'Support access',
    'support_access_hint' => 'Seeing a customer\'s data requires a reason, expires by itself, and is announced to the customer. You then act as one of their users, which means you see exactly what that person sees and no more.',
    'reason' => 'Reason',
    'reason_example' => 'Ticket 4471: work orders not appearing after a factory transfer.',
    'reason_too_short' => 'Write a real reason. It goes to the customer and into the audit log.',
    'hours' => 'Hours',
    'request_access' => 'Open access',
    'support_opened' => 'Access opened. The customer has been notified.',
    'support_closed' => 'Access handed back.',
    'support_open_by' => 'Support access open: :name',
    'active_now' => 'Open now',
    'act_as' => 'Act as',
    'enter' => 'Enter',
    'hand_back' => 'Hand access back now',
    'no_grants' => 'Nobody has been given access to this customer.',
    'grant_not_active' => 'That access has ended or expired. Open a new one.',
    'not_a_member' => 'That person is not an active member of this company.',

    'support_session_banner' => 'Support session — you are inside a customer\'s account',
    'acting_as' => 'Acting as :name (:email)',
    'leave_support_session' => 'Leave',
];
