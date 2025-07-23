<?php

namespace ItHealer\LaravelTron\Enums;

enum TronTransactionType: string
{
    case INCOMING = 'in';
    case OUTGOING = 'out';
}
