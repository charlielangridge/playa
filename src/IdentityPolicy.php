<?php

namespace CharlieLangridge\Playa;

enum IdentityPolicy: string
{
    case Session = 'session';
    case Rolling = 'rolling';
}
