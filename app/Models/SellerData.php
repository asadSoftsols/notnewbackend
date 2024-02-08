<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerData extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seller_datas';
    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';
    /**
     * @var array
     */
    protected $fillable = ['user_id', 'country_id', 'state_id', 'city_id', 'fullname', 'email', 'phone', 'address',  'zip',  'zip',  'password',  'password_confirmation','created_at', 'updated_at'];
    /**
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function country()
    {
        return $this->belongsTo(Countries::class, 'country_id');
    }

    public function states()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function cities()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function product()
    {
        return $this->hasMany(Product::class);
    }
    public function cart(){
        return $this->hasMany(UserCart::class);
    }
}
