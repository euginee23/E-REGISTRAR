<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;

// Mailables are exempt from the preset's queue-everything rule: see below.
arch()->preset()->laravel()->ignoring('App\Mail');

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

arch('mailables leave the choice of queueing to DeliverMail')
    ->expect('App\Mail')
    ->toExtend(Mailable::class)
    ->not->toImplement(ShouldQueue::class);

arch('debugging helpers never reach the codebase')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();
