<?php

namespace App\Services;

use App\Models\Member;

class MemberNumberService
{
    private string $prefix = 'M';
    private int $length = 6;

    public function generate(): string
    {
        $lastMember = Member::withTrashed()
            ->whereNotNull('member_number')
            ->orderByDesc('id')
            ->first();

        if (!$lastMember || !$lastMember->member_number) {
            return $this->prefix . str_pad('1', $this->length, '0', STR_PAD_LEFT);
        }

        $numeric = (int) ltrim(str_replace($this->prefix, '', $lastMember->member_number), '0');
        $next = $numeric + 1;

        return $this->prefix . str_pad((string) $next, $this->length, '0', STR_PAD_LEFT);
    }
}
