<?php

namespace App\Models;

/**
 * PluginInstance model for the legacy osTicket ost_plugin_instance table.
 *
 * @property int $id
 * @property int $plugin_id
 * @property string $name
 * @property int $isactive
 * @property string $config
 * @property string $created
 * @property string $updated
 * @property-read Plugin|null $plugin
 */
class PluginInstance extends LegacyModel
{
    protected $table = 'plugin_instance';

    protected $primaryKey = 'id';

    public function plugin()
    {
        return $this->belongsTo(Plugin::class, 'plugin_id', 'id');
    }
}
