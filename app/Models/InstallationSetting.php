<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationSetting extends Model
{
    public const APP_NAME = 'app_name';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';
}
