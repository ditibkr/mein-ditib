<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MemberService
{
    public function __construct(
        private readonly MemberNumberService $numberService
    ) {}

    public function create(array $data, int $userId): Member
    {
        return DB::transaction(function () use ($data, $userId) {
            $data['member_number'] ??= $this->numberService->generate();
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;

            if (!empty($data['gdpr_consent'])) {
                $data['gdpr_consent_at'] = now();
            }

            return Member::create($data);
        });
    }

    public function update(Member $member, array $data, int $userId): Member
    {
        return DB::transaction(function () use ($member, $data, $userId) {
            $data['updated_by'] = $userId;

            if (isset($data['gdpr_consent']) && $data['gdpr_consent'] && !$member->gdpr_consent) {
                $data['gdpr_consent_at'] = now();
            }

            $member->update($data);
            return $member->fresh();
        });
    }

    public function importCsv(UploadedFile $file, int $userId): array
    {
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $content = file_get_contents($file->getRealPath());
        $lines = explode("\n", mb_convert_encoding($content, 'UTF-8', 'auto'));
        $headers = null;

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line, ';');

            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            try {
                $data = array_combine($headers, $row);
                $this->importRow($data, $userId);
                $results['imported']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Zeile {$lineNumber}: {$e->getMessage()}";
                $results['skipped']++;
                Log::warning("CSV-Import Fehler Zeile {$lineNumber}: {$e->getMessage()}");
            }
        }

        return $results;
    }

    private function importRow(array $data, int $userId): void
    {
        $email = trim($data['email'] ?? '');

        if ($email && Member::where('email', $email)->withTrashed()->exists()) {
            throw new \RuntimeException("E-Mail '{$email}' bereits vorhanden");
        }

        $this->create([
            'first_name' => trim($data['vorname'] ?? $data['first_name'] ?? ''),
            'last_name' => trim($data['nachname'] ?? $data['last_name'] ?? ''),
            'email' => $email ?: null,
            'phone' => trim($data['telefon'] ?? $data['phone'] ?? '') ?: null,
            'birth_date' => $this->parseDate($data['geburtsdatum'] ?? $data['birth_date'] ?? null),
            'street' => trim($data['strasse'] ?? $data['street'] ?? '') ?: null,
            'house_number' => trim($data['hausnummer'] ?? $data['house_number'] ?? '') ?: null,
            'zip_code' => trim($data['plz'] ?? $data['zip_code'] ?? '') ?: null,
            'city' => trim($data['ort'] ?? $data['city'] ?? '') ?: null,
            'status' => 'aktiv',
            'gdpr_consent' => false,
        ], $userId);
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat($format, trim($value));
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function getStatistics(): array
    {
        return [
            'total' => Member::count(),
            'active' => Member::active()->count(),
            'new_this_month' => Member::where('created_at', '>=', now()->startOfMonth())->count(),
            'by_category' => Member::select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
        ];
    }
}
