<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentApplication extends Model
{
    protected $table = 'payment_applications';

    protected $fillable = [
        'payment_id',
        'applicable_type',
        'applicable_id',
        'amount_applied',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'applicable_id' => 'integer',
        'amount_applied' => 'decimal:4',
    ];

    // No se define relacion Eloquent hacia `payments`: esa tabla se maneja
    // hoy via DB::table() (no existe modelo Payment en esta fase).
}
