<?php

namespace App\Http\Controllers\Api;

use App\Events\OfferMade;
use App\Helpers\StripeHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\GuidHelper;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Service;
use App\Models\ProductsAttribute;
use App\Models\User;
use App\Models\Fedex;
use App\Models\CategoryAttributes;
use App\Models\Attribute;
use App\Models\Message;
use App\Models\Order;
use App\Models\RecentView;
use App\Models\ProductRatings;
use App\Models\SavedUsersProduct;
use App\Models\ProductShippingDetail;
use App\Models\ShippingSize;
use App\Models\UserOfferProduct;
use App\Models\SaveAddress;
use App\Scopes\ActiveScope;
use App\Scopes\SoldScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OfferMadeNotification;
use App\Notifications\AddReview;
use App\Notifications\AdApproved;
use App\Notifications\TrustedSeller;
use App\Notifications\DepositReminder;

use Image;

class OfferUser {
    public $name;
    public $email;
    public $sender;
    public $price;
    public $product;
    public function routeNotificationFor()
    {
        return $this->email;
    }
}

class ProductController extends Controller
{
    //
    public function index(Request $request)
    {
        // why Product Categories whynot products ? @todo refactor it make it simple
        //        return ProductsCategories::with('products', 'categories')
        //            ->whereHas('products', function ($query) {
        //                $query->where('active', true);
        //            })->get();
        // $orders = Order::select('product_id')->get();
        // $productid = array();
        // foreach($orders as $order){
        //     array_push($productid, $order['product_id']);
        // }
        // $productid = array_unique($productid);
        // $productid = array_unique($productid);
        
        $products=Product::join('categories as categories','categories.id','=','products.category_id')
            ->where('products.active', true)
            // ->where('products.weight', '<>', null)
            ->where('products.price', '<>', null)
            ->with(['user'])
            ->with(['savedUsers'])
            ->where($this->applyFilters($request))
            ->where('products.is_sold', false)
            // ->where('products.IsSaved', true)
            ->orderByDesc('products.featured')
            ->orderByDesc('products.created_at')
            // ->paginate($this->pageSize, [
            //     'categories.name as category',
            //     'products.*'
            // ]);
            ->get([
                'categories.name as category',
                'products.*'
            ]);
        // $service = Service::join('categories as categories','categories.id','=','services.category_id')
        //     ->where('services.active', true)
        //     // ->where('products.weight', '<>', null)
        //     ->where('services.price', '<>', null)
        //     ->with(['user'])
        //     ->with(['savedUsers'])
        //     ->where($this->applyFilters($request))
        //     ->where('services.is_sold', false)
        //     ->orderByDesc('services.created_at')
        //     ->paginate($this->pageSize, [
        //         'categories.name as category',
        //         'services.*'
        //     ]);
        return $products; 
           /**
           * Below code for getting Products 
           * and services data and shown in same page
           * for Future Use
           */
            // return response()->json([
            //     'products' => $products,
            //     'services' => $service
            // ], 201);
            
            //return $service;//$products->merge($service);//array_merge(json_decode($products),json_decode($service));
    }

