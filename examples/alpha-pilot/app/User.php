<?php

namespace App;

final class User extends \Illuminate\Foundation\Auth\User
{
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
}
