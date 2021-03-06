<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MetaFieldValue extends Model
{
    protected $table = 'meta_field_values';

    public function document(){
        return $this->belongsTo('App\Document');
    }
}
