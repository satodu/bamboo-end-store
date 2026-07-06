<?php

namespace App\Enums;

enum UserState: string
{
    case IDLE = 'idle';
    case INSTALLING = 'installing';
    case UNINSTALLING = 'uninstalling';
    case UPDATING = 'updating';
}
