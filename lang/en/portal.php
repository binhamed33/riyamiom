<?php

return [

    'accounting' => [
        'title' => 'Accounting',
        'total' => 'Total',
        'paid' => 'Paid',
        'due' => 'Outstanding',
        'currency' => 'OMR',
        'invoice' => 'Invoice',
        'paid_badge' => 'Paid',
        'unpaid_badge' => 'Unpaid',
        'remaining' => 'Remaining',
        'note' => 'These are the items your office chose to share. For any question about an amount, contact your office directly.',
    ],
    'brand' => 'Mudāwala',
    'portal' => 'Client Portal',
    'powered_by' => 'Powered by :brand',

    'login' => [
        'tagline' => 'Your window into your case',
        'lede' => 'Follow your cases, hearings and updates in one place, privately.',
        'welcome' => 'Welcome',
        'intro' => 'Enter your national ID to continue.',
        'national_id' => 'National ID',
        'national_id_hint' => 'The number registered with the office',
        'continue' => 'Continue',
        'step_of' => 'Step :current of :total',

        'verify_title' => 'Complete verification',
        'verify_intro' => 'To confirm your identity, enter the last 3 digits of the phone number registered with us.',
        'verify_hint' => 'The number we have on file: :digits',
        'verify_hint_many' => 'The numbers we have on file — any one works: :digits',
        'verify_action' => 'Verify and continue',
        'back' => 'Back',
        'digits_label' => 'Last three digits',

        'failed' => 'We could not verify these details. Please check them and try again.',
        'locked' => 'Too many attempts. Please try again in :minutes minutes.',
        'expired' => 'Verification timed out. Please start again.',
        'session_expired' => 'Your session has ended.',
        'disabled' => 'The client portal is not enabled for this office.',
        'disabled_hint' => 'Please contact the office directly.',
    ],

    'nav' => [
        'home' => 'Home',
        'cases' => 'My cases',
        'account' => 'Account',
        'logout' => 'Sign out',
    ],

    'home' => [
        'greeting' => 'Welcome, :name',
        'lede' => 'Here are the latest updates on your cases.',
        'total_cases' => 'Cases',
        'active_cases' => 'Active cases',
        'upcoming' => 'Upcoming hearings',
        'next_session' => 'Next hearing',
        'remaining_days' => ':count days remaining',
        'remaining_today' => 'Today',
        'remaining_tomorrow' => 'Tomorrow',
        'recent' => 'Recently updated',
        'view_all' => 'All cases',
    ],

    'cases' => [
        'title' => 'My cases',
        'number' => 'Case number',
        'court' => 'Court',
        'type' => 'Case type',
        'status' => 'Status',
        'last_update' => 'Last update',
        'next_session' => 'Next hearing',
        'lawyer' => 'Assigned lawyer',
        'opponent' => 'Opposing party',
        'view' => 'View case',
        'info' => 'Case information',
        'opened_at' => 'Filed on',
        'description' => 'Subject',
    ],

    'sessions' => [
        'title' => 'Hearings',
        'date' => 'Date',
        'location' => 'Location',
        'status' => 'Status',
        'upcoming' => 'Upcoming',
        'completed' => 'Held',
        'postponed' => 'Postponed',
        'cancelled' => 'Cancelled',
    ],

    'timeline' => [
        'title' => 'Case timeline',
        'opened' => 'Case filed',
        'case_number' => 'No. :number',
        'session' => 'Hearing',
        'session_upcoming' => 'Upcoming hearing',
        'session_held' => 'Hearing held',
        'session_postponed' => 'Hearing postponed',
        'session_cancelled' => 'Hearing cancelled',
        'update' => 'Case update',
        'last_update' => 'Last update',
    ],

    'documents' => [
        'title' => 'Documents',
        'view' => 'View',
        'download' => 'Download',
        'size' => 'Size',
        'added' => 'Added',
    ],

    'account' => [
        'title' => 'My account',
        'name' => 'Name',
        'national_id' => 'National ID',
        'phone' => 'Phone number',
        'privacy_note' => 'We show only part of your details to protect your privacy.',
    ],

    'contact' => [
        'title' => 'Need help?',
        'lede' => 'Contact your office directly and we will be glad to assist.',
        'call' => 'Call',
        'email' => 'Email',
    ],

    'empty' => [
        'cases' => 'No cases are linked to your account yet.',
        'cases_hint' => 'When the office files a case in your name it will appear here.',
        'sessions' => 'No upcoming hearings at the moment.',
        'documents' => 'No documents are available to view at the moment.',
        'timeline' => 'No events to show yet.',
    ],

    'status' => [
        'active' => 'In progress',
        'pending' => 'Awaiting hearing',
        'overdue' => 'Needs attention',
        'closed' => 'Closed',
        'won' => 'Decided in your favour',
        'lost' => 'Closed',
        'adjudicated' => 'Judgment issued',
        'fees_pending' => 'In progress',
    ],

    'a11y' => [
        'skip' => 'Skip to content',
        'menu' => 'Menu',
    ],
];
