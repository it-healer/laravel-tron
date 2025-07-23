<?php

namespace ItHealer\LaravelTron\Enums;

enum TronModel: string
{
    case Node = 'node';
    case Wallet = 'wallet';
    case Address = 'address';
    case TRC20 = 'trc20';
    case Transaction = 'transaction';
    case Deposit = 'deposit';
}