<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class LegacyModel extends Model
{
    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'legacy';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Resolve the collection class name from the CollectedBy attribute.
     *
     * Overridden to prevent Laravel from instantiating this abstract class
     * when walking the parent chain for CollectedBy attributes.
     *
     * @return class-string|null
     */
    public function resolveCollectionFromAttribute()
    {
        return null;
    }
}
