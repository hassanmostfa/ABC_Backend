<?php

namespace App\Console\Commands;

use App\Repositories\Complaints\ComplaintRepositoryInterface;
use Illuminate\Console\Command;

class SendComplaintTargetReminders extends Command
{
    protected $signature = 'complaints:send-target-reminders';

    protected $description = 'Send reminder notifications for open complaints approaching CAPA/response target dates';

    public function handle(ComplaintRepositoryInterface $complaintRepository): int
    {
        $sent = $complaintRepository->sendApproachingTargetReminders();
        $this->info("Sent {$sent} complaint target-date reminder(s).");

        return self::SUCCESS;
    }
}
