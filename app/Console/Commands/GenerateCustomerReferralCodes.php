<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class GenerateCustomerReferralCodes extends Command
{
    protected $signature = 'customers:generate-referral-codes {--completed : Only generate for completed registrations}';

    protected $description = 'Generate unique referral codes for existing customers who do not have one';

    public function handle(): int
    {
        $query = Customer::whereNull('referral_code');

        if ($this->option('completed')) {
            $query->where('is_completed', true);
        }

        $customers = $query->get();
        $count = $customers->count();

        if ($count === 0) {
            $this->info('All customers already have referral codes.');
            return self::SUCCESS;
        }

        $this->info("Generating referral codes for {$count} customers...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $generated = 0;
        foreach ($customers as $customer) {
            $customer->update([
                'referral_code' => Customer::generateUniqueReferralCode(),
            ]);
            $generated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Successfully generated {$generated} referral codes.");

        return self::SUCCESS;
    }
}
