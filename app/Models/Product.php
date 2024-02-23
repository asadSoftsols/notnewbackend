<?php

namespace App\Models;

use App\Core\Base;
use App\Helpers\GuidHelper;
use App\Interfaces\IMediaInteraction;
use App\Scopes\ActiveScope;
use App\Scopes\SoldScope;
use App\Traits\InteractWithMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Self_;

/**
 * App\Models\Product
 *
 * @property integer $id
 * @property integer $category_id
 * @property integer $user_id
 * @property string $name
 * @property string $description
 * @property float $price
 * @property float $sale_price
 * @property string $location
 * @property string $google_address
 * @property string $postal_address
 * @property float $longitude
 * @property float $latitude
 * @property boolean $featured
 * @property string $featured_until
 * @property boolean $hired
 * @property string $hired_until
 * @property boolean $active
 * @property string $guid
 * @property string $created_at
 * @property string $updated_at
 * @property string $street_address
 * @property string $city
 * @property string $zip
 * @property string $state
 * @property tinyinteger $steps
 * @property tinyinteger $shipment_type
 * @property User $user
 * @property ProductsCategories[] $productsCategories
 * @property Media[] media
 * @property Rating[] $ratings
 * @property-read int|null $ratings_count
 * @method static \Illuminate\Database\Eloquent\Builder|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereGoogleAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereLatitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereLongitude($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product wherePostalAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereSalePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereUserId($value)
 * @mixin \Eloquent
 */
class Product extends Base implements IMediaInteraction
{
    use InteractWithMedia;

    protected $autoBlame = false; //@todo temp
    const MEDIA_UPLOAD = "PRODUCT";

    const FEATURED_PRICES = [
        7 => 149,
        30 => 299
    ];

    const HIRE_PRICES = [
        7 => 149,
        30 => 299
    ];

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    public static function boot()
    {
        parent::boot(); // TODO: Change the autogenerated stub
        //        static::addGlobalScope(new ActiveScope());
        //        static::addGlobalScope(new SoldScope());
    }

    protected $keyType = 'integer';

    /**
     * @var array
     */
    protected $fillable = ['name', 'street_address', 'city', 'zip', 'state', 'user_id', 'category_id', 
    'is_sold', 'shipping_size_id', 'has_shipping', 'description', 'status', 'price', 'sale_price',
     'location', 'google_address', 'postal_address', 'longitude', 'latitude', 'active', 'guid', 
     'created_at', 'updated_at', 'weight', 'height', 'IsSaved', 'ounces', 'parent_category_id',
    'length', 'width', 'policies', 'in_review', 'steps', 'shipment_type', 'condition','auctioned','state',
    'model', 'brand', 'stockcapacity', 'selling_now', 'listing', 'buyitnow', 'deliverd_domestic', 
    'deliverd_international', 'company','country', 'shipping_price', 'shipping_start', 'shipping_end',
    'return_shipping_price', 'return_ship_duration_limt', 'return_ship_paid_by', 'return_ship_location',
    'featured', 'featured_until','shipping_type', 'city', 'ratings_count','attributes','scheduled',
    'available_colors', 'shop_id','return_ship_duration_limt','return_ship_location','return_ship_paid_by'];
    
    protected $appends = ['cover_image', 'is_owner'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public static function defaultSelect()
    {
        return ['id', 'name', 'guid', 'price',  'location', 'is_sold','zip', 'state', 'weight','description','IsSaved' , 'has_shipping'];
    }   
    public static function getOfferProducts()
    {
        return ['id', 'name', 'guid', 'price', 'location', 'is_sold','zip', 'state', 'weight','description','IsSaved', 'has_shipping'];
    }   
    public static function getID()
    {
    }

    public static function getUser()
    {
        return ['id', 'name', 'profile_url'];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function saveseller()
    {
        return $this->hasMany(SaveSeller::class);
    }

    public function product_shipping_details()
    {
        // return $this->belongsTo(ProductShippingDetail::class);
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function productsAttributes()
    {

        return $this->hasMany(ProductsAttribute::class,);
    }


    public function savedUsers()
    {
        return $this->hasMany(SavedUsersProduct::class, 'product_id');
    }

    // public function Issaved(){
    //     return $this->load('savedUsers');
    // }

    public function withCategory()
    {
        return $this->load(['category' => function (BelongsTo $query) {
            $query->with('attributes');
        }]);
    }

    public function withProductsAttributes()
    {
        return $this->load('productsAttributes');
    }

    public function withUser()
    {
        return $this->load(['user' => function (BelongsTo $belongsTo) {
            $belongsTo->select(['id', 'name', 'profile_url', 'email']);
        }]);
    }

    public function isSaved()
    {
        return self::where("IsSaved", true)->get();
    }

    public function withDetails()
    {
//        return $this->load(['product_shipping_details' => function (BelongsTo $belongsTo) {
//            $belongsTo->select(['id', 'street_address', 'city', 'state', 'zip']);
//        }]);
    }

    public function cart()
    {
        return $this->hasMany(UserCart::class);
    }

    public function withoutScopesQuery()
    {
        return function (Builder $query) use (&$request) {
            $query->withoutGlobalScope(ActiveScope::class);
            $query->withoutGlobalScope(SoldScope::class);
        };
    }

    /**
     * this is temp fix for the Demo its should  be field in the product table
     * @return |null
     */
    public function getCoverImageAttribute()
    {
        $media = $this->media->first();
        if (!empty($media)) {
            return $media->url;
        }
        return null;
    }

    public function getIsOwnerAttribute()
    {
        if (Auth::guard('api')->check()) {

            return $this->user_id == Auth::guard('api')->id();
        }
        return false;
    }

    /**
     *User that method as in Trait so what so ever we also used in service just passing the same name in relation
     */
    public function attachOrDetachSaved()
    {
        if (Auth::check()) {
            $authenticatedUserId = \Auth::user()->id;

            $savedItem = $this->savedUsers()->where('user_id', $authenticatedUserId)->first();

            if (!empty($savedItem)) {
               $savedItem->delete();
               //return 'Product is Delete from WishList';
            }
            $savedUser = $this->savedUsers()->save(new SavedUsersProduct(["user_id" => $authenticatedUserId]));
            if($savedUser){
                return response()->json(['status'=> true,'message' => 'Product is Saved in WishList'], 200);       
            }else{
                return response()->json(['status'=> false,'message' => 'Unable to Saved in WishList'], 500);        
            }
        }
    }
    public function getIsSavedAttribute(): bool
    {
        if (auth('api')->check()) {
            return $this->savedUsers->contains('user_id', auth('api')->user()->id);
        }
        return false;
    }

    public function appendDetailAttribute()
    {
        $this->append(['isSaved']);
        return $this;
    }

    public function getPrice()
    {
        $user = Auth::user() ?? Auth::guard('api')->user();
        $offer = null;
        if ($user) {
            $offer = $this->offers()->where('requester_id', $user->id)
                ->where('status_name', Offer::$STATUS_ACCEPT)
                ->first();
        }

        return $offer ? $offer->price : $this->price;
    }

    public static function getFeaturedPrice($days)
    {
        return self::FEATURED_PRICES[$days];
    }

    public static function getHirePrice($days)
    {
        return self::HIRE_PRICES[$days];
    }

    public static function getByGuid(string $guid)
    {
        return self::where("guid", $guid)->firstOrFail();
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'product_id');
        // return $this->hasOne(Order::class, 'product_id');
    }
    public function shop()
    {
        return $this->belongsTo(SellerData::class, 'shop_id');
    }
    public function withShop()
    {
        return $this->load('shop');
    }
}
