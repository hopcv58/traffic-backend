<?php

namespace App\Responsitory;

class History extends BaseModel
{
    protected $table = 'history';
    protected $fillable = [
      'start_time',
      'end_time',
      'colors',
    ];
    
}