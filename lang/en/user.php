<?php

declare(strict_types=1);

return [
    'users' => 'Users',
    'users_intro' => 'Who works in this company and what they may do. An account can belong to more than one company in a group; this screen manages its place in this one.',
    'new_user' => 'Add user',
    'edit_user' => 'Edit user',
    'person' => 'Person',
    'name' => 'Name',
    'email' => 'Email',
    'email_is_the_account' => 'The address identifies the account across every company it belongs to, so it is not changed here.',
    'phone' => 'Phone',
    'language' => 'Language',
    'search' => 'Search by name or email',
    'factory' => 'Factory',
    'factory_hint' => 'Used by the factory-scoped roles below. A company role covers every factory whatever is chosen here.',
    'factory_scope' => 'Factory',
    'company_scope' => 'Company',
    'company_wide' => 'Company-wide',
    'active' => 'Active',
    'suspended' => 'Suspended',
    'suspend' => 'Suspend',
    'restore' => 'Restore',
    'remove' => 'Remove from company',
    'reset_password' => 'Reset password',

    'roles' => 'Roles',
    'roles_intro' => 'What each role can do. Worth reading before handing one out.',
    'roles_read_only' => 'Seeded roles are shared by every company and cannot be edited. Cloning one to make your own is not built yet.',
    'permission_count' => '{0} No permissions|{1} :count permission|[2,*] :count permissions',
    'holder_count' => '{0} Nobody|{1} :count person|[2,*] :count people',

    'created' => ':name added.',
    'updated' => ':name saved.',
    'removed' => ':name no longer has access to this company.',
    'password_reset' => 'A new password has been issued for :name.',

    'password_will_be_generated' => 'A password will be generated and shown once when this person is added. Most people on a factory floor have no working email address, so it is handed over rather than emailed.',
    'password_shown_once' => 'Password for :email — shown once, and not readable again.',
    'password_hint' => 'Write it down or hand it over now. Reset it from this screen if it is lost.',
    'reset_confirm' => 'Issue a new password for :name? Their current one stops working immediately.',
    'remove_confirm' => 'Remove :name from this company? Their account and everything they signed off stay exactly where they are.',

    'already_a_member' => 'That person is already a member of this company.',
    'roles_required' => 'Choose at least one role. A member with no role can sign in and do nothing.',
    'factory_required' => 'A factory-scoped role needs a factory, otherwise it grants nothing anywhere.',
    'cannot_remove_yourself' => 'You cannot remove your own access.',
    'cannot_suspend_yourself' => 'You cannot suspend your own access.',
    'last_administrator' => 'This is the last person who can manage users. Give somebody else that ability first, or the company would have no way back in.',

    'no_users' => 'Nobody here yet.',
    'no_users_hint' => 'Add the people who will use the system: technicians record work, managers approve it.',
];