    public function recentView(Request $request){
        return RecentView::with(['products'])->orderBy('created_at', 'DESC')->get();
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }
    public function checkEmailReview($guid){
        // $user = User::where('id',$userID)->first();
        // $user->notify(new AddReview($user));
        
        $product = Product::where('guid', $guid)->first();
        
        if($product->in_review == false){
            $user = User::where('id',$product->user_id)->first();
            if($user->is_autoAdd){
                return "Your ad has been posted successfully";
            }else{
                return "Your Add has been Sent for Approval!";
            }

        }
    }
    public function self()
    {
        // return Product::where('user_id', \Auth::user()->id)
        //     ->with(['category', 'media'])
        //     ->withoutGlobalScope(ActiveScope::class)
        //     ->withoutGlobalScope(SoldScope::class)
        //     ->paginate($this->pageSize);
        return Product::join('categories as categories','categories.id','=','products.category_id')
        ->with(['media'])
        ->with(['savedUsers'])
        ->with(['user'])
        ->where('user_id', \Auth::user()->id)
        // ->where('products.weight', '<>', null)
        // ->where($this->applyFilters($request))
        // ->where('products.is_sold', false)
        ->withoutGlobalScope(ActiveScope::class)
        ->withoutGlobalScope(SoldScope::class)
        ->orderByDesc('products.featured')
        ->orderByDesc('products.created_at')
        ->paginate($this->pageSize, [
            'categories.name as category',
            'products.*'
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $active = false;
            $product = new Product();
            //temporary 1, for testing\
            $parentCategory =  Category::where('id',$request->get('category'))->first();
            $user = User::where('id', Auth::user()->id)->first();
            // $product->fill(ArrayHelper::merge($request->all(),
            //     [
            //         'name' => $request->get('title'),
            //         'category_id ' => '1',//$request->get('category'),
            //         'description ' => $request->get('description'),
            //         'price'=> $request->get('price'),
            //         'city'=> $request->get('city'),
            //         'state'=> $request->get('states'),
            //         'user_id' => Auth::user()->id,
            //         'status' => 'DRAFT',
            //         'height' => $request->get('ounces'),
            //         'width' => $request->get('ounces'),
            //         'length' => $request->get('ounces'),
            //         'weight' => $request->get('ounces'),
            //         'active' => $active
            //     ]
            // ));
            // $product->fill(
            //     [
            //         'name' => $request->get('title'),
            //         'category_id ' => '1',//$request->get('category'),
            //         'description ' => $request->get('description'),
            //         // 'price'=> $request->get('price'),
            //         // 'city'=> $request->get('city'),
            //         // 'state'=> $request->get('states'),
            //         // 'user_id' => Auth::user()->id,
            //         // 'status' => 'DRAFT',
            //         // 'height' => $request->get('ounces'),
            //         // 'width' => $request->get('ounces'),
            //         // 'length' => $request->get('ounces'),
            //         // 'weight' => $request->get('ounces'),
            //         // 'active' => $active
            //     ]
            // );
            $product->category_id = $request->get('category');
            $product->user_id = Auth::user()->id;//$request->get('user_id');
            $product->name = $request->get('title');
            $product->model = $request->get('model');
            $product->brand = $request->get('brand');
            $product->stockcapacity = $request->get('stockCapacity');
            $product->description = $request->get('description');
            $product->price = $request->get('price');
            $product->sale_price = $request->get('sale_price');
            $product->selling_now = $request->get('sellingNow');
            $product->auctioned = $request->get('auctions');
            $product->listing = $request->get('listing');
            $product->buyitnow = $request->get('buyitnow');
            $product->deliverd_domestic = $request->get('deliverddomestic');
            $product->deliverd_international = $request->get('deliverdinternational');
            $product->company = $request->get('deliverycompany');
            $product->country = $request->get('country');
            $product->city = $request->get('city');
            $product->state = $request->get('states');
            $product->shipping_price = $request->get('shippingprice');
            $product->shipping_start = $request->get('shippingstart');
            $product->shipping_end = $request->get('shippingend');
            $product->condition = $request->get('condition');
            $product->attributes= json_encode($request->get('sizes'));
            // $product->shipping_duration_limit = $request->get('shippingend');
            // $product->postal_address= $request->get('postal_address');
            $product->save();
            //@todo inherit attribute functionality
            // foreach ($request->get('attributes', []) as $attribute) {
                
            //     $data = [
            //         'attribute_id' => $attribute['id'],
            //         'product_id' => $product->id,
            //         'value' =>  $attribute['value']
            //     ];

            //     $productAttribute = new ProductsAttribute($data);
            //     $productAttribute->save();
            // }
           
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->genericResponse(true, 'Product Created', 200, ['product' => $product->withCategory()]);
    }
    public function getAttributes(Request $request, $categoryID)
    {
        return CategoryAttributes::where('category_id', $categoryID)
                ->with(["attribute" => function ($query) {
                    $query->select(Attribute::defaultSelect());
                }, "category" => function ($query) {
                    $query->select(Category::defaultSelect());
                }])->get();
    }
    public function getProductAttributes(Request $request, $productID)
    {   
        $product = Product::where('guid', $productID)->first();
        return ProductsAttribute::where('product_id', $product->id)
                ->with(["attribute" => function ($query) {
                    $query->select(Attribute::defaultSelect());
                }, "product" => function ($query) {
                    $query->select(Product::defaultSelect());
                }])->get();
    }
    
    /**
     * @param Product $product
     * @return Product
     */
    public function show(Product $product)
    {
        $product->price = $product->getPrice();

        return $product->withCategory()
            ->withProductsAttributes()
            ->appendDetailAttribute()
            ->withUser();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    public function getSavedAddress($guid)
    {
        $product = Product::where('guid', $guid)->first();
        $saveAddress = SaveAddress::where('user_id',Auth::user()->id)
                    ->where('product_id', $product->id)
                    ->first();
        if($saveAddress)
        {
            return $saveAddress;

        }else{
            return SaveAddress::where('user_id',Auth::user()->id)
                    ->get()
                    ->last();
        }
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        DB::beginTransaction();
        try {
//            if (!empty($request->size)) {
//                $shipping = new ShippingSize();
//                $shipping->size = $request->size;
//                $shipping->weight = $request->weight;
//                $shipping->save();
//                $product->shipping_size_id = $shipping->id;
//            }
//            if (empty($product->product_shipping_details_id)) {
//                $shipping_details = new ProductShippingDetail();
//                $shipping_details->city = $request->city;
//                $shipping_details->state = $request->state;
//                $shipping_details->zip = $request->zip;
//                $shipping_details->user_id = \Auth::user()->id;;
//                $shipping_details->street_address = $request->street_address;
//                $shipping_details->save();
//                $product->product_shipping_details_id = $shipping_details->id;
//            } else {
//                $shipping_details = ProductShippingDetail::where('id', $product->product_shipping_details_id)->first();
//                $shipping_details->city = $request->city;
//                $shipping_details->state = $request->state;
//                $shipping_details->zip = $request->zip;
//                $shipping_details->user_id = \Auth::user()->id;;
//                $shipping_details->street_address = $request->street_address;
//                $shipping_details->update();
// //            }
// return $request->all();
// die();
            $user = User::where('id',Auth::user()->id)->first();
            if($request->get('ounces')){
                $product->height =$request->get('ounces');
                $product->width =$request->get('ounces');
                $product->length =$request->get('ounces');
                $product->weight =$request->get('ounces');
            }
            // $product->status = 'Pendings';
            $product->fill($request->all())->update();

            $attributes = ($postedAttributes = $request->get('attributes')) ? array_combine(array_column($postedAttributes, 'id'), array_column($postedAttributes, 'value')) : [];
           
            if (!empty($attributes)) {
                
                // @TODO: create relations to avoid where query
                ProductsAttribute::where('product_id', $product->id)
                    ->get()
                    ->each(function (ProductsAttribute $attribute) use ($attributes) {
                        $attribute->value = $attributes[$attribute->attribute_id];
                        $attribute->save();
                    });
            }
            $product = Product::where('id', $product->id)->first(); 

            //Saving Address of user for Ad
            if($request->get('street_address')){
                // $user = User::where('id',Auth::user()->id)->first();
                $savedAddress = SaveAddress::where('user_id', Auth::user()->id)
                    ->where('product_id', $product->id)
                    ->first();
                
                if($savedAddress){
                    SaveAddress::where('user_id', Auth::user()->id)
                        ->where('product_id', $product->id)
                        ->update([
                            'user_id' => Auth::user()->id,
                            'product_id' => $product->id,
                            'address' =>$request->get('street_address'),
                            'city' => $request->get('city'),
                            'state' => $request->get('state'),
                            'zip' => $request->get('zip')
                        ]);
                }else{
                    $SaveAddress = new SaveAddress();
                    $SaveAddress->user_id= Auth::user()->id;
                    $SaveAddress->product_id= $product->id;
                    $SaveAddress->address= $request->get('street_address');
                    $SaveAddress->city= $request->get('city');
                    $SaveAddress->state= $request->get('state');
                    $SaveAddress->zip= $request->get('zip');
                    $SaveAddress->save();
                }
            }
            //For Step 2 without shipment
            if($product->in_review && $product->steps == '2'  && !$request->get('has_shipping'))
            {  
                // dump('not has shipping');
                $product->in_review = false; 
                $product->update();
                // $user = User::where('id',Auth::user()->id)->first();
                $account = StripeHelper::checkAccount($user);
                if($user->is_autoAdd == true){
                    $user->notify(new AdApproved($user, $product));
                }else{
                    $user->notify(new AddReview($user));
                }
            if(!$account->external_accounts->data){
                    if($product->shipment_type == '2' || $product->shipment_type == '3')
                    {
                        $user->notify(new DepositReminder($user));
                    }
                }
            //For Step 3 with shipment
            }else if($product->in_review && $product->steps == '3'  &&  $request->get('has_shipping') && $request->get('ounces')){
                
                // dump('has shipping');
                $product->in_review = false; 
                $product->update();
                // $user = User::where('id',Auth::user()->id)->first();
                $account = StripeHelper::checkAccount($user);
              
                if($user->is_autoAdd == true){
                    $user->notify(new AdApproved($user, $product));
                }else{
                    $user->notify(new AddReview($user));
                }

                if(!$account->external_accounts->data){
                    if($product->shipment_type == '2' || $product->shipment_type == '3')
                    {
                        $user->notify(new DepositReminder($user));
                    }
                }
                // if(!$user->isTrustedSeller)
                // {
                //     $user->notify(new TrustedSeller($user));
                // }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->genericResponse(true, "$product->name Updated", 200, ['product' => $product->withCategory()->withProductsAttributes()
            ->appendDetailAttribute()]);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Product::destroy($id);
        return response()->json(['message' => 'Product Deleted Successfully'], 200);
    }

    public function ratings($id, Request $request)
    {
        Product::where('guid', $id)->update(['ratings_count' => $request->get('ratings')]);
        $data = [
            'product_id' => $request->get('product_id'),
            'user_id' => $request->get('user_id'),
            'order_id' =>  $request->get('order_id')
        ];
        $productRatings = new ProductRatings($data);
        $productRatings->save();
        
        return response()->json(['message' => 'Thankyou for Rating'], 200);
    }

    public function checkRatings($productId, $userId, $orderId, Request $request)
    {
        return ProductRatings::where('product_id', $productId)
                    ->where('user_id', $userId)
                    ->where('order_id', $orderId)->first();
        // return response()->json(['message' => 'Product Updated Successfully'], 200);
    }
    
    public function media(Product $product, Request $request)
    {
        return $product->images();
    }
    public function upload_(Product $product, Request $request)
    {
        if($request->hasFile('file')) {
            $image = Image::make($request->file('file'));
            /**
             * Main Image Upload on Folder Code
             */
            $imageName = time().'-'.$request->file('file')->getClientOriginalName();
            $destinationPath = public_path('image/');
            // $image->resize(1024,1024);
            $image->resize(1024, 1024, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $image->save($destinationPath.$imageName);
        }
    }
    public function imageResize($image)
    {
        $image = Image::make($image);

        return $image->resize(1024, 1024, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    }
    /**
     * @param Product $product
     * @param Request $request
     * @return array|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function upload(Product $product, Request $request)
    {
        return DB::transaction(function () use (&$request, &$product) {
            $file =$request->file('file');
            
            $extension = $file->getClientOriginalExtension();
            $guid = GuidHelper::getGuid();
            $path = User::getUploadPath() . StringHelper::trimLower(Media::PRODUCT_IMAGES);
            $name = "{$path}/{$guid}.{$extension}";
            $media = new Media();
            $media->fill([
                'name' => $name,
                'extension' => $extension,
                'type' => Media::PRODUCT_IMAGES,
                'user_id' => \Auth::user()->id,
                'product_id' => $product->id,
                'active' => true,
            ]);

            $media->save();
            
            $image = Image::make($request->file('file'));
            $image->orientate();
            $image->resize(1024, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
           });
            //email:flexehome123@gmail.com
            //[pass:123flexeaccount_
            //users/5/product
            $image->stream();
            // dd($image);
            // Storage::putFileAs('public/watermarked/', $watermarkedImage, $fileName . $extension);
            // Storage::put('public/watermarked/' . $fileName . $extension, $watermarkedImage->encode());
            Storage::put('public/'. $name, $image->encode());
            // Storage::put('public/'. $path, $image, 'public');
            // Storage::disk('local')->put('public/'. $path .'/'.$name, $image, 'public');
            // Storage::putFileAs(
            //     'public/' . $path,
            //     $image,
            //     "{$guid}.{$extension}"
            // );
            return [
                'uid' => $media->id,
                'name' => $media->url,
                'status' => 'done',
                'url' => $media->url,
                'guid' => $media->guid
            ];
        });
    }

    public function search(Request $request)
    {
        if ($request->get('lat') && $request->get('lng')) {

            $latitude = abs($request->get('lat'));
            $longitude = abs($request->get('lng'));
        
            $products = Product::where('active', true)->where('is_sold', false)
            ->where('IsSaved', true)
            ->with(['savedUsers'])
            ->with(['user'])
            ->where('name', 'LIKE', "%{$request->get('query')}%")
            ->when($request->has('min_price'), function ($query) use ($request) {
                    $min_price = $request->get('min_price');
                    $max_price = $request->get('max_price');
                    if ($max_price) {
                        $query->whereBetween('price', [$min_price, $max_price]);
                    } else {
                        $query->where('price', ">", $min_price);
                    }
                })->with('order', function ($query) {
                    $query->where('buyer_id', '=', Auth::guard('api')->id());
                })
                ->when($request->get('category_id'), function (Builder $builder, $category) use ($request) {
                    // $builder->where('parent_category_id', $category);
                    $builder->where('category_id', $category);
                    $builder->where('is_sold', false);
                    $builder->where('IsSaved', true);
                    $builder->where('active', true)
                    // $builder->where('category_id', $category)
                        ->when(json_decode($request->get('filters'), true), function (Builder $builder, $filters) {
                            $having = [];
    
                            foreach ($filters as $id => $value) {
                                if (is_bool($value)) {
                                    $value = $value ? 'true' : 'false';
                                }
    
                                if (is_array($value)) {
                                    $value = implode('","', $value);
                                    $having[] = "sum(case when products_attributes.attribute_id = $id and json_overlaps(products_attributes.value, '[\"$value\"]') then 1 else 0 end) > 0";
                                } else {
                                    $having[] = "sum(case when products_attributes.attribute_id = $id and json_contains(products_attributes.value, '\"$value\"') then 1 else 0 end) > 0";
                                }
                            }
    
                            $having = implode(' and ', $having);
                            $builder->whereRaw("
                                id in
                                (select products.id
                                from products
                                inner join products_attributes on products.id = products_attributes.product_id
                                group by products.id
                                having $having)
                            ");
                        });
                })
                ->orderBy(DB::raw("3959 * acos( cos( radians({$latitude}) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(-{$longitude}) ) + sin( radians({$latitude}) ) * sin(radians(latitude)) )"), 'DESC')
                ->orderByDesc('featured')
                ->orderByDesc('created_at')
                ->get();
        } else {
            // $products = Product::where('active', true)->where('is_sold', false)
            //     ->where('name', 'LIKE', "%{$request->get('query')}%")
            //     ->where('parent_category_id', $request->get('category_id'))
            //     ->where('category_id', $request->get('category_id'))
            //     ->when($request->has('min_price'), function ($query) use ($request) {
            //         $min_price = $request->get('min_price');
            //         $max_price = $request->get('max_price');
            //         if ($max_price) {
            //             $query->whereBetween('price', [$min_price, $max_price]);
            //         } else {
            //             $query->where('price', ">", $min_price);
            //         }
            //     })
            //     ->with('order', function ($query) {
            //         $query->where('buyer_id', '=', Auth::guard('api')->id());
            //     })  
            //     // ->when($request->get('category_id'), function (Builder $builder, $category) use ($request) {
            //     //     // $builder->orWhere('category_id', $category);
            //     //     return $category;
            //     // });
            //     ->distinct()
            //     ->orderByDesc('featured')
            //     ->orderByDesc('created_at')
            //     ->get();
            $products = Product::where('active', true)->where('is_sold', false)
                ->where('IsSaved', true)
                ->with(['savedUsers'])
                ->with(['user'])
                ->where('name', 'LIKE', "%{$request->get('query')}%")
                ->when($request->has('min_price'), function ($query) use ($request) {
                    $min_price = $request->get('min_price');
                    $max_price = $request->get('max_price');
                    if ($max_price) {
                        $query->whereBetween('price', [$min_price, $max_price]);
                    } else {
                        $query->where('price', ">", $min_price);
                    }
                })->with('order', function ($query) {
                    $query->where('buyer_id', '=', Auth::guard('api')->id());
                })  
                ->when($request->get('category_id'), function (Builder $builder, $category) use ($request) {
                    // $builder->where('parent_category_id', $category);
                    $builder->where('is_sold', false);
                    $builder->where('IsSaved', true);
                    // $builder->where('is_sold', false);
                    $builder->where('active', true);
                    $builder->where('category_id', $category)
                    // $builder->where('category_id', $category)
                    // $builder->where('category_id', $category)
                    // $builder->where('category_id', $category)
                        ->when(json_decode($request->get('filters'), true), function (Builder $builder, $filters) {
                            $having = [];
    
                            foreach ($filters as $id => $value) {
                                if (is_bool($value)) {
                                    $value = $value ? 'true' : 'false';
                                }
    
                                if (is_array($value)) {
                                    $value = implode('","', $value);
                                    $having[] = "sum(case when products_attributes.attribute_id = $id and json_overlaps(products_attributes.value, '[\"$value\"]') then 1 else 0 end) > 0";
                                } else {
                                    $having[] = "sum(case when products_attributes.attribute_id = $id and json_contains(products_attributes.value, '\"$value\"') then 1 else 0 end) > 0";
                                }
                            }
    
                            $having = implode(' and ', $having);
                            // $builder->whereRaw("
                            //     id in
                            //     (select products.id
                            //     from products
                            //     inner join products_attributes on products.id = products_attributes.product_id
                            //     right join categories on products.category_id = categories.id
                            //     group by products.id
                            //     having $having)
                            // ");
                            $builder->whereRaw("
                                id in
                                (select products.id
                                from products
                                inner join products_attributes on products.id = products_attributes.product_id
                                right join categories on products.category_id = categories.id
                                group by products.id
                                having $having)
                            ");
                        });
                })
                ->distinct()
                ->orderByDesc('featured')
                ->orderByDesc('created_at')
                ->get();
        }

        $category = Category::when($request->get('category_id'), function (Builder $builder, $category) {
            $builder->where('id', $category)
                ->with('attributes');
        })
            ->where('type', Category::PRODUCT)
            ->get();

        $categories = Category::with('attributes')->where('type', Category::PRODUCT)->get();
        return [
            'results' => $products,
            'categories' => $categories,
            'category' => $category
        ];
    }

    /**
     * Saved user products
     * @param Product $product
     * @param Request $request
     */
    public function Saved(Product $product, Request $request)
    {
        return $product->attachOrDetachSaved();
    }

    /*
     * @param Product $product
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function offer(Product $product, Request $request)
    {
        $offer = $request->get('offer');
        $chat_id;

        // optimize move this into the request
        if ($offer >= $product->price) {
            throw new \Exception('Your offer should be less than the prduct price.');
        }

        if ($offer <= 0) {
            throw new \Exception('Your offer is invalid.');
        }

        $sender = Auth::user();
        $recipient = $product->user;
       
        if ($sender->id === $recipient->id) {
            throw new \Exception('Unable to make an offer on your own product');
        }

        if ($sender->id > $recipient->id) {
            $chat_id = $recipient->id.$sender->id;
        } else {
            $chat_id = $sender->id.$recipient->id;
        }

        $message = new Message();
        $message->sender_id = $sender->id;
        $message->product_id = $product->guid;
        $message->chat_id = $chat_id;   
        $message->recipient_id = $recipient->id;
        $message->data = $sender->name . ' has made an offer of ' . $offer . ' for ' . $product->name;
        $message->notifiable_id = $product->id;
        $message->notifiable_type = Product::class;
        $message->save();

        Offer::request($product, $offer);

        OfferMade::trigger($recipient);

        $notifiable_user = new OfferUser();
        $notifiable_user->name = $recipient->name;
        $notifiable_user->email = $recipient->email;
        $notifiable_user->sender = $sender->name;
        $notifiable_user->price = $offer;
        $notifiable_user->product = $product->name;

        $userofferproduct = new UserOfferProduct();
        $userofferproduct->userId = $sender->id;
        $userofferproduct->productId = $product->id;
        $userofferproduct->status = '1';
        $userofferproduct->save();

        $recipient->notify(new OfferMadeNotification($notifiable_user));

        return $this->genericResponse(true, 'Offer made successfully.');
    }

    public function getSaved()
    {
        if (Auth::check()) {

            $user = User::where('id', Auth::user()->id)->with('savedProducts')
            // ->with('savedServices')
            ->first();
           
            // $data = array_merge(json_decode($user->savedProducts));//, json_decode($user->savedServices));
            // return $user->savedServices;
            return response()->json([
                'user' => Auth::user()->id,
                'data' => $user->savedProducts,
            ], 200);
        }
    }
    public function getSaveByUser()
    {
        if (Auth::check()) {
            // return SavedUsersProduct::where('user_id', Auth::user()->id)->get();
            return SavedUsersProduct::get();
        }
    }
    public function deleteMedia(Media $media)
    {
        if (Auth::user()->id == $media->user_id) {
            Storage::delete($media->name);
            $media->delete();
        }
    }

    public function getBuyingOffers()
    {
        
        $user = Auth::user();
        // return Offer::where("requester_id","=",Auth::guard('api')->id())
        // // ->leftJoin('orders','offers.id','=','orders.offer_id')
        // ->where('status_name', Offer::$STATUS_NEW_REQUEST)
        // ->with(["product"=>function (BelongsTo $hasMany){
        //     $hasMany->select(Product::defaultSelect());
        // }, "user" => function (BelongsTo $hasMany) {
        //     $hasMany->select(Product::getUser());
        // }])
        // ->get();
        return Offer::where("requester_id","=",Auth::guard('api')->id())
        // ->where('status_name', Offer::$STATUS_NEW_REQUEST)
        ->whereHas('product', function($query){
            $query->where('is_sold','=', false);
        })->with(["product" => function (BelongsTo $hasMany) {
                $hasMany->select(Product::defaultSelect());
                }, "user" => function (BelongsTo $hasMany) {
                    $hasMany->select(Product::getUser());
        }])->get();
            
    }

    public function getOrderdProduct(Request $request)
    {
        return DB::table('orders')
        ->select('*')
        ->join('offers','orders.offer_id','=','offers.id')
       ->where('offers.status_name','=','Accepted')
        ->get();
    }

    public function getSellingOffers()
    {
        $user = Auth::user();
        return $user->sellingOffers()        
        ->where('status_name', Offer::$STATUS_NEW_REQUEST)
        ->whereHas('product', function($query){
            $query->where('is_sold','=', false);
        })->with(["product" => function (BelongsTo $hasMany) {
            $hasMany->select(Product::defaultSelect());
        }, "requester" => function (BelongsTo $hasMany) {
            $hasMany->select(User::defaultSelect());
        }])->get();

        // return Offer::where("user_id","=",Auth::guard('api')->id())
        // // ->with(["product"=>function(BelongsTo $hasMany){
        // //     $hasMany->select(Product::defaultSelect());
        // // }, "user" => function (BelongsTo $hasMany) {
        // //     $hasMany->select(Product::getUser());
        // // }])
        // ->get();

    }
    

    public function feature(Product $product, Request $request)
    {
        $stripe = new StripeClient(env('STRIPE_SK'));
        $paymentIntent = $stripe->paymentIntents->retrieve($request->get('payment_intent'));

        $days = $request->get('days');
        if (
            $paymentIntent->id === $request->get('payment_intent') &&
            $paymentIntent->status === 'succeeded' &&
            $paymentIntent->amount === (Product::getFeaturedPrice($days) * 100)
        ) {
            $product->featured = true;
            $product->featured_until = Carbon::today()->addDays($days);
            $product->update();
        }

        return $product;
    }
    public function userRating($id)
    { 
        $userRating = Product::where('user_id', $id)->get();
        // $ratingCount = $userRating->avg('ratings_count');
        $ratingsCount = [];
        foreach($userRating as $key=> $rating){
            if($rating->ratings_count){
                array_push($ratingsCount, $rating->ratings_count);
            }
        }
        if($ratingsCount){
            $rateCount = count($ratingsCount);
            $rateSum = array_sum($ratingsCount);
            $rateTotal = $rateSum/$rateCount;
            return $rateTotal;
        }else{
            return 0;
        }
    }
    public function hire(Product $product, Request $request)
    {
        $stripe = new StripeClient(env('STRIPE_SK'));
        $paymentIntent = $stripe->paymentIntents->retrieve($request->get('payment_intent'));

        $days = $request->get('days');
        if (
            $paymentIntent->id === $request->get('payment_intent') &&
            $paymentIntent->status === 'succeeded' &&
            $paymentIntent->amount === (Product::getHirePrice($days) * 100)
        ) {
            $product->hired = true;
            $product->hired_until = Carbon::today()->addDays($days);
            $product->update();
        }

        return $product;
    }
    public function checkUserProductOffer($id, $guid){

        $product = Product::where('guid', $guid)->first();
        $user = User::where('id', $id)->first();
        $offer = Offer::where('requester_id', $user->id)
            ->where('product_id', $product->id)
            ->first();
        return $offer;

    }
}
