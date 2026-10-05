<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Complaint action recipients
    |--------------------------------------------------------------------------
    |
    | These addresses receive an email each time a complaint is registered
    | so the team can take action. Comma-separated in COMPLAINT_ACTION_EMAILS.
    |
    */

    'action_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'COMPLAINT_ACTION_EMAILS',
            'h.ali@abcjuice.com.kw,m.thomas@abcjuice.com.kw,bibinc@abcjuice.com.kw'
        ))
    ))),

];
