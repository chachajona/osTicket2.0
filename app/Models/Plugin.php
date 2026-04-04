<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Plugin model for the legacy osTicket ost_plugin table.
 *
 * @property int $id
 * @property string $install_path
 * @property string $name
 * @property int $isactive
 * @property string $isphar
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, PluginInstance> $instances
 */
class Plugin extends LegacyModel
{
    protected $table = 'plugin';

    protected $primaryKey = 'id';

    public function instances()
    {
        return $this->hasMany(PluginInstance::class, 'plugin_id', 'id');
    }
}
