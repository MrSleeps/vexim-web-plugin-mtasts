<?php

namespace  VEximweb\Plugin\MTASTS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use VEximweb\Core\Data\Models\Domain;

class MtaSts extends Model
{
    use HasFactory;

    protected $table = 'vw_mta_sts';

    protected $fillable = [
        'domain_id',
        'policy_type',
        'max_age',
        'generated_id'
    ];

    // Define relationship with Domain
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'domain_id');
    }

    // Accessor for formatted max_age
    public function getFormattedMaxAgeAttribute()
    {
        if ($this->max_age >= 86400) {
            $days = floor($this->max_age / 86400);
            return $days . ' days';
        }
        return $this->max_age . ' seconds';
    }
}