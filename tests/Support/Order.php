<?php

namespace Karnoweb\Wallet\Tests\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic fixture "transactionable" model (order/payment/reservation...).
 */
class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = ['name'];
}
