<?php

declare(strict_types=1);

namespace App\Models\Scp;

use Illuminate\Database\Eloquent\Model;

class StaffPreference extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'staff_preferences';

    protected $guarded = [];

    protected $casts = [
        'notifications' => 'array',
    ];

    public static function defaults(int $staffId): array
    {
        return [
            'staff_id' => $staffId,
            'theme' => 'system',
            'language' => null,
            'timezone' => null,
            'notifications' => [
                'desktop' => true,
                'email' => false,
                'sound' => false,
            ],
            'last_active_panel' => 'scp',
            'default_scp_tab' => null,
            'default_admin_tab' => null,
        ];
    }

    public static function forStaff(int $staffId): self
    {
        return self::firstOrCreate(['staff_id' => $staffId], self::defaults($staffId));
    }
}
