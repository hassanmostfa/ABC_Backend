<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Open = 'open';
    case InInvestigation = 'in_investigation';
    case PendingAction = 'pending_action';
    case CustomerNotified = 'customer_notified';
    case Closed = 'closed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InInvestigation],
            self::InInvestigation => [self::PendingAction],
            self::PendingAction => [self::CustomerNotified],
            self::CustomerNotified => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public function nextStatusValues(): array
    {
        return array_map(
            static fn (self $status) => $status->value,
            $this->allowedTransitions()
        );
    }
}
