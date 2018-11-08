<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceArticle extends Model
{

    protected $table = 'invoice_article';

    protected $fillable = ['product_id', 'invoice_id', 'total', 'price',
        'discount', 'profit', 'status', 'weight', 'count', 'meta'];

    protected $appends = ['confectionery_name', 'confectionery_avatar'];

    /**
     * @return HasOne
     */
    public function product(): HasOne
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

    /**
     * @return mixed
     */
    public function getConfectioneryNameAttribute(){

        return User::whereId(Product::whereId($this->product_id)->first()->owner)->first()->meta->pastry_name;
    }

    /**
     * @return mixed
     */
    public function getConfectioneryAvatarAttribute(){
        return User::whereId(Product::whereId($this->product_id)->first()->owner)->first()->avatar;
    }

    /**
     * @param $product
     * @param $invoice_header
     * @param $price
     */
    public function createNewArticle($product, $invoice_header, $price){

        $product_model = Product::whereId($product['product_id'])->first();
        static::create([
            'product_id' =>$product['product_id'],
            'invoice_id' =>$invoice_header->id,
            'meta' =>json_encode($product['meta']),
            'count' =>$product['count'],
            'discount' =>isset($price['discounted']) ? (($price['discounted'] == 0) ? $price['price'] : $price['discounted']) : $price["price"],
            'profit' =>$product_model->commission,
            'price' =>$price['price'],
            'weight' =>$product['weight']
        ]);
    }

}
