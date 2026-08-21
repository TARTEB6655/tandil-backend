<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Live/local fix: remove fake "Abu Dhabi (1)" zones, reset hamood@tandil.com,
 * and put him on the same real zone as supervisor1@test.com (or Abu Dhabi Central).
 *
 *   php artisan supervisors:reset-hamood --force
 */
class ResetHamoodSupervisorCommand extends Command
{
    protected $signature = 'supervisors:reset-hamood
                            {--force : Required in production}
                            {--email=hamood@tandil.com : Supervisor email to reset}
                            {--password=123456789 : New password}
                            {--name=Hamood : Display name}';

    protected $description = 'Delete/recreate hamood supervisor and assign to shared Abu Dhabi zone (not Abu Dhabi (1))';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Production requires --force');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->option('email')));
        $password = (string) $this->option('password');
        $name = trim((string) $this->option('name')) ?: 'Hamood';

        $this->info("Resetting supervisor {$email} …");

        DB::transaction(function () use ($email, $password, $name) {
            $canonical = $this->resolveCanonicalAbuDhabiZone();
            $this->line("Canonical zone: #{$canonical->id} {$canonical->name} / {$canonical->location}");

            $this->mergeAndDeleteFakeAbuDhabiZones($canonical);

            $existing = User::query()->where('email', $email)->first();
            if ($existing) {
                DB::table('area_supervisor')->where('user_id', $existing->id)->delete();
                Visit::query()->where('supervisor_id', $existing->id)->update(['supervisor_id' => null]);
                if (method_exists($existing, 'tokens')) {
                    $existing->tokens()->delete();
                }
                try {
                    if (method_exists($existing, 'syncRoles')) {
                        $existing->syncRoles([]);
                    }
                } catch (\Throwable $e) {
                    //
                }

                try {
                    $existing->delete();
                    $this->info("Deleted existing user #{$existing->id}");
                } catch (\Throwable $e) {
                    $existing->forceFill([
                        'email' => 'deleted+'.$existing->id.'.'.$email,
                        'status' => 'inactive',
                        'password' => Hash::make(str()->random(32)),
                    ])->save();
                    $this->warn("Hard delete blocked ({$e->getMessage()}); deactivated old row #{$existing->id}");
                }
            }

            try {
                Role::findOrCreate('supervisor', 'web');
            } catch (\Throwable $e) {
                //
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'supervisor',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            try {
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole('supervisor');
                }
            } catch (\Throwable $e) {
                $this->warn('Spatie role note: '.$e->getMessage());
            }

            $canonical->supervisors()->syncWithoutDetaching([$user->id]);

            $sup1 = User::query()->where('email', 'supervisor1@test.com')->first();
            if ($sup1) {
                $canonical->supervisors()->syncWithoutDetaching([$sup1->id]);
            }

            $this->info("Created #{$user->id} {$user->email}");
            $this->info("Assigned to area #{$canonical->id} {$canonical->name}");
        });

        $canonical = $this->resolveCanonicalAbuDhabiZone();
        $canonical->load('supervisors:id,name,email');
        $this->newLine();
        $this->info("Zone #{$canonical->id} supervisors:");
        foreach ($canonical->supervisors as $s) {
            $this->line("  - #{$s->id} {$s->email}");
        }

        $fakeLeft = Area::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', ['abu dhabi'])
                    ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['abu dhabi (%)']);
            })
            ->where('id', '!=', $canonical->id)
            ->count();
        $this->info("Fake Abu Dhabi/(N) rows left (besides canonical if named Abu Dhabi): {$fakeLeft}");

        return self::SUCCESS;
    }

    private function resolveCanonicalAbuDhabiZone(): Area
    {
        $sup1 = User::query()->where('email', 'supervisor1@test.com')->first();
        if ($sup1) {
            $fromSup1 = $sup1->supervisedAreas()
                ->where(function ($q) {
                    $q->where('name', 'like', '%Abu Dhabi%')
                        ->orWhere('location', 'like', '%Abu Dhabi%');
                })
                ->whereRaw("LOWER(TRIM(name)) NOT LIKE 'abu dhabi (%)'")
                ->orderBy('areas.id')
                ->first();
            if ($fromSup1) {
                return $fromSup1;
            }
        }

        foreach (['Abu Dhabi Central', 'Abu Dhabi City'] as $name) {
            $area = Area::query()->where('name', $name)->orderBy('id')->first();
            if ($area) {
                return $area;
            }
        }

        // Prefer clean "Abu Dhabi" over "Abu Dhabi (1)"
        $clean = Area::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', ['abu dhabi'])
            ->orderBy('id')
            ->first();
        if ($clean) {
            return $clean;
        }

        $numbered = Area::query()
            ->whereRaw('LOWER(TRIM(name)) LIKE ?', ['abu dhabi (%)'])
            ->orderBy('id')
            ->first();
        if ($numbered) {
            $numbered->name = 'Abu Dhabi';
            $numbered->location = $numbered->location ?: 'Abu Dhabi';
            $numbered->save();

            return $numbered;
        }

        return Area::create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
            'priority' => 100,
            'service_radius_km' => 30,
        ]);
    }

    private function mergeAndDeleteFakeAbuDhabiZones(Area $canonical): void
    {
        $fakes = Area::query()
            ->where('id', '!=', $canonical->id)
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', ['abu dhabi'])
                    ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', ['abu dhabi (%)']);
            })
            ->get();

        foreach ($fakes as $fake) {
            $supIds = $fake->supervisors()->pluck('users.id')->all();
            $techIds = $fake->technicians()->pluck('users.id')->all();
            if ($supIds !== []) {
                $canonical->supervisors()->syncWithoutDetaching($supIds);
            }
            if ($techIds !== []) {
                $canonical->technicians()->syncWithoutDetaching($techIds);
            }
            Visit::query()->where('area_id', $fake->id)->update(['area_id' => $canonical->id]);
            $fake->supervisors()->detach();
            $fake->technicians()->detach();
            $fake->delete();
            $this->line("Merged/deleted fake area #{$fake->id} {$fake->name}");
        }
    }
}
