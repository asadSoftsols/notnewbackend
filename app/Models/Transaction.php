<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction';
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';
    /**
     * @var array
     */
    protected $fillable = ['transaction_id', 'account_id', 'user_id', 'transaction_type','amount','transaction_date', 'created_at', 'updated_at'];

    public function user()
    {
        return $this->hasMany('App\Models\User');
    }
}
