<?php

namespace App\Services;

use App\Exceptions\SSOUnavailableException;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SSOIdentityService
{
    public function __construct(private SSOService $sso, private SSOOfficeService $offices) {}

    public function synchronize(array $authorization, ?User $expectedUser = null, bool $refreshOffice = true): ?User
    {
        $uuid = $authorization['employee']['uuid'] ?? null;
        $role = $authorization['role'] ?? null;

        if (($authorization['authorized'] ?? false) !== true || ! is_string($uuid)
            || ! Str::isUuid($uuid) || ! in_array($role, SSOService::DIRECTORY_ROLES, true)
            || ($expectedUser && $expectedUser->sso_uuid !== $uuid)) {
            return null;
        }

        $person = $this->sso->directoryUser($uuid);
        if (! $person || ($person['uuid'] ?? null) !== $uuid || ($person['role'] ?? null) !== $role) {
            return null;
        }

        $ssoOfficeId = isset($person['office_id']) ? (int) $person['office_id'] : null;
        $office = ! $refreshOffice && $ssoOfficeId !== null ? Office::where('sso_id', $ssoOfficeId)->first() : null;
        if ($ssoOfficeId !== null && ($refreshOffice || ! $office)) {
            $office = $this->offices->resolve($ssoOfficeId);
        }
        $email = $authorization['employee']['email'] ?? null;
        $email = is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;

        return DB::transaction(function () use ($expectedUser, $uuid, $person, $role, $office, $email) {
            $user = $expectedUser ?? User::where('sso_uuid', $uuid)->first() ?? new User;
            // Email is display metadata, never an identity link to a legacy account.
            if ($email && User::where('email', $email)->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))->exists()) {
                $email = null;
            }

            $user->forceFill([
                'sso_uuid' => $uuid,
                'name' => $person['full_name'],
                'email' => $email,
                'sso_role' => $role,
                'sso_position' => $person['position_title'] ?? null,
                'sso_position_id' => $person['position_id'] ?? null,
                'office_id' => $office?->id,
                'is_active' => true,
                'password' => null,
            ])->save();
            $user->syncRoles([$this->sso->mapSsoRoleToOptsRole($role)]);
            $user->unsetRelation('office');
            $user->unsetRelation('roles');

            return $user;
        });
    }

    /** Discover current recipients from SSO, including assignments changed while users are idle. */
    public function eligibleRecipients(array $roles = SSOService::DIRECTORY_ROLES, ?int $officeId = null): Collection
    {
        $ssoOfficeId = $officeId === null ? null : Office::find($officeId)?->sso_id;
        if ($officeId !== null && $ssoOfficeId === null) {
            return new Collection;
        }

        $uuids = [];
        $page = 1;
        try {
            do {
                $response = $this->sso->directory(['page' => $page, 'per_page' => 50]);
                if (($response['meta']['page'] ?? null) !== $page || $page > 10000) {
                    throw new SSOUnavailableException('SSO directory pagination did not finish.');
                }
                foreach ($response['data'] as $person) {
                    if (in_array($person['role'], $roles, true)
                        && ($ssoOfficeId === null || (int) ($person['office_id'] ?? 0) === (int) $ssoOfficeId)) {
                        $uuids[] = $person['uuid'];
                    }
                }
                $page++;
            } while ($response['meta']['has_more']);
        } catch (SSOUnavailableException $exception) {
            Log::warning('SSO recipient directory unavailable; notifications withheld.');

            return new Collection;
        }

        return User::whereIn('sso_uuid', array_unique($uuids))->get();
    }

    /** Current eligibility is checked before sending information to an existing subscription. */
    public function eligibleRecipient(User $user, array $roles = SSOService::DIRECTORY_ROLES, ?int $officeId = null): bool
    {
        if (! $user->sso_uuid) {
            return false;
        }

        try {
            $person = $this->sso->directoryUser($user->sso_uuid);
            if (! $person || ($person['uuid'] ?? null) !== $user->sso_uuid
                || ! in_array($person['role'] ?? null, $roles, true)) {
                return false;
            }

            if ($officeId !== null) {
                $office = Office::find($officeId);

                return $office && $office->sso_id !== null
                    && (int) ($person['office_id'] ?? 0) === (int) $office->sso_id;
            }

            return true;
        } catch (SSOUnavailableException $exception) {
            Log::warning('SSO recipient eligibility unavailable; notification withheld.');

            return false;
        }
    }
}
