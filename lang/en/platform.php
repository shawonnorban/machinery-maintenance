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
    'company_closed' => 'Closed',
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

    // Who the customer is, read rather than filled in — everything a support
    // call needs to confirm it is speaking to the right person, before either
    // form below is touched.
    'owner_phone' => 'Phone',
    'owner_email_verified' => 'Email verified',
    'verified_on' => 'Verified :date',
    'not_verified' => 'Not verified',
    'owner_last_login' => 'Last sign-in',
    'never_signed_in' => 'Never signed in',
    'owner_customer_since' => 'Customer since',
    'owner_status' => 'Membership',
    'member_status_active' => 'Active',
    'member_status_suspended' => 'Suspended in this company',
    'not_recorded' => 'Not recorded',

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

    // -- The shell and the customer list ----------------------------------
    'staff' => 'Platform staff',
    'manage' => 'Open',

    // Two of these four are problems rather than statistics, which is why they
    // are on the list at all: a customer nobody will invoice, and somebody
    // inside a customer right now.
    'figure_tenants' => 'Customers',
    'figure_active' => 'Active',
    'figure_unbilled' => 'No contract',
    'figure_support_open' => 'Support inside',
    'figure_suspended' => 'Suspended',
    'growth_chart_label' => 'New customers by month, the last six months',

    // -- One customer ------------------------------------------------------
    'until' => 'until',
    'open_ended' => 'no end date',
    'included' => 'Included',
    // Which of these two is true depends on the contract's overage policy, and
    // saying the wrong one is worse than saying nothing: this line was written
    // when no limit was enforced anywhere, and it kept claiming nothing was
    // blocked after Block started blocking.
    'limits_are_advisory' => 'Recorded for billing. Nothing is blocked when a customer goes over.',
    'limits_are_enforced' => 'Enforced. At the limit, adding another factory, machine or user is refused. Anything already over the limit is left alone — only the next one is stopped.',
    'earlier_contracts' => '{1} :count earlier contract|[2,*] :count earlier contracts',
    'replace_contract' => 'Replace this contract',
    'supersedes_notice' => 'Saving this archives :number. Invoices already raised under it are left alone.',
    'no_factories' => 'No factories yet.',
    'access_history' => 'Earlier support access',
    'act_as_hint' => 'Whoever you pick is named in the audit log. Acting as the owner and acting as a storekeeper are different amounts of access.',

    // -- Suspension --------------------------------------------------------
    'currently_suspended' => 'This company is suspended. Nobody in it can sign in.',
    'suspended_by_on' => 'Suspended by :name on :date',
    'suspend_hint' => 'Stops everybody in this company from signing in. Nothing is deleted, and reactivating restores access immediately.',
    'suspension_reason' => 'Reason',
    'suspension_reason_hint' => 'Shown to the customer word for word on the screen that tells them they are stopped.',
    'suspension_reason_example' => 'Invoice INV-2026-0042 unpaid since 30 June, after three reminders.',

    'usage' => 'Usage',
    'no_limit_set' => 'no limit set',
    // -- Invoices and account controls ------------------------------------
    'invoices' => 'Invoices',
    'invoice_number' => 'Invoice',
    'issued' => 'Issued',
    'due' => 'Due',
    'total' => 'Total',
    'balance' => 'Balance',
    'outstanding' => ':amount outstanding',
    'issue' => 'Issue',
    'record_payment' => 'Record payment',
    'method' => 'Method',
    'method_bank_transfer' => 'Bank transfer',
    'method_cash' => 'Cash',
    'method_cheque' => 'Cheque',
    'method_mobile' => 'Mobile money',
    'method_card' => 'Card',
    'reference' => 'Reference',
    'no_invoices' => 'No invoices yet',
    'no_invoices_hint' => 'Raise one below, or let the scheduled run issue the first on the billing cycle.',
    'raise_invoice' => 'Raise an invoice',
    'period_start' => 'Period from',
    'period_end' => 'Period to',
    'tax_rate' => 'Tax %',
    'draft' => 'Draft it',
    'invoice_drafted' => 'Invoice :number drafted. Check it, then issue it.',
    'invoice_issued' => 'Invoice :number issued.',
    'invoice_voided' => 'Invoice :number voided.',
    'payment_recorded' => 'Payment recorded.',
    'no_contract_to_invoice' => 'This customer has no contract, so there is nothing to invoice against. Record a contract first.',
    'details' => 'Company details',
    'details_saved' => 'Details saved.',
    'code_is_fixed' => 'The code :code cannot be changed. It is inside every work order and breakdown number this customer has issued.',
    'save_email' => 'Save email',
    'email_changed' => 'Sign-in address changed to :email.',
    'reset_password' => 'Issue a new password',
    'reset_hint' => 'Signs the account out everywhere and stops its API tokens. The new password is shown once, here.',
    'reset_confirm' => 'Issue a new password for :name? They are signed out everywhere immediately.',
    'reset_shown_once' => 'Give this to the customer now. It is shown once and cannot be retrieved again.',
    'password_reset_done' => 'A new password has been issued for :name.',
    'reset_reason_example' => 'Owner locked out; company email account closed. Ticket 5120.',
    'credential_reason_example' => 'Address mistyped at onboarding. Ticket 5120.',
    'credential_change_notice' => 'The :what for :account was changed by support. Reason: :reason',
    'credential_email' => 'sign-in address',
    'credential_password' => 'password',
    'customer_login' => 'Customer sign-in',
    'customer_login_hint' => 'The owner account — the one the customer signs in with, and the only one nobody inside their company can reset. Both changes are announced to the customer and recorded in the audit log.',
    'customer_id' => 'Customer ID (sign-in email)',
    'customer_id_hint' => 'This is what they type to sign in. Change it when the address was mistyped or the mailbox is gone.',
    'customer_password' => 'Issue a new password',

    // Closing an account, and erasing one. Two different things, said
    // differently: the first sentence of each is what separates them.
    // What a customer is allowed. Amended in place: price and term are contract
    // terms and still need a new contract, an entitlement is not.
    'limits' => 'Plan limits',
    'limits_hint' => 'What this customer is allowed. Changing these does not change their contract, its price or its term.',
    'limits_blank_hint' => 'Leave a box empty for no limit.',
    'limits_need_contract' => 'Limits live on the contract, so this customer needs one before limits can be set.',
    'limits_saved' => 'Limits saved.',
    'unlimited' => 'No limit',
    'in_use' => ':count in use now',
    'overage_effect' => 'Only Block actually stops anything: at the limit, adding another is refused. Warn only and Allow and bill let the work carry on and settle it commercially.',

    // Where a customer reaches their system. Two kinds, and the difference in
    // effort between them is the customer's, not ours — so it is spelled out.
    'domains' => 'Web address',
    'domain_none' => 'This customer uses the shared address. Add one below to give them their own.',
    'domain_add' => 'Add an address',
    'domain_kind' => 'Kind',
    'domain_kind_subdomain' => 'Subdomain on our domain',
    'domain_kind_custom' => "The customer's own domain",
    'domain_host' => 'Address',
    'domain_host_hint' => 'For a subdomain, type only the first part — it is added to :base. For their own domain, type the whole address.',
    'domain_primary' => 'Primary',
    'domain_verified' => 'Working',
    'domain_pending' => 'Waiting on DNS',
    'domain_check' => 'Check now',
    'domain_make_primary' => 'Make primary',
    'domain_remove_confirm' => 'Remove :host? Anybody using it will have to go back to the shared address.',
    'domain_added' => ':host has been added. It will not work until the records below are in place.',
    'domain_live' => ':host is working.',
    'domain_removed' => ':host has been removed.',
    'domain_primary_set' => ':host is now the address this customer is given.',
    'domain_taken' => 'That address is already in use by a customer.',
    'domain_invalid' => 'That does not look like a web address.',
    'domain_verify_first' => 'An address has to be working before it can be the primary one.',
    'domain_not_found_yet' => 'The record is not visible yet. DNS changes can take a few hours to spread — try again later.',
    'domain_verify_first_note' => 'Until this is done the address does not reach anybody, which is deliberate: honouring an unproven claim would let one customer collect another\'s sign-ins.',

    'domain_steps' => 'What the customer has to do',
    'domain_step_cname' => 'Add a CNAME record pointing their address at ours:',
    'domain_step_txt' => 'Add a TXT record proving they own it:',
    'domain_step_check' => 'Then press Check now. DNS changes can take a few hours to spread.',
    'domain_tls_note' => 'A new address also needs an HTTPS certificate on the server. That is done by the server, not from this screen — see docs/11-Deployment.md.',

    // The two screens that are about the platform rather than one customer.
    'tenant' => 'Customer',
    'support_desk_intro' => 'Who is inside a customer\'s data, and who has been.',
    'support_open_now' => 'Open right now',
    'support_none_open' => 'Nobody has access to a customer\'s data.',
    'support_no_history' => 'No support access has been opened',
    'support_no_history_hint' => 'Every grant ever opened is listed here, with who opened it and why.',
    'notifications_intro' => 'What the rest of the platform staff have done.',
    'notifications_empty_hint' => 'Support access, suspensions and closures appear here as they happen.',

    // Told to colleagues, not to the person who did it: a notification saying
    // "you opened support access" is noise, and noise is how a bell stops
    // being read.
    'notify_support_opened' => ':name opened support access to :company',
    'notify_support_closed' => ':name handed back support access to :company',
    'notify_suspended' => ':name suspended :company',
    'notify_closed' => ':name closed the account for :company',
    'notify_erased' => ':name erased :company and all of its data',

    'close_account' => 'Close the account',
    'close_hint' => 'Ends the account. Everybody in this company is signed out and cannot sign in again, and the customer disappears from the list above.',
    'close_reversible' => 'Nothing is deleted. A closed account can be reopened, and everything in it comes back exactly as it was.',
    'confirm_code_label' => 'Type the customer code :code to confirm',
    'confirm_code_mismatch' => 'That is not the customer code. Type :code exactly.',
    'closed_done' => ':name has been closed. It can still be reopened from the list.',
    'reopened_done' => ':name has been reopened.',

    'closed_customers' => 'Closed accounts',
    'closed_customers_hint' => 'Closed accounts keep all their data and can be reopened. Erasing one is permanent.',
    'closed_on' => 'Closed :date',
    'reopen' => 'Reopen',
    'no_closed_customers' => 'No closed accounts.',

    'erase' => 'Erase permanently',
    'erase_hint' => 'Deletes this customer and everything in it — machines, work orders, breakdowns, stock, invoices and users\' access. This cannot be undone.',
    'erase_no_export' => 'There is no tenant export yet, so nothing is kept. If the customer may ever ask for their records back, reopen the account instead.',
    'erase_audit_kept' => 'The audit log survives: what was done, by whom and when is kept even after the data it describes is gone.',
    'erased_done' => ':name and all of its data have been erased.',

    // The tabs a customer's page is split across. Each is its own page rather
    // than a panel on one long one — a customer's billing history and their
    // support tickets are different enough errands that cramming both onto a
    // single page was what made the page unreadable.
    'company_management' => 'Company',
    'bill_management' => 'Billing',
    'danger_zone' => 'Danger zone',
    'analytics' => 'Analytics',

    'company_email' => 'Email',
    'company_phone' => 'Phone',
    'company_country' => 'Country',
    'company_address' => 'Address',

    // -- The money ---------------------------------------------------------
    // Every figure is read back from invoices, payments and refunds rather
    // than kept as a running total. A stored total can drift from the
    // documents it claims to summarise, and the first time it does nobody can
    // tell which of the two is wrong.
    'finance' => 'Money',
    'finance_intro' => 'What each customer has paid, what is still owed, and what running the platform costs.',
    'total_invoiced' => 'Invoiced',
    'total_received' => 'Received',
    'total_due' => 'Outstanding',
    'total_spent' => 'Spent',
    'net' => 'Net',
    'refunded_note' => 'Includes :amount :currency refunded, already taken off the received figure.',
    'money_by_month' => 'In and out, by month',
    'no_money_by_month' => 'Nothing has been received or spent in the last twelve months.',
    'per_customer' => 'By customer',
    'last_payment' => 'Last payment',
    'no_money_yet' => 'Nothing has been invoiced yet',
    'no_money_yet_hint' => 'Once a customer has a contract and an invoice, the totals appear here.',
    'no_billed_customers' => 'No customer has been invoiced',
    'no_billed_customers_hint' => 'Give a customer a contract, then raise their first invoice from their Billing tab.',

    // Late, as distinct from merely unpaid. An invoice inside its due date is
    // business as usual; one past it is a phone call somebody has to make.
    'overdue' => 'Overdue',
    'days_late' => ':days days late',
    'nothing_overdue' => 'Nothing is overdue',
    'nothing_overdue_hint' => 'Every issued invoice is either paid or still inside its due date.',

    'payments_received' => 'Payments received',
    'no_payments' => 'No payments yet',
    'no_payments_hint' => 'Each payment recorded against an invoice appears here, newest first.',

    'spending' => 'Spending',
    'expense_add' => 'Record a cost',
    'expense_date' => 'Date',
    'expense_description' => 'What it was for',
    'expense_description_example' => 'Server, August',
    'expense_category' => 'Category',
    'expense_vendor' => 'Paid to',
    'expense_reference' => 'Receipt number',
    'expense_recorded' => 'Cost recorded.',
    'expense_removed' => 'Cost removed.',
    'expense_remove_confirm' => 'Remove this cost from the totals?',
    'no_expenses' => 'No costs recorded',
    'no_expenses_hint' => 'Hosting, domains, software and the rest. Without them the net figure is only income.',

    // No salary or wages category, deliberately: payroll is out of scope for
    // this product, and an expense category is exactly how it would arrive.
    'expense_category_hosting' => 'Hosting',
    'expense_category_domain' => 'Domains',
    'expense_category_software' => 'Software',
    'expense_category_marketing' => 'Marketing',
    'expense_category_equipment' => 'Equipment',
    'expense_category_professional_fees' => 'Professional fees',
    'expense_category_bank_charges' => 'Bank charges',
    'expense_category_other' => 'Other',

    'logo' => 'Logo',
    'no_logo' => 'No logo',
    'edit_company' => 'Edit :name',
    'edit_company_hint' => 'The customer\'s own details. Their code is fixed and cannot be changed here.',
    'customer_since' => 'Customer since',
    'logo_hint' => 'PNG, JPEG or WebP, up to 512 KB. Shown at 120×120.',
    'logo_save' => 'Save logo',
    'logo_saved' => 'Logo saved.',

    'work_orders' => 'Work orders',
    'no_usage_history' => 'No usage has been measured yet. The first month appears after billing:advance next runs.',

    // Support tickets (SRS 5): a customer reaching the platform in writing,
    // the opposite direction from a support grant. Nobody's data is touched
    // by asking, so this carries none of a grant's ceremony.
    'support_ticket' => 'Support tickets',
    'tickets_intro' => 'Ask the provider something, in writing.',
    'tickets_desk_intro' => 'Every customer\'s tickets, in one inbox.',
    'ticket_new' => 'New ticket',
    'ticket_subject' => 'Subject',
    'ticket_message' => 'Message',
    'ticket_submit' => 'Send',
    'ticket_send' => 'Send reply',
    'ticket_reply_placeholder' => 'Write a reply…',
    'ticket_manage' => 'Manage',
    'ticket_status' => 'Status',
    'ticket_assignee' => 'Assigned to',
    'ticket_unassigned' => 'Unassigned',
    'ticket_opened' => 'Ticket opened. The provider has been told.',
    'ticket_reply_sent' => 'Reply sent.',
    'ticket_status_saved' => 'Status saved.',
    'ticket_assigned' => 'Assignment saved.',
    'ticket_closed' => 'This ticket is closed. Open a new one to raise it again.',
    'ticket_body_required' => 'Write something before sending.',
    'ticket_status_invalid' => 'That is not a valid status.',
    'ticket_opened_by' => 'Opened by :name',
    'ticket_assigned_to' => 'Assigned to :name',
    'ticket_last_activity' => 'Last activity :time',
    'ticket_last_activity_column' => 'Last activity',
    'ticket_status_open' => 'Open',
    'ticket_status_in_progress' => 'In progress',
    'ticket_status_resolved' => 'Resolved',
    'ticket_status_closed' => 'Closed',
    'no_tickets' => 'No tickets',
    'no_tickets_hint' => 'This customer has not raised anything with the provider.',
    'no_tickets_hint_tenant' => 'When something needs the provider\'s attention, raise it here.',
    'tickets_open' => 'Open right now',
    'tickets_none_open' => 'Nothing open across any customer.',
    'tickets_closed' => 'Closed tickets',
    'tickets_no_closed' => 'No closed tickets yet',
    'tickets_no_closed_hint' => 'Every ticket, once it is answered and closed, is listed here.',

    // Told to colleagues, not to whoever raised or answered the ticket — the
    // same reason support-access and suspension notices skip the person who
    // acted.
    'notify_ticket_opened' => ':name at :company opened a ticket',
    'notify_ticket_replied' => ':name replied on a ticket',
];
