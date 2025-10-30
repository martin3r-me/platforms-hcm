<?php

namespace Platform\Hcm\Models;

use Illuminate\Database\Eloquent\Model;

class HcmJobActivityAlias extends Model
{
    protected $table = 'hcm_job_activity_aliases';

    protected $fillable = [
        'job_activity_id',
        'alias',
        'team_id',
        'created_by_user_id',
    ];
}


