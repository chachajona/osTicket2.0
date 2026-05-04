<?php

namespace App\Providers;

use App\Auth\StaffUserProvider;
use App\Mail\OutboundMailGuard;
use App\Models\CannedResponse;
use App\Models\Department;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateGroup;
use App\Models\Filter;
use App\Models\HelpTopic;
use App\Models\Role;
use App\Models\Sla;
use App\Models\Staff;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Policies\CannedResponsePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmailConfigPolicy;
use App\Policies\FilterPolicy;
use App\Policies\HelpTopicPolicy;
use App\Policies\RolePolicy;
use App\Policies\SlaPolicy;
use App\Policies\StaffPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TicketActionPolicy;
use App\Services\Admin\DepartmentRoleResolver;
use App\Services\LegacyHasher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Hashing\HashManager;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'T' => Ticket::class,
            'staff' => Staff::class,
            'A' => Task::class,
        ]);

        $this->app->make(HashManager::class)->extend('legacy', function () {
            return new LegacyHasher;
        });

        Auth::provider('staff', function ($app, array $config) {
            return new StaffUserProvider(
                $app['hash'],
                $config['model'],
                $app['cache']->store(),
            );
        });

        Gate::define('can-in-department', function (Staff $staff, string $perm, int $deptId): bool {
            $role = app(DepartmentRoleResolver::class)->roleForDepartment($staff, $deptId);

            return $role?->hasPermissionTo($perm) ?? false;
        });

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(CannedResponse::class, CannedResponsePolicy::class);
        Gate::policy(HelpTopic::class, HelpTopicPolicy::class);
        Gate::policy(Filter::class, FilterPolicy::class);
        Gate::policy(Sla::class, SlaPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(EmailAccount::class, EmailConfigPolicy::class);
        Gate::policy(EmailTemplate::class, EmailConfigPolicy::class);
        Gate::policy(EmailTemplateGroup::class, EmailConfigPolicy::class);
        Gate::policy(Staff::class, StaffPolicy::class);
        Gate::policy(Ticket::class, TicketActionPolicy::class);

        Event::listen(MessageSending::class, OutboundMailGuard::class);
    }
}
