<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var int
     */
    protected $row_per_page;

    /**
     * @var int
     */
    protected $page;

    /**
     * @var null
     */
    protected $keyword;

    /**
     * @var
     */
    protected $auth;

    /**
     * @var
     */
    protected $admin;

    /**
     * @var mixed|string
     */
    protected $neighbor_id = '';

    /**
     * @var mixed|string
     */
    protected $product_id = '';

    /**
     * @var mixed|string
     */
    protected $confectionery_id = '';

    /**
     * @var string
     */
    protected $lat;

    /**
     * @var mixed
     */
    protected $lng;

    /**
     * @var array|mixed
     */
    protected $categories = [];

    /**
     * @var array|mixed
     */
    protected $tags = [];

    /**
     * @var mixed|string
     */
    protected $sort = '';

    /**
     * @var string
     */
    protected $sort_lat = '';

    /**
     * @var string
     */
    protected $sort_lng = '';

    /**
     * @var bool|mixed
     */
    protected $show_all_confectioneries = false;

    /**
     * @var mixed
     */
    protected $status;

    protected $start_date;

    protected $end_date;

    public function __construct(Request $request){

        $this->middleware(function ($request, $next) {
            $this->auth = Auth::user();
            return $next($request);
        });

        $this->request = $request;
        if( $this->request->has('row_per_page') ){
            if( $this->request->get('row_per_page') == '-1') {
                $this->row_per_page = 99999999;
            }
            else {
                $this->row_per_page = $this->request->get('row_per_page');
            }
        }
        else {
            $this->row_per_page = 12;
        }

        if( $this->request->get('page') ) {
            $this->page = $this->request->get('page');
        }
        else {
            $this->page = 1;
        }

        if( $this->request->has('search') ) {
            $this->keyword = $this->request->get('search');
        }
        else {
            $this->keyword = null;
        }

        if( $this->request->has('lat') ) {
            $this->lat = $this->request->lat;
        }
        else {
            $this->lat = '';
        }

        if( $this->request->has('sort_lat') ) {
            $this->sort_lat = $this->request->sort_lat;
        }
        else {
            $this->sort_lat = '';
        }


        if( $this->request->has('lng') ) {
            $this->lng = $this->request->lng;
        }
        else {
            $this->lat = '';
        }

        if( $this->request->has('sort_lng') ) {
            $this->sort_lng = $this->request->sort_lng;
        }
        else {
            $this->sort_lng = '';
        }

        if( $this->request->has('neighbor_id') ) {
            $this->neighbor_id = $this->request->get('neighbor_id');
        }

        if( $this->request->has('product_id') ) {
            $this->product_id = $this->request->get('product_id');
        }

        if( $this->request->has('confectionery_id') ) {
            $this->confectionery_id = $this->request->get('confectionery_id');
        }

        if( $this->request->has('categories') ) {
            $this->categories = $this->request->get('categories');
        }

        if( $this->request->has('tags') ) {
            $this->tags = $this->request->get('tags');
        }

        if( $this->request->has('sort') ) {
            $this->sort = $this->request->get('sort');
        }

        if( $this->request->has('show_all_confectioneries') ) {
            $this->show_all_confectioneries = $this->request->get('show_all_confectioneries');
        }

        if( $this->request->has('status') ) {
            $this->status = $this->request->get('status');
        }

        if( $this->request->has('start_date') ) {
            $this->start_date = $this->request->get('start_date');
        }

        if( $this->request->has('end_date') ) {
            $this->end_date = $this->request->get('end_date');
        }

        $this->admin = User::where('type', 'admin')->first();
    }

    /**
     * Using this method to generate the appropriate paginate
     *
     * @param $query
     * @return array
     */
    protected function paginate($query){

        $paginate = $query->paginate($this->row_per_page, null, null, $this->page);
        return [$paginate->toArray(), $paginate->render()->toHtml()];
    }



    public function convertShamsiToGregorian($shamsi_date){

        if(is_array($shamsi_date)){
            $greg = mds_to_gregorian($shamsi_date[0], $shamsi_date[1], $shamsi_date[2]);
            $greg[1] = sprintf('%02d', $greg[1]);
            $greg[2] = sprintf('%02d', $greg[2]);
            return implode('-', $greg). ' 00:00:00';
        } else {
            $shamsi_date = explode('/', $shamsi_date);
            $greg = mds_to_gregorian($shamsi_date[0], $shamsi_date[1], $shamsi_date[2]);
            $greg[1] = sprintf('%02d', $greg[1]);
            $greg[2] = sprintf('%02d', $greg[2]);
            return implode('-', $greg). ' 00:00:00';
        }
    }


}
