<?php

use Illuminate\Database\Eloquent\Model;

arch()->preset()->laravel();

arch()->preset()->security();

arch('enums are string backed and live in the enums namespace')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('models extend the eloquent base model')
    ->expect('App\Models')
    ->toExtend(Model::class)
    ->ignoring('App\Models\User');

arch('actions are invokable single-purpose classes')
    ->expect('App\Actions')
    ->toBeClasses();

arch('debugging helpers never reach the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();
