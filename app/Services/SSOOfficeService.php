<?php

namespace App\Services;

use App\Exceptions\SSOUnavailableException;
use App\Models\Office;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SSOOfficeService
{
    public function __construct(private SSOService $sso) {}

    public function refreshIfStale(): void
    {
        if (! Cache::has($this->cacheKey())) {
            Cache::lock($this->cacheKey().':lock', 30)->block(5, function () {
                if (! Cache::has($this->cacheKey())) {
                    $this->sync();
                }
            });
        }
    }

    public function sync(): array
    {
        $records = [];
        $page = 1;
        do {
            $response = $this->sso->offices($page++);
            foreach ($response['data'] as $record) {
                if (! $record['is_active'] || isset($records[$record['id']])) {
                    throw new SSOUnavailableException('SSO returned conflicting office references.');
                }
                $records[$record['id']] = $record;
            }
            if ($page > 10000 && $response['meta']['has_more']) {
                throw new SSOUnavailableException('SSO office pagination did not finish.');
            }
        } while ($response['meta']['has_more']);

        // Finish every network read before changing the local reference snapshot.
        foreach (Office::withTrashed()->whereNotNull('sso_id')->pluck('sso_id') as $id) {
            if (! isset($records[$id])) {
                $record = $this->sso->office($id);
                if ($record !== null) {
                    if ($record['is_active']) {
                        throw new SSOUnavailableException('SSO offices changed during synchronization. Please retry.');
                    }
                    $records[$id] = $record;
                }
            }
        }

        $report = DB::transaction(function () use ($records) {
            Office::query()->update(['is_active' => false]);
            foreach ($records as $record) {
                $this->upsert($record);
            }

            return [
                'active' => Office::where('is_active', true)->whereNotNull('sso_id')->count(),
                'unmapped' => Office::whereNull('sso_id')->count(),
            ];
        });
        Cache::put($this->cacheKey(), true, now()->addMinute());

        return $report;
    }

    public function resolve(?int $ssoId): ?Office
    {
        if ($ssoId === null) {
            return null;
        }
        $record = $this->sso->office($ssoId);
        if ($record === null) {
            Office::where('sso_id', $ssoId)->update(['is_active' => false]);

            return null;
        }

        return DB::transaction(fn () => $this->upsert($record));
    }

    private function upsert(array $record): Office
    {
        $office = Office::withTrashed()->where('sso_id', $record['id'])->lockForUpdate()->first();
        $matches = Office::withTrashed()->where(function ($query) use ($record) {
            $query->where('abbreviation', $record['abbreviation'])->orWhere('name', $record['name']);
        })->lockForUpdate()->get();

        if ($office) {
            if ($matches->contains(fn ($match) => $match->id !== $office->id)) {
                throw new SSOUnavailableException('An SSO office conflicts with an existing office. Review the mapping.');
            }
        } else {
            if ($matches->count() > 1 || $matches->contains(fn ($match) => $match->sso_id !== null)) {
                throw new SSOUnavailableException('An SSO office has an ambiguous local mapping. Review the mapping.');
            }
            $office = $matches->first() ?? new Office;
        }

        $office->fill([
            'sso_id' => $record['id'],
            'name' => $record['name'],
            'abbreviation' => $record['abbreviation'],
            'is_active' => $record['is_active'],
            'sso_synced_at' => now(),
        ]);
        $office->deleted_at = null;
        $office->save();

        return $office;
    }

    private function cacheKey(): string
    {
        return 'opts:sso-offices:'.hash('sha256', config('services.lgu_sso.api_url').'|'.config('services.lgu_sso.client_id'));
    }
}
