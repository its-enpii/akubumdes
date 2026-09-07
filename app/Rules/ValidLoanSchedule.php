<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Lending\Services\LoanService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidLoanSchedule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $frequency = $attribute === 'interest_frequency'
            ? 'interest_frequency'
            : 'principal_frequency';
        $interval = LoanService::intervalMonths((string) data_get($this->data, $frequency, 'monthly'));
        if ($interval === 0) {
            return;
        }
        $grace = max(0, (int) $value);
        if ($grace + $interval > (int) data_get($this->data, 'term_months', 0)) {
            $fail('Interval angsuran tidak boleh melebihi jangka waktu setelah grace period.');
        }
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    private array $data = [];
}
