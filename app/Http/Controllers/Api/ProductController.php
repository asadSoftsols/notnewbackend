<?php

namespace App\Http\Controllers\Api;

use App\Events\OfferMade;
use App\Helpers\StripeHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\GuidHelper;
use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\DeliverCompany;
use App\Models\Stock;
use App\Models\InStock;
use App\Models\OutStock;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Service;
use App\Models\SellerData;
use App\Models\SaveSearch;
use App\Models\ProductsAttribute;
use App\Models\ProductAttributes;
use App\Models\User;
use App\Models\RecentUserView;
use App\Models\Countries;
use App\Models\City;
use App\Models\State;
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
use App\Images;
use Image;
use File;

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
        // 2024-03-22 00:53:00
        // return Carbon::now()->format('Y-m-d h:m:s');
        // die();
        // return Carbon::now()->format('Y-m-d');
        // die();
        $productNormal=Product::join('categories as categories','categories.id','=','products.category_id')
            ->where('products.active', true)
            // ->where('products.weight', '<>', null)
            ->where('products.price', '<>', null)
            ->with(['user'])
            ->with(['media'])
            ->with(['savedUsers'])
            ->with(['shop'])
            ->where($this->applyFilters($request))
            ->where('products.is_sold', false)
            // ->where('products.listing','<>','0000-00-00 00:00:00')
            // ->whereDate('products.listing','>=',Carbon::now()->format('Y-m-d'))
            ->where('products.listing', '<=', date('Y-m-d'))
            // ->orwhere('products.auction_End_listing' ,'>=', today())
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
        $productAuctioned=Product::join('categories as categories','categories.id','=','products.category_id')
            ->where('products.active', true)
            // ->where('products.weight', '<>', null)
            ->where('products.price', '<>', null)
            ->with(['user'])
            ->with(['media'])
            ->with(['savedUsers'])
            ->with(['shop'])
            ->where($this->applyFilters($request))
            ->where('products.is_sold', false)
            // ->where('products.auction_listing', '<>', '0000-00-00 00:00:00')
            ->where('products.auction_listing','<=', Carbon::now()->format('Y-m-d h:m:s'))
            // ->orwhere('products.auction_End_listing' ,'>=', today())
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
            $product = array_merge(json_decode($productNormal), json_decode($productAuctioned));
            if($product){
                return response()->json(['status'=> true,'data' =>$product], 200);       
            }else{
                return response()->json(['status'=> false,'data' =>"Unable To Get Products"], 400);       
            }
            
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
        $recentProducts = RecentView::with(['products'])
        ->orderBy('created_at', 'DESC')->get();
        $userProducts = [];
        foreach($recentProducts as $recentProduct){
            //    $getRecent =  
            array_push($userProducts, $recentProduct);
        }
        return $userProducts;
    }
    public function recentUserView(Request $request){
        $userProduct = RecentUserView::where('user_id', \Auth::user()->id)
            ->with(['user'])
            ->with(['recent'])
            ->with(['product'])
            ->get();
        // $recent =  RecentView::with(['products'])->orderBy('created_at', 'DESC')->get();
        // $products = [];
        // foreach($recent as $rcent){
        //     array_push($products, $rcent->products);
        // }
        $product = [];
        foreach($userProduct as $pro){
            // if($pro->user_id == \Auth::user()->id){
                array_push($product, $pro->product);  
            // }
            //   
        }
        if($product){
            return response()->json(['status'=>'true','data'=>$product],200);
        }else{
            return response()->json(['status'=>'false','message'=>$product],500);
        }
    }
    public function inStock(Request $request){
        
        $stockIn = InStock::with('products')
            ->where('user_id', \Auth::user()->id)->get();

        if($stockIn){
            return response()->json(['status'=>'true','data'=>$stockIn],200);
        }else{
            return response()->json(['status'=>'false','message'=>'Unable to Get Inventory!'],500);
        }
    }
    public function outStock(Request $request){
        $stockOut = OutStock::with('products')
            ->where('user_id', \Auth::user()->id)->get();
        if($stockOut){
            return response()->json(['status'=>'true','data'=>$stockOut],200);
        }else{
            return response()->json(['status'=>'false','message'=>'Unable to Get Inventory!'],500);
        }

    }

    public function deleteRecent(Request $request){
        $delete = RecentUserView::where('user_id', \Auth::user()->id)->delete();
        if($delete){
            return response()->json(['status'=>'true','data'=>"Recent view has been CLeared!"],200);
        }else{
            return response()->json(['status'=>'false','message'=>'Unable to CLeared Recent view!'],500);
        }
    }
    public function createUserRecientView(Request $request){
            $product = Product::where('guid',$request->get('id'))->first();
            $recentview = RecentView::orderby('id','desc')->first();
            $recentuserview =new  RecentUserView();
            $recentuserview->recent_view_id = $recentview->id;
            $recentuserview->product_id = $product->id;
            $recentuserview->user_id = \Auth::user()->id;
            $recentuserview->save();
    }
    public function createRecentView(Request $request){
        DB::beginTransaction();
        try{
            $product = Product::where('guid',$request->get('id'))->first();

            RecentView::where('product_id',$product->id)->delete();
            // RecentView::with(['products'])->orderBy('created_at', 'DESC')->get();
            $recentview = new RecentView();
            $recentview->product_id = $product->id;
            $recentview->save();
           DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
        ->with(['shop'])
        ->where('user_id', \Auth::user()->id)
        // ->where('products.weight', '<>', null)
        // ->where($this->applyFilters($request))
        // ->where('products.is_sold', false)
        ->withoutGlobalScope(ActiveScope::class)
        ->withoutGlobalScope(SoldScope::class)
        ->orderByDesc('products.featured')
        ->orderByDesc('products.created_at')
        ->get([
            'categories.name as category',
            'products.*'
        ]);
        // ->paginate($this->pageSize, [
        //     'categories.name as category',
        //     'products.*'
        // ]);
    }
    public function selfItems(Request $request, $status)
    {
        // return Product::where('user_id', \Auth::user()->id)
        //     ->with(['category', 'media'])
        //     ->withoutGlobalScope(ActiveScope::class)
        //     ->withoutGlobalScope(SoldScope::class)
        //     ->paginate($this->pageSize);
        if($status=="active"){
            $product=  Product::join('categories as categories','categories.id','=','products.category_id')
            ->with(['media'])
            ->with(['savedUsers'])
            ->with(['user'])
            ->where('user_id', \Auth::user()->id)
            ->where('products.active', true)
            // ->where('products.weight', '<>', null)
            // ->where($this->applyFilters($request))
            // ->where('products.is_sold', false)
            ->withoutGlobalScope(ActiveScope::class)
            ->withoutGlobalScope(SoldScope::class)
            ->orderByDesc('products.featured')
            ->orderByDesc('products.created_at')
            // ->paginate($this->pageSize, [
            //     'categories.name as category',
            //     'products.*'
            // ]);
            ->get(
                [
                'categories.name as category',
                'products.*'
                ]
            );
            if($product){
                return response()->json(['status'=>'true','message'=>'Product Finds','data'=>$product],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Create Product!'],403);
            }
        }else if($status=="inactive"){
            // return Product::join('categories as categories','categories.id','=','products.category_id')
            $product = Product::join('categories as categories','categories.id','=','products.category_id')
            ->with(['media'])
            ->with(['savedUsers'])
            ->with(['user'])
            ->where('user_id', \Auth::user()->id)
            ->where('products.active', false)
            // ->where('products.weight', '<>', null)
            // ->where($this->applyFilters($request))
            // ->where('products.is_sold', false)
            ->withoutGlobalScope(ActiveScope::class)
            ->withoutGlobalScope(SoldScope::class)
            ->orderByDesc('products.featured')
            ->orderByDesc('products.created_at')
            // ->paginate($this->pageSize, [
            //     'categories.name as category',
            //     'products.*'
            // ]);
            ->get(
                [
                'categories.name as category',
                'products.*'
                ]
            );
            
            if($product){
                return response()->json(['status'=>'true','message'=>'Product Finds','data'=>$product],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Create Product!'],403);
            }
        }else if($status=="scheduled"){
            // return Product::join('categories as categories','categories.id','=','products.category_id')
            // ->with(['media'])
            // ->with(['savedUsers'])
            // ->with(['user'])
            // ->where('scheduled', true)
            // ->where('user_id', \Auth::user()->id)
            // // ->where('products.weight', '<>', null)
            // // ->where($this->applyFilters($request))
            // // ->where('products.is_sold', false)
            // ->withoutGlobalScope(ActiveScope::class)
            // ->withoutGlobalScope(SoldScope::class)
            // ->orderByDesc('products.featured')
            // ->orderByDesc('products.created_at')
            // // ->paginate($this->pageSize, [
            // //     'categories.name as category',
            // //     'products.*'
            // // ]);
            // ->get(
            //     [
            //     'categories.name as category',
            //     'products.*'
            //     ]
            // );
            $productNormal=Product::join('categories as categories','categories.id','=','products.category_id')
            ->where('products.active', true)
            // ->where('products.weight', '<>', null)
            ->where('products.price', '<>', null)
            ->with(['user'])
            ->with(['media'])
            ->with(['savedUsers'])
            ->with(['shop'])
            ->where($this->applyFilters($request))
            ->where('products.is_sold', false)
            ->where('products.listing','<>',null)
            ->orwhere('products.listing','>=',today())
            // ->orwhere('products.auction_End_listing' ,'>=', today())
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
        $productAuctioned=Product::join('categories as categories','categories.id','=','products.category_id')
            ->where('products.active', true)
            // ->where('products.weight', '<>', null)
            ->where('products.price', '<>', null)
            ->with(['user'])
            ->with(['media'])
            ->with(['savedUsers'])
            ->with(['shop'])
            ->where($this->applyFilters($request))
            ->where('products.is_sold', false)
            ->where('products.auction_listing', '<>', null)
            ->orwhere('products.auction_listing', '>=', today())
            // ->orwhere('products.auction_End_listing' ,'>=', today())
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
            $product = array_merge(json_decode($productNormal), json_decode($productAuctioned));
            if($product){
                return response()->json(['status'=>'true','message'=>'Product Finds','data'=>$product],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Create Product!'],403);
            }
        }else{
            $product = Product::join('categories as categories','categories.id','=','products.category_id')
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
            // ->paginate($this->pageSize, [
            //     'categories.name as category',
            //     'products.*'
            // ]);
            ->get(
                [
                'categories.name as category',
                'products.*'
                ]
            );
            if($product){
                return response()->json(['status'=>'true','message'=>'Product Finds','data'=>$product],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Create Product!'],403);
            }
        }
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
            /**
             * For Product Starts
             */
            $active = false;
            $product = new Product();
            $user = User::where('id', Auth::user()->id)->first();
            // $country = Countries::where('id', $request->get('country'))->first();
            // $states = State::where('id', $request->get('state'))->first();
            // $city = City::where('id', $request->get('city'))->first();
            $store = SellerData::where('user_id',Auth::user()->id)->first();

            $product->user_id = Auth::user()->id;
            $product->name = $request->get('title');
            $product->condition = $request->get('condition');
            $product->model = $request->get('model');
            $product->category_id = $request->get('category');
            $product->brand = $request->get('brand');
            $product->stockcapacity = $request->get('stockCapacity');
            $product->attributes = $request->get('sizes');
            $product->available_colors = json_encode($request->get('availableColors'));
            $product->description = $request->get('description');
            $sellingNow = 0;
            if($request->get('sellingNow') == 'true'){
                $sellingNow = 1;
                $product->listing = $request->get('listing');
            }
            $product->selling_now = $sellingNow;
            $product->price = $request->get('price');
            $product->sale_price = $request->get('saleprice');
            $product->min_purchase = $request->get('minpurchase');
            $auctioned = 0;
            if($request->get('auctioned') == 'true'){
                $auctioned = 1;
                $product->bids = $request->get('bids');
                $product->auction_listing = $request->get('auctionListing');
                $product->auction_End_listing = $request->get('auctionEndListing');
            }
            $product->auctioned = $auctioned;
            $product->durations = $request->get('durations');
            $deliverdDomestic = 0;
            if($request->get('deliverddomestic') == 'true'){
                $deliverdDomestic = 1;
            }
            $product->deliverd_domestic = $deliverdDomestic;
            $product->tags = json_encode($request->get('tags'));
            $deliverdInternational = 0;
            if($request->get('deliverdinternational') == 'true'){
                $deliverdInternational = 1;
            }
            $product->deliverd_international = $deliverdInternational;
            $product->delivery_company = $request->get('deliverycompany');
            $product->is_sold = false;
            $product->google_address = $request->get('google_address');
            $product->postal_address = $request->get('postal_address');
            $product->street_address = $request->get('street_address');
            $product->latitude = $request->get('latitude');
            $product->longitude = $request->get('longitude');
            $product->country = $request->get('country');
            $product->city = $request->get('city');
            $product->state = $request->get('state');
            $product->IsSaved = true;
            $product->shipping_price = $request->get('shippingprice');
            $product->shipping_start = $request->get('shippingstart');
            $product->shipping_end = $request->get('shippingend');
            $product->return_shipping_price =  $request->get('returnshippingprice');
            $product->return_ship_duration_limt = $request->get('returndurationlimit');
            $product->return_ship_paid_by = $request->get('returnshippingpaidby');
            $product->return_ship_location = $request->get('returnshippinglocation');
            $product->shop_id = $store->id;
            $product->save();
        
            /**
             * For Product Ends
             */

            /**
             * For Images Uploading Start
             */
            $imageName = [];
            if($request->hasFile('file')){
                foreach ($request->file('file') as $file) {
                    $extension = $file->getClientOriginalExtension();
                    $guid = GuidHelper::getGuid();
                    // $path = User::getUploadPath(Auth::user()->id) . StringHelper::trimLower(Media::PRODUCT_IMAGES);
                    $path = 'images/'.$entity::PRODUCT_IMAGES.'/'.Auth::user()->id;
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
                    $image->stream();
                    $request->file->move($path, "{$guid}.{$extension}");                
                }
            }
            /**
             * For Images Uploading End
             */

            /**
             * For Product Attributes Start
             */
            $sizes = json_decode($request->get('sizes'));
            foreach($sizes as $size){
                foreach($size as $key => $siz){
                    $productattributes =new ProductAttributes();
                    $productattributes->name=$key;
                    $productattributes->value=$siz;
                    $productattributes->product_id=$product->id;
                    $productattributes->save();
                }
            }
            /**
             * For Product Attributes Ends
             */

             /**
              * Stock Starts
              */
            //   store
              $stock = new Stock();
              $stock->user_id = Auth::user()->id;
              $stock->guid = GuidHelper::getGuid();
              $stock->product_id = $product->id;
              $stock->quantity = $request->get('stockCapacity');
              $stock->save();

              $instock = new InStock();
              $instock->user_id = Auth::user()->id;
              $instock->guid = GuidHelper::getGuid();
              if($request->get('auctioned') == 'true'){
                $instock->listingdate = $request->get('auctionListing');
              }else if($request->get('sellingNow') == 'true'){
                $instock->listingdate = $request->get('listing');
              }
              $instock->productid = $product->id;
              $instock->quantity = $request->get('stockCapacity');
              $instock->save();

              /**
               * Stock Ends
            */
            if($product){
                return response()->json(['status'=>'true','data'=>"Product has been Created!"],200);
            }else{
                return response()->json(['status'=>'false','message'=>'Unable to Create Product!'],403);
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        // return $this->genericResponse(true, 'Product Created', 200, ['product' => $product->withCategory()->withShop()->withAttributes()]);
    }
    public function Imgupload(Request $request){
        return DB::transaction(function () use (&$request) {
            $active = false;
            $product = new Product();

            $user = User::where('id', Auth::user()->id)->first();
            $store = SellerData::where('user_id',Auth::user()->id)->first();

            $product->user_id = Auth::user()->id;
            $product->name = 'title';
            $product->condition = 'condition';
            $product->model = 'model';
            $product->category_id = '1';
            $product->brand = 'brand';
            $product->stockcapacity = 0;
            $product->attributes = json_encode(['sizes']);
            $product->available_colors = json_encode(['availableColors']);
            $product->description = 'description';
            $product->selling_now = false;
            $product->price = 0;
            $product->sale_price = 0;
            $product->min_purchase = 1;
            $product->listing = "listing";
            $product->auctioned = 'auctions';
            $product->bids = 0;
            $product->durations = 0;
            $product->auction_listing = 'auctionListing';
            $product->deliverd_domestic = false;
            $product->tags = json_encode(['tags']);
            $product->deliverd_international = false;
            $product->delivery_company = 'deliverycompany';
            $product->is_sold = false;
            $product->street_address = "";//$request->get('street_address');//for later when google address will be implement
            $product->country = 'countryname';
            $product->city = 'cityname';
            $product->state = 'statesname';
            $product->IsSaved = true;
            $product->shipping_price = 0;
            $product->shipping_start = '2024-02-29 17:09:48';
            $product->shipping_end = '2024-02-29 17:09:48';
            $product->return_shipping_price =  0;
            $product->return_ship_duration_limt = 0;
            $product->return_ship_paid_by = 'admin';
            $product->return_ship_location = 'local';
            $product->shop_id = $store->id;
            $product->save();
            $file =$request->file('images');
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
                'category_id' => $product->category_id,
                'active' => true,
            ]);

            $media->save();
            
            $image = Image::make($request->file('images'));
            $image->orientate();
            $image->resize(1024, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
        });
            $image->stream();
            Storage::put('public/'. $name, $image->encode());

            return [
                'uid' => $media->id,
                'name' => $media->url,
                'status' => 'done',
                'url' => $media->url,
                'guid' => $media->guid,
                'product'=>$product->guid,
            ];
        }); 
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
            // ->withAttributes()
            ->withShop()
            // ->withBids()
            // ->appendDetailAttribute()
            ->withUser();
    }
    public function getTrendingProduct($id){
        $product =  Product::where('guid', $id)->first();
        $storeId = SellerData::where('user_id', $product->user_id)->first();
        return Product::where('shop_id', $storeId->id)
            ->where('is_sold', 1)
            ->count();
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
        return DB::transaction(function () use ($request, $product) { 
            // $country = Countries::where('id', $request->get('country'))->first();
            // $states = State::where('id', $request->get('states'))->first();
            // $city = City::where('id', $request->get('city'))->first();
            $user = User::where('id',Auth::user()->id)->first(); 
            $store = SellerData::where('user_id',Auth::user()->id)->first();
            $sellingNow = 0;
            if($request->get('sellingNow') == 'true'){
                $sellingNow = 1;
            }
            $auctioned = 0;
            if($request->get('auctioned') == 'true'){
                $auctioned = 1;
            }
            $deliverdDomestic = 0;
            if($request->get('deliverddomestic') == 'true'){
                $deliverdDomestic = 1;
            }
            $deliverdInternational = 0;
            if($request->get('deliverdinternational') == 'true'){
                $deliverdInternational = 1;
            }
           $products = Product::where('id', $product->id)->update([
                "user_id" => Auth::user()->id,
                "name" => $request->get('title'),
                "condition" => $request->get('condition'),
                "model" => $request->get('model'),
                "category_id" => $request->get('category'),
                "brand" => $request->get('brand'),
                "stockcapacity" => $request->get('stockCapacity'),
                "attributes" => $request->get('sizes'),
                "available_colors" => json_encode($request->get('availableColors')),
                "description" => $request->get('description'),
                "selling_now" => $sellingNow,
                "price" => $request->get('price'),
                "sale_price" => $request->get('saleprice'),
                "min_purchase" => $request->get('minpurchase'),
                "listing" => $request->get('listing'),
                "auctioned" => $auctioned,
                "bids" => $request->get('bids'),
                "durations" => $request->get('durations'),
                "auction_listing" => $request->get('auctionListing'),
                "auction_End_listing" => $request->get('auctionEndListing'),
                "deliverd_domestic" => $deliverdDomestic,
                "tags" => json_encode($request->get('tags')),
                "deliverd_international" => $deliverdInternational,
                "delivery_company" => $request->get('deliverycompany'),
                "country" => $request->get('country'),
                "city" => $request->get('city'),
                "state" => $request->get('states'),
                "shipping_price" => $request->get('shippingprice'),
                "shipping_start" => $request->get('shippingstart'),
                "shipping_end" => $request->get('shippingend'),
                "return_shipping_price" =>  $request->get('returnshippingprice'),
                "return_ship_duration_limt" => $request->get('returndurationlimit'),
                "return_ship_paid_by" => $request->get('returnshippingpaidby'),
                "return_ship_location" => $request->get('returnshippinglocation'),
                "shop_id" => $store->id,             
            ]);
            
          /**
             * For Product Ends
             */

            /**
             * For Images Uploading Start
             */
            $imageName = [];
            if($request->hasFile('file')){
                foreach ($request->file('file') as $file) {
                    // return $imageName;
                    // $file =$request->file('images');
                    $extension = $file->getClientOriginalExtension();
                    // if($extension != 'jpg' || $extension != 'png' || $extension != 'jpeg'){
                    //     return $this->genericResponse(true, 'Invalid Format', 500, ['message'=>'Invalid Format Image must be jpg or png']);                
                    // }
                    $guid = GuidHelper::getGuid();
                    $path = User::getUploadPath($user->id) . StringHelper::trimLower(Media::PRODUCT_IMAGES);
                    $name = $file->getClientOriginalName();
                    // $path = User::getUploadPath() . StringHelper::trimLower(Media::PRODUCT_IMAGES);
                    $imgName = "{$path}/{$guid}.{$extension}";
                    array_push($imageName, $name);
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
                    $image = Image::make($file)->save(public_path('image/product/') . $name);
                    // Storage::put('public/'. $imgName, $image->encode());
                    // $image = Image::make($request->file('file'));
                    //     $image->orientate();
                    //     $image->resize(1024, null, function ($constraint) {
                    //         $constraint->aspectRatio();
                    //         $constraint->upsize();
                    //    });
                    // $image->stream();
                    // Storage::put('public/'. $name, $image->encode());
                    // return [
                    //     'uid' => $media->id,
                    //     'name' => $media->url,
                    //     'status' => 'done',
                    //     'url' => $media->url,
                    //     'guid' => $media->guid,
                    //     'productguid'=> 7
                    // ];
    
                }
            }
            /**
             * For Images Uploading End
             */

            /**
             * For Product Attributes Start
             */
            // $attributes = ProductAttributes::where('product_id', $product->id)->first();
            // if($attributes){
            //     ProductAttributes::where('product_id', $product->id)->delete();
            // }
            // $sizes = json_decode($request->get('sizes'));
            // foreach($sizes as $size){
            //     foreach($size as $key => $siz){
            //         $productattributes =new ProductAttributes();
            //         $productattributes->name=$key;
            //         $productattributes->value=$siz;
            //         $productattributes->product_id=$product->id;
            //         $productattributes->save();
            //     }
            // }
            /**
             * For Product Attributes Ends
             */

        return $this->genericResponse(true, "$product->name Updated", 200, ['product' => $product->withCategory()]);
        });
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return DB::transaction(function () use (&$request, &$id) {
            $product = Product::where('guid', $id)->first();
            Product::where('guid', $id)->delete();
            Stock::where('product_id', $product->id)->delete();
            return response()->json(['message' => 'Product Deleted Successfully'], 200);
        });
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
    // public function upload(Request $request)
    {
        return DB::transaction(function () use (&$request) {
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
                'guid' => $media->guid,
                'productguid'=> $product->guid
            ];
        });
    }

    public function searched(Request $request)
    {
        return $request->get('query');
        die();
        // if ($request->get('lat') && $request->get('lng')) {

        //     $latitude = abs($request->get('lat'));
        //     $longitude = abs($request->get('lng'));
        
        //     $products = Product::where('active', true)->where('is_sold', false)
        //     ->where('IsSaved', true)
        //     ->with(['savedUsers'])
        //     ->with(['user'])
        //     ->where('name', 'LIKE', "%{$request->get('query')}%")
        //     ->when($request->has('min_price'), function ($query) use ($request) {
        //             $min_price = $request->get('min_price');
        //             $max_price = $request->get('max_price');
        //             if ($max_price) {
        //                 $query->whereBetween('price', [$min_price, $max_price]);
        //             } else {
        //                 $query->where('price', ">", $min_price);
        //             }
        //         })->with('order', function ($query) {
        //             $query->where('buyer_id', '=', Auth::guard('api')->id());
        //         })
        //         ->when($request->get('category_id'), function (Builder $builder, $category) use ($request) {
        //             // $builder->where('parent_category_id', $category);
        //             $builder->where('category_id', $category);
        //             $builder->where('is_sold', false);
        //             $builder->where('IsSaved', true);
        //             $builder->where('active', true)
        //             // $builder->where('category_id', $category)
        //                 ->when(json_decode($request->get('filters'), true), function (Builder $builder, $filters) {
        //                     $having = [];
    
        //                     foreach ($filters as $id => $value) {
        //                         if (is_bool($value)) {
        //                             $value = $value ? 'true' : 'false';
        //                         }
    
        //                         if (is_array($value)) {
        //                             $value = implode('","', $value);
        //                             $having[] = "sum(case when products_attributes.attribute_id = $id and json_overlaps(products_attributes.value, '[\"$value\"]') then 1 else 0 end) > 0";
        //                         } else {
        //                             $having[] = "sum(case when products_attributes.attribute_id = $id and json_contains(products_attributes.value, '\"$value\"') then 1 else 0 end) > 0";
        //                         }
        //                     }
    
        //                     $having = implode(' and ', $having);
        //                     $builder->whereRaw("
        //                         id in
        //                         (select products.id
        //                         from products
        //                         inner join products_attributes on products.id = products_attributes.product_id
        //                         group by products.id
        //                         having $having)
        //                     ");
        //                 });
        //         })
        //         ->orderBy(DB::raw("3959 * acos( cos( radians({$latitude}) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(-{$longitude}) ) + sin( radians({$latitude}) ) * sin(radians(latitude)) )"), 'DESC')
        //         ->orderByDesc('featured')
        //         ->orderByDesc('created_at')
        //         ->get();
        // } else {
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
                //////////////////$products = Product::where('active', true)
                ////////////////// ->where('IsSaved', true)
                ////////////////// ->with(['savedUsers'])
                ////////////////// ->with(['user'])
                ///////////////->where('name', 'LIKE', "%{$request->get('query')}%")
                // ->when($request->has('min_price'), function ($query) use ($request) {
                //     $min_price = $request->get('min_price');
                //     $max_price = $request->get('max_price');
                //     if ($max_price) {
                //         $query->whereBetween('price', [$min_price, $max_price]);
                //     } else {
                //         $query->where('price', ">", $min_price);
                //     }
                // })
                // ->with('order', function ($query) {
                //     $query->where('buyer_id', '=', Auth::guard('api')->id());
                // })  
                // ->when($request->get('category_id'), function (Builder $builder, $category) use ($request) {
                //     // $builder->where('parent_category_id', $category);
                //     $builder->where('is_sold', false);
                //     $builder->where('IsSaved', true);
                //     // $builder->where('is_sold', false);
                //     $builder->where('active', true);
                //     $builder->where('category_id', $category)
                //     // $builder->where('category_id', $category)
                //     // $builder->where('category_id', $category)
                //     // $builder->where('category_id', $category)
                //         ->when(json_decode($request->get('filters'), true), function (Builder $builder, $filters) {
                //             $having = [];
    
                //             foreach ($filters as $id => $value) {
                //                 if (is_bool($value)) {
                //                     $value = $value ? 'true' : 'false';
                //                 }
    
                //                 if (is_array($value)) {
                //                     $value = implode('","', $value);
                //                     $having[] = "sum(case when products_attributes.attribute_id = $id and json_overlaps(products_attributes.value, '[\"$value\"]') then 1 else 0 end) > 0";
                //                 } else {
                //                     $having[] = "sum(case when products_attributes.attribute_id = $id and json_contains(products_attributes.value, '\"$value\"') then 1 else 0 end) > 0";
                //                 }
                //             }
    
                //             $having = implode(' and ', $having);
                //             // $builder->whereRaw("
                //             //     id in
                //             //     (select products.id
                //             //     from products
                //             //     inner join products_attributes on products.id = products_attributes.product_id
                //             //     right join categories on products.category_id = categories.id
                //             //     group by products.id
                //             //     having $having)
                //             // ");
                //             $builder->whereRaw("
                //                 id in
                //                 (select products.id
                //                 from products
                //                 inner join products_attributes on products.id = products_attributes.product_id
                //                 right join categories on products.category_id = categories.id
                //                 group by products.id
                //                 having $having)
                //             ");
                //         });
                // })
                //////////////->distinct()
                // ->orderByDesc('featured')
                //////////////->orderByDesc('created_at')
                //////////////->get();
        // }

        // $category = Category::when($request->get('category_id'), function (Builder $builder, $category) {
        //     $builder->where('id', $category)
        //         ->with('attributes');
        // })
        //     ->where('type', Category::PRODUCT)
        //     ->get();

        // $categories = Category::with('attributes')->where('type', Category::PRODUCT)->get();
       
        // $searched= Product::get();
        // if($searched){
        //     return response()->json(['status'=> true,'data' =>$searched], 200);       
        // }else{
        //     return response()->json(['status'=> false,'data' => 'Unable to Fetch Product'], 400);        
        // }
        // return [
        //     'results' => $products,
        //     'categories' => $categories,
        //     'category' => $category
        // ];
    }
    public function getProductByPrice(Request $request, $price)
    {
        $product = Product::where('price', $price)->get();
        if($product){
            return response()->json(['status'=> true,'data' =>$product], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Products"], 400);       
        }
    }
    public function getAuctionedProducts(Request $request)
    {
        $products = Product::where('auctioned', true)->get();
        if($products){
            return response()->json(['status'=> true,'data' =>$products], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Products"], 400);       
        }
    }
    public function getProductBySize(Request $request, $size)
    {
        $products = Product::get();
        $attributes = [];
        $finalAttributes = [];
        foreach($products as $product){
            $attributes =[$product->id => json_decode($product->attributes)];
            array_push($finalAttributes, $attributes);
        }
        foreach($finalAttributes as $finalAttribute){
            print_r($finalAttribute);
        }
        // return $finalAttributes;
        // if($product){
        //     return response()->json(['status'=> true,'data' =>$product], 200);       
        // }else{
        //     return response()->json(['status'=> false,'data' =>"Unable To Get Products"], 400);       
        // }
    }
    public function getProductByPriceRange(Request $request, $min, $max)
    {
        $product = Product::whereBetween('price', [$min,$max])->get();
        if($product){
            return response()->json(['status'=> true,'data' =>$product], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Products"], 400);       
        }
    }
    public function getMin(Request $request)
    {
        $minimum = [];
        $products = Product::where('active',true)
        ->get();
        foreach($products as $product){
            array_push($minimum, $product->price);
        } 
        if($minimum){
            return response()->json(['status'=> true,'data' => min($minimum)], 200);       
        }else{
            return response()->json(['status'=> false,'data' => 'Unable to Fetch Price'], 400);        
        }
    }
    public function getMax(Request $request)
    {
        $maximum = [];
        $products = Product::where('active',true)
        ->get();
        foreach($products as $product){
            array_push($maximum, $product->price);
        } 
        if($maximum){
            return response()->json(['status'=> true,'data' => max($maximum)], 200);       
        }else{
            return response()->json(['status'=> false,'data' => 'Unable to Fetch Price'], 400);        
        }
    }
    public function getByCategory(Request $request, $id)
    {
        $products = Product::where('active',true)
            ->where('category_id', $id)
            ->get();
        if($products){
            return response()->json(['status'=> true,'data' => $products], 200);       
        }else{
            return response()->json(['status'=> false,'data' => 'Unable to Fetch Products'], 400);        
        }
    }
    // public function categories(Request $request){
    //     $products = Product::where('active',true)
    //     ->get();
    //     $category = [];
    //     foreach($products as $pro){
    //         $cat = Category::where('id',$pro->category_id)->first();
    //         array_push($category, $cat);
    //     }
    //     $resultCategory = array_unique($category);
    //     if($resultCategory){
    //         return response()->json(['status'=> true,'data' => $resultCategory], 200);       
    //     }else{
    //         return response()->json(['status'=> false,'data' => 'Unable to Fetch Category'], 400);        
    //     }
    // }
    public function categories(Request $request){
        $products = Product::where('active',true)
        ->get();
        $category = [];
        foreach($products as $pro){
            $cat = Category::where('id',$pro->category_id)->first();
            array_push($category, $cat->id);
         // array_push($category, $cat);
        }

        $resultCategory = array_unique($category);
        $resultCategories = array_values($resultCategory);
        $categories = Category::whereIn('id', $resultCategories)->get();
        if($categories){
            return response()->json(['status'=> true,'data' => $categories], 200);       
        }else{
            return response()->json(['status'=> false,'data' => 'Unable to Fetch Category'], 400);        
        }
    }
    public function getSizes(Request $request)
    {
        $attributes = ProductAttributes::get();
        $sizeattr = [];
        foreach($attributes as $attribute){
            if($attribute->name == "size"){
                array_push($sizeattr, $attribute->value);
            }
        }
        $arr = array_unique($sizeattr);
        $sizeattrUnique = array_values($arr);
        
        if($sizeattrUnique){
            return response()->json(['status'=> true,'data' => $sizeattrUnique], 200);       
        }else{
            return response()->json(['status'=> false,'data' => 'Unable to Fetch Sizes'], 400);        
        }
        // $sizes = [];
        // $products = Product::where('active',true)
        // ->get();
        // foreach($products as $product){
        //     array_push($sizes, json_decode($product->attributes));
        // } 
        // $selectedSizes = [];
        // foreach($sizes as $size){
        //     foreach($size as $siz){
        //         array_push($selectedSizes,$siz->size);
        //     }
        // }
        // $givensizes = array_unique($selectedSizes);
        // $givnsiz = [];
        // foreach($givensizes as $givensiz){
        //     array_push($givnsiz, $givensiz);
        // }
        // if($givnsiz){
        //     return response()->json(['status'=> true,'data' => $givnsiz], 200);       
        // }else{
        //     return response()->json(['status'=> false,'data' => 'Unable to Fetch Sizes'], 400);        
        // }
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

            $user = User::where('id', Auth::user()->id)->with('savedProducts')->with('shop')
            // ->with('savedServices')
            ->first();
            
            if($user){
                return response()->json(['status'=> true,'message' => 'Found WishList','data' => $user], 200);       
            }else{
                return response()->json(['status'=> false,'message' => 'Unable to get WishList'], 500);        
            }
            // $data = array_merge(json_decode($user->savedProducts));//, json_decode($user->savedServices));
            // return $user->savedServices;
            // return response()->json([
            //     'user' => Auth::user()->id,
            //     'data' => $user->savedProducts->shop,
            // ], 200);
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

    public function getProductbyStore(Request $request, $storeId)
    {
        
        $products=Product::join('categories as categories','categories.id','=','products.category_id')
            ->where('products.active', true)
            ->where('products.price', '<>', null)
            ->with(['user'])
            ->with(['savedUsers'])
            ->where($this->applyFilters($request))
            ->orderByDesc('products.created_at')
            ->get([
                'categories.name as category',
                'products.*'
            ]);
        return $products; 
    }

    public function getcategorybyStore(Request $request, $storeId)
    {
        $products=Product::with('category') 
            ->where('shop_id', $storeId)
            ->get();
        return $products; 
    }

    public function results(Request $request, $search)
    {
        $products=Product::with('category') 
            ->where('name', 'like', '%' . $search . '%')
            ->get();
        if($products){
            return response()->json(['status'=> true,'data' =>$products], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Related Products"], 400);       
        }   
    }

    public function relatedProduct(Request $request, $guid)
    {
        $category = Category::where('guid', $guid)->first();
        $products=Product::with('category') 
            ->with(['user'])
            ->with(['media'])
            ->with(['savedUsers'])
            ->where('category_id',  $category->id)
            ->get();
        $data = [
            'products' =>$products,
            'category' => $category
        ];
        if($products){
            return response()->json(['status'=> true,'data' =>$data], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get Related Products"], 400);       
        }   
    }
    public function savedSearch(Request $request){
        return DB::transaction(function () use ($request) {
            //Delete previous Searches if exists
            $getsavesearch = SaveSearch::where('user_id',Auth::user()->id)
                ->where('keywords', $request->get("keywords"))->delete();
            //Insert new save searches
            $savedSearches =new  SaveSearch();
            $savedSearches->user_id = Auth::user()->id;
            $savedSearches->keywords = $request->get("keywords");
            $savedSearches->email_alert = $request->get("emailAlert");
            $savedSearches->guid = GuidHelper::getGuid();
            $savedSearches->save();
            if($savedSearches){
                return response()->json(['status'=> true,'data' =>'Saved on saved searches'], 200);       
            }else{
                return response()->json(['status'=> false,'data' =>"Unable To Saved on saved searches"], 400);       
            }   
        });
    }
    public function getSavedSearch(Request $request){
        $savedSearches =SaveSearch::where('user_id', Auth::user()->id)->get();
        if($savedSearches){
            return response()->json(['status'=> true,'data' =>$savedSearches], 200);       
        }else{
            return response()->json(['status'=> false,'data' =>"Unable To Get  Saved searches"], 400);       
        }   
    }
    public function getRecomemdedProducts(Request $request,$shops)
    {
        return $shops;
        // $products = Product::whereIn('shop_id', $request->get('shops'))->get();
        // if($products){
        //     return response()->json(['status'=> true,'data' =>$products], 200);       
        // }else{
        //     return response()->json(['status'=> false,'data' =>"Unable To Get Recomended Products"], 400);       
        // }
    }
    public function getCompanies(Request $request){
        $deleiverycomapny =  DeliverCompany::get();
        if($deleiverycomapny){
            return response()->json(['status'=>'true','data'=>"Delivery Company Found!", 'data'=> $deleiverycomapny],200);
        }else{
            return response()->json(['status'=>'false','message'=>'Unable to Find Delivery Company'],403);
        }

    }
}
