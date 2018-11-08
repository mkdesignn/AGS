<template>
    <div v-if="is_loaded" class="order_wrapper">
        <headers></headers>
        <section class="order" v-if="status">
            <div class="contant-page-bg">
                <div class="container">
                    <div class="content-order">
                        <div class="row justify-content-lg-center">
                            <div class="col col-lg-10 col-md-12">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="conf-state">
                                            <div class="logo-image">
                                                <img :src="order.articles[0].confectionery_avatar" alt="قنادی ناتلی">
                                            </div>
                                            <h4 v-html="order.articles[0].confectionery_name"></h4>
                                            <div class="state-order">
                                                <span>{{order.status_per}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="details-order">
                                            <ul>
                                                <li>
                                                    <span class="subject-detail">شماره پیگیری</span>
                                                    <span class="result-details">{{order.code}}</span>
                                                </li>
                                                <li>
                                                    <span class="subject-detail">نحوه پرداخت</span>
                                                    <span class="result-details">آنلاین</span>
                                                </li>
                                                <li>
                                                    <span class="subject-detail">زمان سفارش</span>
                                                    <span class="result-details">{{time}}</span>
                                                </li>
                                                <li>
                                                    <span class="subject-detail">تاریخ سفارش</span>
                                                    <span class="result-details">{{date}}</span>
                                                </li>
                                                <li>
                                                    <span class="subject-detail">آدرس ارسال :</span>
                                                    <span>{{order.address}}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="more-detail-order">
                                            <h4>جزئیات سفارش</h4>
                                            <div class="more-order">
                                                <div class="head-more-order">
                                                    <span class="name">نام سفارش</span>
                                                    <div class="left-more-order">
                                                        <span class="count">تعداد</span>
                                                        <span class="price">قیمت</span>
                                                    </div>
                                                </div>
                                                <ul class="list-more-order">
                                                    <li v-for="(article, index) in order.articles">
                                                        <span class="counter">{{index + 1}}</span>
                                                        <h4>{{article.product.name}}</h4>
                                                        <div class="left-more-order">
                                                            <span class="more-count">
                                                                {{article.count}}
                                                                <span class="r-count"> عدد</span>
                                                            </span>
                                                            <span class="more-price">{{(article.price / 10) | currency}} تومان</span>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                            <ul class="result-more-order">
                                                <li>
                                                    <span>جمع سفارش</span>
                                                    <span class="price">{{(order.amount / 10) | currency}} تومان</span>
                                                </li>
                                                <li v-if="$store.state.verify_code.discount != undefined">
                                                    <span>تخفیف (کد کوپن)</span>
                                                    <span v-if="$store.state.verify_code.type == 'rial'" class="price">{{($store.state.verify_code.discount / 10) | currency}} تومان</span>
                                                    <span v-if="$store.state.verify_code.type == 'percent'" class="price">{{$store.state.verify_code.discount}} % </span>
                                                </li>
                                                <li>
                                                    <span>هزینه ارسال</span>
                                                    <span class="price">{{(order.send_price / 10) | currency}} تومان</span>
                                                </li>
                                            </ul>
                                            <div class="result-order">
                                                <span>مبلغ قابل پرداخت</span>
                                                <span class="price">{{(order.total_amount / 10) | currency}} تومان</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section><!-- End order -->
        <div class="load-wrapp d-block padding-50" v-if="!status && !error">
            <div class="load-3">
                <div class="line"></div>
                <div class="line"></div>
                <div class="line"></div>
            </div>
            <span>در حال بررسی</span>
        </div>
        <section class="error text-center padding-15" v-if="error">
            <span v-html="error_message"></span>
        </section>
    </div>
</template>

<script>

    import headers from './header.vue';
    import footers from './footer.vue';
    import {currency, reverseCurrency} from '../../filters.js';

    export default{
        components:{
            headers,
            footers
        },
        data(){
            return {
                is_loaded: false,
                order: '',
                error_message: '',
                error: false,
                status: false,
                order_code: ''
            }
        },
        mounted(){

            var vm = this;

            // we show the user payment and also pay the user invoice using the below condition
            if(vm.$store.state.pay_type == false){
                if( Object.keys(vm.$router.currentRoute.params).length > 0 )
                    vm.getOrderInfo(vm.$router.currentRoute.params.id);
            } else {
                if(vm.$store.state.pay_type == 'online'){

                    if(vm.$store.state.bank_response.ResCode != '0')
                        window.location.href = window.location.origin;
                    else
                        vm.checkPayment();
                } else
                    vm.checkPayment(vm.$router.currentRoute.params.id);
            }

            // this.$store.state.pre_loader = false
            this.is_loaded = true;
        },
        methods:{
            checkPayment(order_id){

                var vm = this;
                vm.$store.state.bank_response.pay_type = vm.$store.state.pay_type;
                if(order_id != undefined)
                    this.order_code = vm.$store.state.bank_response.SaleOrderId = order_id;
                else
                    this.order_code = order_id = vm.$store.state.bank_response.SaleOrderId;

                axios.post(vm.$store.state.base_info.base_url+"/gateway/check-payment", vm.$store.state.bank_response).then(function(response){

                    vm.getOrderInfo(order_id, function(response){
                        vm.order = response.data;
                        vm.status = true;
                        // reload the credit of the user
                        if(vm.$store.state.pay_type == "credit"){

                            vm.$store.state.user.credits = vm.$store.state.user.credits - vm.order.total_amount;
                            window.localStorage.setItem("user_info", JSON.stringify(vm.$store.state.user));
                        }

                        vm.$store.state.cart.products = [];
                        window.localStorage.setItem("cart", '{"products": ""}');
                    });

                }).catch( (response) => {
                    vm.error = true;
                    vm.error_message = response.response.data.msg;
                });
            },
            getOrderInfo(id, response){

                var vm = this,
                    request = axios.get(vm.$store.state.base_info.base_url+"/user/order/"+id, vm.$store.state.bank_response);

                if(response != undefined){
                    request.then(response);
                } else {
                    axios.get(vm.$store.state.base_info.base_url+"/user/order/"+id, vm.$store.state.bank_response).then( (response) => {
                        vm.order = response.data;
                        vm.status = true;
                    });
                }
            }
        },
        computed:{
            date(){
                if( this.order != "" )
                    return this.order.order_date;
            },
            time(){
                if(this.order != "")
                   return this.order.created_at.split(" ")[1];
            }
        },
        filters:{
            currency, reverseCurrency
        }
    }

</script>