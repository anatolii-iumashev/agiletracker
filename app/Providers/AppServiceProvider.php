<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Token abilities the API v1 exposes. Each one is also a Gate ability,
     * so Lomkit's authorization layer can require it next to the policy.
     *
     * @var list<string>
     */
    public const array API_ABILITIES = [
        'items:read',
        'items:write',
        'users:read',
        'users:write',
        'labels:read',
        'labels:write',
        'comments:read',
        'comments:write',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        foreach (self::API_ABILITIES as $ability) {
            Gate::define($ability, fn (User $user): bool => $user->tokenCan($ability));
        }

        // The access policy for the documentation UI is an open question for
        // production: it stays restricted outside local and testing.
        Gate::define('viewApiDocs', fn (?User $user): bool => app()->environment('local', 'testing'));

        file_put_contents(storage_path('logs/scramble-boot.txt'), 'boot '.date('H:i:s').PHP_EOL, FILE_APPEND);
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            file_put_contents(storage_path('logs/scramble-boot.txt'), 'transformer'.PHP_EOL, FILE_APPEND);
            $openApi->secure(SecurityScheme::http('bearer'));

            foreach (['UserCreate', 'UserUpdate'] as $schema) {
                $password = $openApi->components->schemas[$schema]->type->properties['password'] ?? null;

                if ($password instanceof Type) {
                    $password->setDescription('Write-only. '.$password->description);
                }
            }
        });
    }
}
