<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bids extends Model
{   
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tbl_bids';
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';
    /**
     * @var array
     */
    protected $fillable = ['max_bids', 'shipping_charges', 'time_bids', 'estimated_total', 'user_id', 'product_id', 'guid', 'created_at', 'updated_at'];
}
